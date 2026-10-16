<?php

namespace Tests\Integration\Publication;

use Illuminate\Validation\ValidationException;
use PDO;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A plain PHPUnit controller deliberately does not boot Laravel/RefreshDatabase:
 * fixtures must be committed and each actor owns an independent real connection.
 */
final class PublicationConcurrencyTest extends TestCase
{
    private array $children = [];

    private PDO $observer;

    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('CONCURRENCY_RUNTIME') || ! getenv('CONCURRENCY_DATABASE') || ! getenv('CONCURRENCY_USER') || ! getenv('CONCURRENCY_PORT')) {
            self::markTestSkipped('Requires an explicitly provisioned, verified PostgreSQL 17 disposable concurrency runtime (TESTING_SKILL.md).');
        }
        $this->observer = new PDO('pgsql:host=127.0.0.1;port='.getenv('CONCURRENCY_PORT').';dbname='.getenv('CONCURRENCY_DATABASE'), getenv('CONCURRENCY_USER'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $identity = $this->observer->query('SELECT current_database(), current_user, current_setting(\'server_version_num\')')->fetch(PDO::FETCH_NUM);
        self::assertSame(getenv('CONCURRENCY_DATABASE'), $identity[0]);
        self::assertStringStartsWith('concurrency_', $identity[0]);
        self::assertSame(getenv('CONCURRENCY_USER'), $identity[1]);
        self::assertSame(17, intdiv((int) $identity[2], 10000));
    }

    protected function tearDown(): void
    {
        foreach ($this->children as [$process, $pipes]) {
            if (proc_get_status($process)['running']) {
                proc_terminate($process);
            }
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        parent::tearDown();
    }

    public static function orders(): array
    {
        return [
            'same receipt, two independent Undo actors' => ['rename', 'undo', 'undo', 'for update'],
            'Commit wins before Undo eligibility check' => ['rename', 'commit', 'undo', 'lock table public.'],
            'Revert waits for the new LIVE Commit' => ['revert', 'commit', 'revert', 'pg_advisory_xact_lock'],
            'Restore wins before storage reclaim' => ['media', 'restore', 'reclaim', 'pg_advisory_xact_lock'],
            'Released recovery root rejects a waiting Restore' => ['media', 'reclaim', 'restore', 'pg_advisory_xact_lock'],
            'Commit publishes the fully restored media state' => ['media', 'restore', 'commit', 'pg_advisory_xact_lock'],
            'Activity feedback for competing Undo actions' => ['rename', 'ui-undo', 'ui-undo', 'for update'],
            'Undo wins before Commit copies Working' => ['rename', 'undo', 'commit', 'for update'],
        ];
    }

    #[DataProvider('orders')]
    public function test_serial_outcomes_at_observed_database_lock_points(string $scenario, string $first, string $second, string $pause): void
    {
        $setup = $this->start(['operation' => 'prepare', 'scenario' => $scenario]);
        $fixture = $this->read($setup, 'result')['fixture'];
        $auditBefore = $this->observer->query('SELECT id, row_to_json(audit_events)::text AS payload FROM audit_events ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $notificationIds = $this->observer->query('SELECT id FROM admin_notifications')->fetchAll(PDO::FETCH_COLUMN);
        $arguments = ['actor' => $fixture['actor'], 'receipt' => $fixture['receipt'], 'event' => $fixture['event']];
        if (isset($fixture['target'])) {
            $arguments['target'] = $fixture['target'];
        }
        $a = $this->start(['operation' => $first, 'pause' => $pause, ...$arguments]);
        $this->read($a, 'started');
        $locked = $this->read($a, 'locked');
        self::assertStringContainsString($pause, strtolower($locked['sql']));
        $b = $this->start(['operation' => $second, ...$arguments]);
        $this->read($b, 'started');
        try {
            $this->assertBlockedBy($b, $a);
        } finally {
            fwrite($this->children[$a][1][0], "continue\n");
            fflush($this->children[$a][1][0]);
        }
        $resultA = $this->read($a, 'result');
        $resultB = $this->read($b, 'result');
        self::assertTrue($resultA['ok'], json_encode($resultA));
        $expectedRejection = $first === 'reclaim' || ($first === 'commit' && $second === 'undo') || ($first === 'undo' && $second === 'undo');
        self::assertSame(! $expectedRejection, $resultB['ok'], json_encode($resultB));
        if ($expectedRejection) {
            self::assertSame(ValidationException::class, $resultB['exception']);
        }
        if ($first === 'ui-undo') {
            self::assertCount(1, $resultA['value']['notifications']);
            self::assertSame('success', $resultA['value']['notifications'][0]['status']);
            self::assertSame(1, $resultA['value']['header_events']);
            self::assertCount(1, $resultB['value']['notifications']);
            self::assertSame('warning', $resultB['value']['notifications'][0]['status']);
            self::assertSame(1, $resultB['value']['header_events']);
            $after = $this->observer->query('SELECT id FROM admin_notifications')->fetchAll(PDO::FETCH_COLUMN);
            self::assertCount(10, $after, 'Retention remains bounded, not total-count + 2.');
            self::assertEqualsCanonicalizing([$resultA['value']['notifications'][0]['id'], $resultB['value']['notifications'][0]['id']], array_values(array_diff($after, $notificationIds)));
        }
        $auditAfter = $this->observer->query('SELECT id, row_to_json(audit_events)::text FROM audit_events ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        self::assertSame($auditBefore, array_intersect_key($auditAfter, $auditBefore), 'Existing audit is append-only.');
        $live = $this->observer->query('SELECT * FROM publication_checkpoints ORDER BY published_at DESC, id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        if ($scenario === 'rename') {
            $undoFirst = in_array($first, ['undo', 'ui-undo'], true);
            $name = $undoFirst ? 'Original Gallery' : 'Renamed Gallery';
            self::assertSame($name, $this->observer->query('SELECT name FROM artwork_categories WHERE id='.$fixture['gallery'])->fetchColumn());
            self::assertSame($name, $this->observer->query('SELECT title FROM site_sections WHERE artwork_category_id='.$fixture['gallery'])->fetchColumn());
            self::assertSame($undoFirst ? 1 : 0, (int) $this->observer->query("SELECT count(*) FROM audit_events WHERE action = 'admin.undo_applied'")->fetchColumn());
            if ($first === 'commit') {
                self::assertSame($name, $this->observer->query('SELECT name FROM committed.artwork_categories WHERE id='.$fixture['gallery'])->fetchColumn());
                self::assertSame(0, (int) $this->observer->query('SELECT count(*) FROM admin_action_receipts WHERE id='.$fixture['receipt'])->fetchColumn());
            } else {
                self::assertNotEmpty($this->observer->query('SELECT undone_at FROM admin_action_receipts WHERE id='.$fixture['receipt'])->fetchColumn());
            }
        } elseif ($scenario === 'revert') {
            self::assertSame('Third Gallery', $this->observer->query('SELECT name FROM committed.artwork_categories WHERE id='.$fixture['gallery'])->fetchColumn());
            self::assertSame('Renamed Gallery', $this->observer->query('SELECT name FROM artwork_categories WHERE id='.$fixture['gallery'])->fetchColumn());
            self::assertSame($fixture['second'], (int) $live['parent_publication_checkpoint_id']);
            $context = $this->observer->query('SELECT operation, source_publication_checkpoint_id FROM publication_working_context WHERE id=1')->fetch(PDO::FETCH_ASSOC);
            self::assertSame('revert', $context['operation']);
            self::assertSame((int) $live['id'], (int) $context['source_publication_checkpoint_id']);
        } else {
            $file = getenv('LARAVEL_STORAGE_PATH').'/app/private/'.$fixture['key'];
            if ($first === 'reclaim') {
                self::assertFalse(is_file($file));
                self::assertSame('deleted', $this->observer->query('SELECT state FROM media_assets WHERE id='.$fixture['asset'])->fetchColumn(), 'Rejected Restore must not erase or partially reconstruct Working.');
                self::assertSame(0, (int) $this->observer->query("SELECT count(*) FROM audit_events WHERE action='publication.version_restored'")->fetchColumn());
            } else {
                self::assertFileExists($file);
                self::assertSame($fixture['sha'], hash_file('sha256', $file));
                self::assertSame('available', $this->observer->query('SELECT state FROM media_assets WHERE id='.$fixture['asset'])->fetchColumn());
                self::assertSame($second === 'commit' ? 'available' : 'deleted', $this->observer->query('SELECT state FROM committed.media_assets WHERE id='.$fixture['asset'])->fetchColumn());
                $context = $this->observer->query('SELECT operation, source_publication_checkpoint_id FROM publication_working_context WHERE id=1')->fetch(PDO::FETCH_ASSOC);
                if ($second === 'commit') {
                    self::assertSame('restore', $live['operation']);
                    self::assertSame($fixture['target'], (int) $live['source_publication_checkpoint_id']);
                    self::assertSame($fixture['deleted'], (int) $live['parent_publication_checkpoint_id']);
                    self::assertTrue($context === false || $context['operation'] === null);
                } else {
                    self::assertSame('restore', $context['operation']);
                    self::assertSame($fixture['target'], (int) $context['source_publication_checkpoint_id']);
                }
            }
        }
        // The checkpoint hash must describe the complete LIVE state, including
        // media references; the independent worker computes the canonical hash.
        $state = $this->start(['operation' => 'inspect', 'actor' => $fixture['actor']]);
        $this->read($state, 'started');
        $inspection = $this->read($state, 'result');
        self::assertTrue($inspection['ok'], json_encode($inspection));
        self::assertSame($live['snapshot_hash'], $inspection['value']['live_hash']);
    }

    private function start(array $request): int
    {
        $command = [PHP_BINARY, '-d', 'variables_order=EGPCS', '-d', 'sys_temp_dir='.getenv('CONCURRENCY_RUNTIME').'/php-temp', '-d', 'upload_tmp_dir='.getenv('CONCURRENCY_RUNTIME').'/php-upload', dirname(__DIR__, 2).'/Support/PublicationConcurrencyWorker.php', json_encode($request, JSON_THROW_ON_ERROR)];
        $process = proc_open($command, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, dirname(__DIR__, 3));
        self::assertIsResource($process);
        $index = count($this->children);
        $this->children[$index] = [$process, $pipes];
        $verified = $this->read($index, 'verified');
        self::assertSame(getenv('CONCURRENCY_DATABASE'), $verified['identity']['db']);
        $this->children[$index][2] = (int) $verified['identity']['pid'];

        return $index;
    }

    public function test_restore_rollback_releases_waiting_commit_without_partial_state_and_allows_retry(): void
    {
        $setup = $this->start(['operation' => 'prepare', 'scenario' => 'media']);
        $fixture = $this->read($setup, 'result')['fixture'];
        $before = $this->observer->query('SELECT id, row_to_json(audit_events)::text FROM audit_events ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR);
        $arguments = ['actor' => $fixture['actor'], 'target' => $fixture['target']];
        $restore = $this->start(['operation' => 'restore', 'pause' => 'pg_advisory_xact_lock', 'fail_after_audit' => true, ...$arguments]);
        $this->read($restore, 'started');
        $this->read($restore, 'locked');
        $commit = $this->start(['operation' => 'commit', ...$arguments]);
        $this->read($commit, 'started');
        try {
            $this->assertBlockedBy($commit, $restore);
        } finally {
            fwrite($this->children[$restore][1][0], "continue\n");
            fflush($this->children[$restore][1][0]);
        }
        $failure = $this->read($restore, 'result');
        self::assertFalse($failure['ok']);
        self::assertSame(\RuntimeException::class, $failure['exception']);
        self::assertStringStartsWith('Injected failure after restored rows', $failure['message']);
        $committed = $this->read($commit, 'result');
        self::assertTrue($committed['ok'], json_encode($committed));
        self::assertNull($committed['value'], 'Commit sees rolled-back Working and is a no-op.');
        self::assertSame($before, $this->observer->query('SELECT id, row_to_json(audit_events)::text FROM audit_events ORDER BY id')->fetchAll(PDO::FETCH_KEY_PAIR));
        self::assertSame('deleted', $this->observer->query('SELECT state FROM media_assets WHERE id='.$fixture['asset'])->fetchColumn());
        self::assertSame('deleted', $this->observer->query('SELECT state FROM committed.media_assets WHERE id='.$fixture['asset'])->fetchColumn());
        self::assertSame($fixture['deleted'], (int) $this->observer->query('SELECT max(id) FROM publication_checkpoints')->fetchColumn());
        self::assertSame(0, (int) $this->observer->query("SELECT count(*) FROM admin_notifications WHERE status='success'")->fetchColumn());
        $file = getenv('LARAVEL_STORAGE_PATH').'/app/private/'.$fixture['key'];
        self::assertSame($fixture['sha'], hash_file('sha256', $file));
        $retry = $this->start(['operation' => 'restore', ...$arguments]);
        $this->read($retry, 'started');
        self::assertTrue($this->read($retry, 'result')['ok']);
        self::assertSame('available', $this->observer->query('SELECT state FROM media_assets WHERE id='.$fixture['asset'])->fetchColumn());
        self::assertSame('deleted', $this->observer->query('SELECT state FROM committed.media_assets WHERE id='.$fixture['asset'])->fetchColumn());
        self::assertSame($fixture['sha'], hash_file('sha256', $file));
    }

    private function read(int $child, string $phase): array
    {
        $line = fgets($this->children[$child][1][1]);
        $message = $line === false ? null : json_decode($line, true);
        self::assertIsArray($message, 'Missing worker response: '.($line ?: stream_get_contents($this->children[$child][1][2])));
        self::assertSame($phase, $message['phase'], json_encode($message));

        return $message;
    }

    private function assertBlockedBy(int $waiter, int $owner): void
    {
        $query = $this->observer->prepare('SELECT wait_event_type, wait_event, :owner = ANY(pg_blocking_pids(pid)) AS blocked FROM pg_stat_activity WHERE pid = :waiter');
        $deadline = hrtime(true) + 8_000_000_000;
        do {
            $query->execute(['owner' => $this->children[$owner][2], 'waiter' => $this->children[$waiter][2]]);
            $row = $query->fetch(PDO::FETCH_ASSOC);
            // pg_stat_activity and pg_blocking_pids are sampled separately by
            // PostgreSQL. Require both observations together, not a transition
            // where the blocker exists but the wait-event field is not set yet.
            if ($row && $row['blocked'] && $row['wait_event_type'] === 'Lock') {
                self::assertSame('Lock', $row['wait_event_type']);
                self::assertContains($row['wait_event'], ['advisory', 'transactionid', 'relation', 'tuple']);

                return;
            }
        } while (hrtime(true) < $deadline);
        self::fail('No actual PostgreSQL blocking relationship observed: '.json_encode($row));
    }
}
