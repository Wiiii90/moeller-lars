<?php

use App\Domain\Publication\PublicationService;
use Illuminate\Support\Facades\DB;

it('reports concrete changed records and fields for staged publication review', function (): void {
    $id = (int) DB::table('public_content_settings')->where('scope', 'general')->value('id');

    DB::table('public_content_settings')->where('id', $id)->update([
        'legal_disclaimer' => 'Staged publication review value',
    ]);

    $details = app(PublicationService::class)->pendingDetails();
    $row = collect($details['rows'])->firstWhere('table', 'public_content_settings');

    expect($row)->not->toBeNull()
        ->and($row['row_key'])->toBe((string) $id)
        ->and($row['change'])->toBe('changed')
        ->and($row['fields'])->toContain('legal_disclaimer')
        ->and($details['truncated'])->toBeFalse();
});


it('reports a complete review at its exact row limit without claiming truncation', function (): void {
    DB::table('public_content_settings')->where('scope', 'general')->update(['public_email' => 'pending-exact@example.test']);
    $publication = app(PublicationService::class);
    expect($publication->pendingSummary()['total'])->toBe(1);
    $details = $publication->pendingDetails(limit: 1);
    expect($details['rows'])->toHaveCount(1)
        ->and($details['rows'][0]['fields'])->toContain('public_email')
        ->and($details['truncated'])->toBeFalse();
});

it('marks truncation only when another changed row exists including in a later table', function (): void {
    DB::table('public_content_settings')->where('scope', 'general')->update(['public_email' => 'pending-more@example.test']);
    $redirect = \App\Models\Redirect::query()->create([
        'source_path' => '/activity-review-before', 'target_path' => '/activity-review-after',
        'status_code' => 301, 'enabled' => true, 'reason' => 'activity-review',
    ]);
    $publication = app(PublicationService::class);
    $limited = $publication->pendingDetails(limit: 1);
    $complete = $publication->pendingDetails(limit: 2);
    expect($limited['rows'])->toHaveCount(1)->and($limited['truncated'])->toBeTrue()
        ->and($complete['rows'])->toHaveCount(2)->and($complete['truncated'])->toBeFalse()
        ->and($complete['rows'][1]['table'])->toBe('redirects')
        ->and($complete['rows'][1]['row_key'])->toBe((string) $redirect->id)
        ->and($complete['rows'][1]['change'])->toBe('added');
});

it('refreshes the mounted publication review from actual pending state without creating audit or feedback', function (): void {
    $actor = \App\Models\User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    $original = DB::table('public_content_settings')->where('scope', 'general')->value('public_email');
    $component = \Livewire\Livewire::test(\App\Filament\Pages\Activity::class)
        ->mountAction('publicationReview')->assertActionMounted('publicationReview')->assertMountedActionModalSee('No working-state changes');
    DB::table('public_content_settings')->where('scope', 'general')->update(['public_email' => 'review-refresh@example.test']);
    $component->unmountAction()->mountAction('publicationReview')->assertActionMounted('publicationReview')->assertMountedActionModalSee('public_email')
        ->assertNotDispatched('admin-header-notification');
    DB::table('public_content_settings')->where('scope', 'general')->update(['public_email' => $original]);
    $component->unmountAction()->mountAction('publicationReview')->assertActionMounted('publicationReview')->assertMountedActionModalSee('No working-state changes')
        ->assertNotDispatched('admin-header-notification');
    expect(app(PublicationService::class)->pendingDetails()['rows'])->toBe([])
        ->and(\App\Models\AuditEvent::query()->count())->toBe(0)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
});


it('reports removed committed records without inventing changed fields', function (): void {
    $actor = \App\Models\User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $redirect = \App\Models\Redirect::query()->create([
        'source_path' => '/activity-retired-path', 'target_path' => '/activity-current-path',
        'status_code' => 301, 'enabled' => true, 'reason' => 'activity-review',
    ]);
    $publication = app(PublicationService::class);
    $publication->commit($actor, 'Publish redirect review fixture');
    expect($publication->pendingDetails()['rows'])->toBe([]);
    $redirect->delete();
    $details = $publication->pendingDetails();
    expect($details['rows'])->toHaveCount(1)
        ->and($details['rows'][0]['table'])->toBe('redirects')
        ->and($details['rows'][0]['row_key'])->toBe((string) $redirect->id)
        ->and($details['rows'][0]['change'])->toBe('removed')
        ->and($details['rows'][0]['fields'])->toBe([])
        ->and($details['truncated'])->toBeFalse();
});

it('omits framework timestamp-only differences from pending records and totals', function (): void {
    DB::table('public_content_settings')->where('scope', 'general')->update(['updated_at' => now()->addMinute()]);
    $publication = app(PublicationService::class);
    expect($publication->pendingSummary()['total'])->toBe(0)
        ->and($publication->pendingDetails(limit: 1))->toBe(['rows' => [], 'truncated' => false]);
});
