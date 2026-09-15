<?php

use App\Filament\Support\AdminActivityFeed;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('projects publication control Activity against the concrete commit history target', function (): void {
    $actor = User::factory()->admin()->create();
    $checkpoint = PublicationCheckpoint::query()->latest('id')->firstOrFail();

    $event = AuditEvent::query()->create([
        'admin_user_id' => $actor->getKey(),
        'action' => 'publication.stage_reset',
        'entity_type' => 'publication_checkpoint',
        'entity_id' => $checkpoint->getKey(),
        'occurred_at' => now(),
        'request_id' => null,
        'metadata' => null,
    ]);

    PublicationCheckpointEvent::query()->create([
        'publication_checkpoint_id' => $checkpoint->getKey(),
        'audit_event_id' => $event->getKey(),
        'created_at' => now(),
    ]);

    $projected = app(AdminActivityFeed::class)->event((int) $event->getKey(), $actor);

    expect($projected)->not->toBeNull()
        ->and($projected['area'])->toBe('Publication')
        ->and($projected['target'])->toBe('Commit '.$checkpoint->shortHash())
        ->and($projected['checkpoint_short_hash'])->toBe($checkpoint->shortHash())
        ->and($projected['publication_status'])->toBe('committed')
        ->and($projected['url'])->toContain('?view=commits');
});
