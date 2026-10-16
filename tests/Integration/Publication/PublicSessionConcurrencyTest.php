<?php

namespace Tests\Integration\Publication;

use App\Http\Middleware\UseCommittedPublicState;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use PDO;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

/** No RefreshDatabase: independent HTTP workers must see committed fixtures. */
#[Group('integration')]
#[Group('concurrency')]
final class PublicSessionConcurrencyTest extends TestCase
{
    private array $servers = [];

    private array $urls = [];

    private array $curl = [];

    private ?\CurlMultiHandle $multi = null;

    private PDO $observer;

    private string $root;

    private string $cookie = '';

    protected function setUp(): void
    {
        parent::setUp();
        if (! getenv('PUBLIC_SESSION_RUNTIME')) {
            self::markTestSkipped('Requires the explicitly isolated RunPublicSessionConcurrency.ps1 runtime.');
        }
        $this->root = getenv('PUBLIC_SESSION_RUNTIME');
        self::assertStringContainsString('/storage/framework/testing/public-session-', str_replace('\\', '/', (string) realpath($this->root)));
        self::assertStringStartsWith('public_session_', getenv('PUBLIC_SESSION_DATABASE'));
        self::assertSame('database', getenv('SESSION_DRIVER'));
        $verified = json_decode(file_get_contents($this->root.'/verified.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(getenv('PUBLIC_SESSION_DATABASE'), $verified['identity'][0]);
        self::assertSame(getenv('PUBLIC_SESSION_CLUSTER'), $verified['identity'][4]);
        $this->observer = new PDO('pgsql:host=127.0.0.1;port='.getenv('DB_PORT').';dbname='.getenv('PUBLIC_SESSION_DATABASE'), getenv('PUBLIC_SESSION_DATABASE'), getenv('DB_PASSWORD'), [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        self::assertSame(getenv('PUBLIC_SESSION_DATABASE'), $this->observer->query('SELECT current_database()')->fetchColumn());
        self::assertSame(getenv('PUBLIC_SESSION_CLUSTER'), $this->observer->query('SELECT system_identifier::text FROM pg_control_system()')->fetchColumn());
        self::assertSame(getenv('PUBLIC_SESSION_DATABASE'), $this->observer->query('SELECT current_user')->fetchColumn());
        self::assertSame(17, intdiv((int) $this->observer->query('SHOW server_version_num')->fetchColumn(), 10000));
        $this->observer->exec("SET statement_timeout='5s'");
        $this->observer->exec('DELETE FROM sessions');
        foreach ([0, 1] as $index) {
            $socket = stream_socket_server('tcp://127.0.0.1:0');
            $address = stream_socket_get_name($socket, false);
            fclose($socket);
            $this->urls[] = 'http://'.$address;
            $command = [PHP_BINARY, '-d', 'variables_order=EGPCS', '-d', 'sys_temp_dir='.$this->root.'/php-temp',
                '-d', 'upload_tmp_dir='.$this->root.'/php-upload', '-S', $address, 'tests/Support/PublicSessionHttpWorker.php'];
            $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['file', $this->root.'/http-'.$index.'.log', 'a'], 2 => ['file', $this->root.'/http-'.$index.'.log', 'a']], $pipes, dirname(__DIR__, 3));
            self::assertIsResource($process);
            $this->servers[] = [$process, $pipes];
            $deadline = microtime(true) + 10;
            do {
                $ready = @stream_socket_client('tcp://'.$address, $error, $message, 0.1);
                if ($ready) {
                    fclose($ready);
                    break;
                }
            } while (microtime(true) < $deadline);
            self::assertNotFalse($ready, 'HTTP worker did not start: '.$this->root.'/http-'.$index.'.log');
        }
        // A real encrypted Laravel cookie, issued by StartSession and shared
        // across both independent HTTP workers (cookies are not port-scoped).
        $warmup = $this->request(0, '/robots.txt');
        self::assertSame(200, $warmup['status'], $warmup['body']);
        preg_match_all('/^Set-Cookie: ([^;\r\n]+)/mi', $warmup['headers'], $cookies);
        $this->cookie = implode('; ', $cookies[1]);
        self::assertNotSame('', $this->cookie);
        self::assertSame(1, (int) $this->observer->query('SELECT count(*) FROM sessions')->fetchColumn());
    }

    protected function tearDown(): void
    {
        if (isset($this->observer)) {
            $this->observer->query('SELECT pg_advisory_unlock_all()');
        }
        if ($this->multi) {
            foreach ($this->curl as $handle) {
                curl_multi_remove_handle($this->multi, $handle);
                curl_close($handle);
            }
            curl_multi_close($this->multi);
        }
        foreach ($this->servers as [$process, $pipes]) {
            proc_terminate($process);
            foreach ($pipes as $pipe) {
                fclose($pipe);
            }
            proc_close($process);
        }
        parent::tearDown();
    }

    public function test_parallel_spa_navigation_saves_one_database_session_outside_the_committed_snapshot(): void
    {
        $controllerPid = (int) $this->observer->query('SELECT pg_backend_pid()')->fetchColumn();
        $this->observer->query('SELECT pg_advisory_lock(781001), pg_advisory_lock(781002)');
        $this->multi = curl_multi_init();
        foreach (['session-exhibitions', 'session-cv'] as $index => $slug) {
            $handle = $this->handle($index, '/'.$slug, ['X-Livewire-Navigate: true', 'X-Session-Probe: parallel-'.$index, 'X-Session-Barrier: '.(781001 + $index)]);
            $this->curl[] = $handle;
            curl_multi_add_handle($this->multi, $handle);
        }
        $deadline = microtime(true) + 12;
        do {
            curl_multi_exec($this->multi, $running);
            $blocked = $this->observer->query("SELECT pid FROM pg_stat_activity WHERE $controllerPid = ANY(pg_blocking_pids(pid)) AND wait_event_type = 'Lock' AND wait_event = 'advisory' ORDER BY pid")->fetchAll(PDO::FETCH_COLUMN);
        } while (count($blocked) !== 2 && microtime(true) < $deadline);
        self::assertCount(2, $blocked, 'Both independent HTTP requests must simultaneously wait inside their snapshots.');
        self::assertNotSame($blocked[0], $blocked[1]);
        // A later committed revision is invisible to both already-open reads.
        $this->observer->exec("UPDATE committed.site_sections SET title='Later publication' WHERE slug IN ('session-exhibitions','session-cv')");
        $this->observer->query('SELECT pg_advisory_unlock(781001)');
        $done = [];
        $deadline = microtime(true) + 12;
        do {
            curl_multi_exec($this->multi, $running);
            while ($info = curl_multi_info_read($this->multi)) {
                $done[spl_object_id($info['handle'])] = $info['result'];
            }
        } while (! isset($done[spl_object_id($this->curl[0])]) && microtime(true) < $deadline);
        self::assertArrayHasKey(spl_object_id($this->curl[0]), $done, 'First HTTP response, including its session UPDATE, must finish before releasing the second.');
        self::assertArrayNotHasKey(spl_object_id($this->curl[1]), $done);
        $this->observer->query('SELECT pg_advisory_unlock(781002)');
        $deadline = microtime(true) + 12;
        do {
            curl_multi_exec($this->multi, $running);
        } while ($running && microtime(true) < $deadline);
        self::assertSame(0, $running);
        foreach ($this->curl as $index => $handle) {
            self::assertSame(200, curl_getinfo($handle, CURLINFO_RESPONSE_CODE), 'Parallel navigation '.$index.' must succeed; see isolated HTTP log.');
        }
        $sessionIds = [];
        foreach ($this->curl as $index => $handle) {
            $raw = curl_multi_getcontent($handle);
            $evidence = json_decode(file_get_contents($this->root.'/parallel-'.$index.'.json'), true, flags: JSON_THROW_ON_ERROR);
            $view = $evidence['views'][0];
            $expected = 'Committed '.(['session-exhibitions', 'session-cv'][$index]);
            self::assertSame($expected, $view['before']);
            self::assertSame($expected, $view['after']);
            self::assertSame(1, $view['level']);
            self::assertSame('repeatable read', $view['isolation']);
            self::assertTrue($view['committed']);
            self::assertSame('true', $view['navigate']);
            self::assertStringContainsString($expected, $raw);
            self::assertStringNotContainsString('Working only', $raw);
            self::assertStringNotContainsString('Later publication', $raw);
            $sessionIds[] = $view['session'];
            $writes = array_values(array_filter($evidence['queries'], fn ($query) => str_starts_with($query['sql'], 'update "sessions"')));
            self::assertCount(1, $writes);
            self::assertSame(0, $writes[0]['level']);
            self::assertSame('read committed', $writes[0]['isolation']);
            self::assertFalse($writes[0]['committed']);
            $reads = array_values(array_filter($evidence['queries'], fn ($query) => str_starts_with($query['sql'], 'select') && str_contains($query['sql'], '"sessions"')));
            self::assertNotEmpty($reads);
            foreach ($reads as $read) {
                self::assertSame(0, $read['level']);
                self::assertSame('read committed', $read['isolation']);
                self::assertFalse($read['committed']);
            }
        }
        self::assertSame($sessionIds[0], $sessionIds[1]);
        self::assertSame($sessionIds[0], $this->observer->query('SELECT id FROM sessions')->fetchColumn());
        fwrite(STDOUT, "\nObserved two HTTP backends waiting on controller $controllerPid: ".implode(', ', $blocked)."; both 200; same database session; UPDATE outside snapshot.\n");
    }

    public function test_public_media_binding_and_authorization_remain_inside_the_committed_read(): void
    {
        $fixture = json_decode(file_get_contents($this->root.'/fixture.json'), true, flags: JSON_THROW_ON_ERROR);
        $response = $this->request(0, '/media/original/'.$fixture['asset'], ['X-Livewire-Navigate: true', 'X-Session-Probe: binding']);
        self::assertSame(200, $response['status'], $response['body']);
        self::assertStringContainsString('originals/session.png', str_replace('\\', '/', $response['headers']));
        self::assertStringNotContainsString('working-only.png', $response['headers']);
        $evidence = json_decode(file_get_contents($this->root.'/binding.json'), true, flags: JSON_THROW_ON_ERROR);
        $mediaReads = array_values(array_filter($evidence['queries'], fn ($query) => str_contains($query['sql'], '"media_assets"')));
        self::assertNotEmpty($mediaReads);
        foreach ($mediaReads as $query) {
            self::assertStringContainsString('"committed"."media_assets"', $query['sql']);
            self::assertSame(1, $query['level']);
            self::assertSame('repeatable read', $query['isolation']);
            self::assertTrue($query['committed']);
        }
        self::assertSame(404, $this->request(1, '/media/original/'.$fixture['private'])['status']);
        self::assertSame(404, $this->request(1, '/media/original/99999999')['status']);
        self::assertSame(200, $this->request(1, '/media/variant/'.$fixture['variant'])['status']);
    }

    public function test_effective_route_stack_keeps_session_outside_and_bindings_inside(): void
    {
        $stack = json_decode(file_get_contents($this->root.'/verified.json'), true, flags: JSON_THROW_ON_ERROR);
        self::assertNotContains(UseCommittedPublicState::class, $stack['global']);
        foreach ($stack['routes'] as $name => $middleware) {
            self::assertContains(StartSession::class, $middleware, $name);
            self::assertContains(UseCommittedPublicState::class, $middleware, $name);
            self::assertContains(SubstituteBindings::class, $middleware, $name);
            self::assertLessThan(array_search(UseCommittedPublicState::class, $middleware), array_search(StartSession::class, $middleware), $name);
            self::assertLessThan(array_search(SubstituteBindings::class, $middleware), array_search(UseCommittedPublicState::class, $middleware), $name);
        }
    }

    private function handle(int $server, string $path, array $headers): \CurlHandle
    {
        $handle = curl_init($this->urls[$server].$path);
        curl_setopt_array($handle, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_TIMEOUT => 30,
            CURLOPT_COOKIE => $this->cookie, CURLOPT_HTTPHEADER => ['Accept: text/html', ...$headers]]);

        return $handle;
    }

    private function request(int $server, string $path, array $headers = []): array
    {
        $handle = $this->handle($server, $path, $headers);
        // X-Sendfile has no body in PHP's built-in server. Header-only evidence
        // here proves binding/auth, deliberately not physical image delivery.
        curl_setopt($handle, CURLOPT_IGNORE_CONTENT_LENGTH, true);
        $raw = curl_exec($handle);
        self::assertNotFalse($raw, curl_error($handle));
        $result = ['status' => curl_getinfo($handle, CURLINFO_RESPONSE_CODE),
            'headers' => substr($raw, 0, curl_getinfo($handle, CURLINFO_HEADER_SIZE)),
            'body' => substr($raw, curl_getinfo($handle, CURLINFO_HEADER_SIZE))];
        curl_close($handle);

        return $result;
    }
}
