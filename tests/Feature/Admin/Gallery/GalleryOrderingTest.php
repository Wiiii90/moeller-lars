<?php

use App\Domain\Admin\AdminActivityNotificationContext;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Publication\PublicationService;
use App\Filament\Pages\GalleryWorkspace;
use App\Models\AdminNotification;
use App\Models\Artwork;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

require_once __DIR__.'/GalleryFixtures.php';

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    \Filament\Facades\Filament::setCurrentPanel('admin');
    \Filament\Facades\Filament::bootCurrentPanel();
    galleryFixtureUnavailableAnalytics();
});

function galleryOrderState(): array
{
    return Artwork::query()->orderBy('id')->get(['id', 'artwork_category_id', 'position'])->toArray();
}

it('orders newly created Manual Gallery Artworks first and leaves older records in relative order', function (): void {
    $gallery = galleryFixtureCategory();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    $second = galleryFixtureArtwork($gallery, ['position' => 1]);

    $created = app(\App\Domain\Artwork\ArtworkDraftService::class)->create([
        'artwork_category_id' => $gallery->getKey(),
        'title' => 'Newest Gallery addition',
        'slug' => 'newest-gallery-addition',
        'work_date' => null,
    ]);

    expect(Artwork::query()->where('artwork_category_id', $gallery->getKey())
        ->orderBy('position')->pluck('id')->all())
        ->toBe([$created->getKey(), $first->getKey(), $second->getKey()]);
});

it('applies date sorting consistently without overwriting manual positions', function (): void {
    $gallery = galleryFixtureCategory();
    $older = galleryFixtureArtwork($gallery, ['position' => 0, 'work_year' => 2023, 'work_date' => '2023-01-01']);
    $newer = galleryFixtureArtwork($gallery, ['position' => 1, 'work_year' => 2026, 'work_date' => '2026-10-02']);
    $gallery->forceFill(['sort_mode' => 'newest'])->save();

    $ids = \App\Domain\Artwork\GalleryPresentation::orderArtworks(
        Artwork::query()->where('artwork_category_id', $gallery->getKey()),
        $gallery->fresh(),
    )->pluck('id')->all();

    expect($ids)->toBe([$newer->getKey(), $older->getKey()])
        ->and((int) $older->fresh()->getAttribute('position'))->toBe(0);
});

it('persists native sort once while leaving Committed order unchanged and repeated or boundary actions silent', function (): void {
    $gallery = galleryFixtureCategory();
    $artworks = collect(range(0, 2))->map(fn ($position) => galleryFixtureArtwork($gallery, ['position' => $position]));
    app(PublicationService::class)->commit(auth()->user(), 'Gallery ordering baseline');
    $committed = DB::table('committed.artworks')->orderBy('id')->get()->toJson();
    app(AdminActivityNotificationContext::class)->discard();
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->id])
        ->call('sortArtwork', (string) $artworks[2]->id, '0')->assertHasNoErrors();
    expect($gallery->artworks()->orderBy('position')->pluck('id')->all())
        ->toBe([$artworks[2]->id, $artworks[0]->id, $artworks[1]->id])
        ->and($gallery->artworks()->orderBy('position')->pluck('position')->all())->toBe([0, 1, 2])
        ->and(AuditEvent::query()->count())->toBe($audits + 1)
        ->and(AuditEvent::query()->latest('id')->first()->only(['action', 'entity_type', 'entity_id']))->toBe([
            'action' => 'artwork_category.gallery_reordered',
            'entity_type' => 'artwork_category',
            'entity_id' => $gallery->id,
        ])
        ->and(AdminNotification::query()->count())->toBe($notifications + 1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1)
        ->and(DB::table('committed.artworks')->orderBy('id')->get()->toJson())->toBe($committed);
    $saved = galleryOrderState();
    foreach ([['sortArtwork', $artworks[2]->id, 0], ['moveArtwork', $artworks[2]->id, 'up'], ['moveArtwork', $artworks[1]->id, 'down']] as $call) {
        $editor->call(...$call)->assertNotDispatched('admin-header-notification');
        expect(galleryOrderState())->toBe($saved)
            ->and(AuditEvent::query()->count())->toBe($audits + 1)
            ->and(AdminNotification::query()->count())->toBe($notifications + 1);
    }
});

it('ignores malformed, foreign and out-of-range native sort requests', function (): void {
    $gallery = galleryFixtureCategory();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    galleryFixtureArtwork($gallery, ['position' => 1]);
    $foreign = galleryFixtureArtwork(galleryFixtureCategory());
    $before = galleryOrderState();
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->id]);
    foreach ([[$first->id, -1], [$first->id, 2], [$first->id, '1.5'], [$first->id, 'invalid'], ['invalid', 1], [$foreign->id, 1], [PHP_INT_MAX, 1]] as $params) {
        $editor->call('sortArtwork', ...$params)->assertNotDispatched('admin-header-notification');
        expect(galleryOrderState())->toBe($before)
            ->and(AuditEvent::query()->count())->toBe($audits)
            ->and(AdminNotification::query()->count())->toBe($notifications);
    }
});

it('rejects direct native sort while any Gallery filter is active', function (string $property, string $value): void {
    $gallery = galleryFixtureCategory();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    galleryFixtureArtwork($gallery, ['position' => 1]);
    $before = galleryOrderState();
    $audits = AuditEvent::query()->count();
    $notifications = AdminNotification::query()->count();
    Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->id])
        ->set($property, $value)->call('sortArtwork', $first->id, 1)
        ->assertNotDispatched('admin-header-notification');
    expect(galleryOrderState())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe($notifications);
})->with([
    'search' => ['search', 'Artwork'],
    'status' => ['statusFilter', 'draft'],
    'readiness' => ['readinessFilter', 'needs-attention'],
]);

it('rolls back Gallery sorting if its audit cannot be recorded', function (): void {
    $gallery = galleryFixtureCategory();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    galleryFixtureArtwork($gallery, ['position' => 1]);
    $before = galleryOrderState();
    $audits = AuditEvent::query()->count();
    $audit = Mockery::mock(app(AdminAuditService::class));
    $audit->shouldReceive('recordOrdering')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(AdminAuditService::class, $audit);
    app()->forgetInstance(GalleryEditorialService::class);
    expect(fn () => Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->id])->call('sortArtwork', $first->id, 1))
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect(galleryOrderState())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe(0);
});

it('protects an already mounted Gallery sort after admin access is revoked', function (): void {
    $gallery = galleryFixtureCategory();
    $first = galleryFixtureArtwork($gallery, ['position' => 0]);
    galleryFixtureArtwork($gallery, ['position' => 1]);
    $editor = Livewire::test(GalleryWorkspace::class, ['gallery' => $gallery->id]);
    auth()->user()->forceFill(['is_admin' => false])->save();
    $before = galleryOrderState();
    $audits = AuditEvent::query()->count();
    $editor->call('sortArtwork', $first->id, 1)->assertForbidden();
    expect(galleryOrderState())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(AdminNotification::query()->count())->toBe(0);
});
