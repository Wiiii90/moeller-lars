<?php

use App\Domain\Admin\EditorialRecordService;
use App\Models\AuditEvent;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function cvRecord(string $title, int $position, string $state = 'draft'): CvEntry
{
    return CvEntry::create([
        'section' => 'Biography',
        'title' => $title,
        'year_text' => '2026',
        'state' => $state,
        'position' => $position,
        'date_precision' => 'year',
    ]);
}

function exhibitionRecord(string $title, int $position, string $state = 'draft'): Exhibition
{
    return Exhibition::create([
        'slug' => strtolower(str_replace(' ', '-', $title)),
        'title' => $title,
        'date_text' => '2026',
        'state' => $state,
        'position' => $position,
    ]);
}

it('publishes, unpublishes, archives and restores CV entries with audit events', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin, 'web');
    $entry = cvRecord('Editorial lifecycle', 0);
    $service = app(EditorialRecordService::class);

    expect($service->publish($entry)->state)->toBe('published')
        ->and($entry->fresh()->published_at)->not->toBeNull()
        ->and($service->unpublish($entry)->state)->toBe('draft')
        ->and($service->archive($entry)->state)->toBe('archived')
        ->and($service->restoreDraft($entry)->state)->toBe('draft');

    expect(AuditEvent::query()->where('entity_type', 'cv_entry')->pluck('action')->all())
        ->toContain('cv_entry.published', 'cv_entry.unpublished', 'cv_entry.archived', 'cv_entry.restored_to_draft');
});

it('reorders published exhibitions without tripping the partial unique position index', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $first = exhibitionRecord('First exhibition', 0, 'published');
    $second = exhibitionRecord('Second exhibition', 1, 'published');
    $third = exhibitionRecord('Third exhibition', 2, 'published');
    $service = app(EditorialRecordService::class);

    expect($service->move($third, 'up'))->toBeTrue()
        ->and(Exhibition::query()->orderBy('position')->pluck('id')->all())
        ->toBe([$first->id, $third->id, $second->id])
        ->and(Exhibition::query()->orderBy('position')->pluck('position')->all())
        ->toBe([0, 1, 2])
        ->and(AuditEvent::query()->where('action', 'exhibition.reordered')->count())
        ->toBe(2);
});

it('does not mutate ordering beyond list boundaries', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $first = cvRecord('First boundary entry', 0);
    cvRecord('Second boundary entry', 1);

    expect(app(EditorialRecordService::class)->move($first, 'up'))->toBeFalse()
        ->and($first->fresh()->position)->toBe(0)
        ->and(AuditEvent::query()->where('action', 'cv_entry.reordered')->count())->toBe(0);
});

it('requires an admin actor before editorial state or order mutations', function () {
    $entry = cvRecord('Protected entry', 0);
    $service = app(EditorialRecordService::class);

    expect(fn () => $service->publish($entry))->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => $service->archive($entry))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->move($entry, 'up'))->toThrow(AuthorizationException::class)
        ->and($entry->fresh()->state)->toBe('draft');
});
