<?php

use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;


require_once __DIR__.'/PublicationVersionFixtures.php';

it('creates immutable SHA-256 commits with retained restorable snapshots', function (): void {
    $actor = User::factory()->admin()->create();
    $publication = app(PublicationService::class);

    publicationVersionSetDisclaimer('Version A');
    $checkpoint = $publication->commit($actor, 'Version A');

    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and((string) $checkpoint->getAttribute('hash'))->toMatch('/^[a-f0-9]{64}$/')
        ->and((string) $checkpoint->getAttribute('snapshot_hash'))->toMatch('/^[a-f0-9]{64}$/')
        ->and((string) $checkpoint->getAttribute('schema_hash'))->toMatch('/^[a-f0-9]{64}$/')
        ->and((bool) $checkpoint->getAttribute('snapshot_available'))->toBeTrue()
        ->and((string) $checkpoint->getAttribute('operation'))->toBe('commit')
        ->and(DB::table('publication_version_rows')->where('publication_checkpoint_id', $checkpoint->getKey())->count())->toBeGreaterThan(0)
        ->and(publicationVersionDisclaimer('committed'))->toBe('Version A')
        ->and(app(PublicationVersionService::class)->isRestorable($checkpoint))->toBeTrue();
});


it('keeps publication commit history permanent at the database boundary', function (): void {
    $checkpoint = PublicationCheckpoint::query()->latest('id')->firstOrFail();
    $pdo = DB::connection()->getPdo();
    $pdo->exec('SAVEPOINT publication_history_guard');

    try {
        expect(fn () => DB::table('publication_checkpoints')->where('id', $checkpoint->getKey())->delete())
            ->toThrow(QueryException::class);
    } finally {
        $pdo->exec('ROLLBACK TO SAVEPOINT publication_history_guard');
    }

    expect(PublicationCheckpoint::query()->whereKey($checkpoint->getKey())->exists())->toBeTrue();
});
