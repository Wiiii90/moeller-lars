<?php

use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\AuditEvent;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('reorders Exhibition Gallery media by native position without losing playback preferences', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Gallery media ordering', 'gallery-media-ordering', JournalTemplate::Exhibitions->value,
    );
    $assets = collect(range(1, 3))->map(function (int $index): MediaAsset {
        $asset = MediaAsset::query()->create([
            'storage_key' => 'originals/order-media-'.$index.'.jpg',
            'original_filename' => 'order-media-'.$index.'.jpg',
            'mime_type' => 'image/jpeg',
            'byte_size' => 4,
            'sha256' => hash('sha256', 'order-media-'.$index),
            'state' => 'available',
            'alt_text' => 'Gallery item '.$index,
            'width' => 2,
            'height' => 2,
        ]);

        MediaVariant::query()->create([
            'media_asset_id' => $asset->getKey(),
            'variant_kind' => 'thumbnail',
            'transform_profile' => \App\Domain\Media\MediaIngestService::LARGE_IMAGE_TRANSFORM_PROFILE,
            'storage_key' => 'variants/order-media-'.$index.'.webp',
            'mime_type' => 'image/webp',
            'byte_size' => 4,
            'sha256' => hash('sha256', 'order-variant-'.$index),
            'state' => 'available',
            'width' => 2,
            'height' => 2,
        ]);

        return $asset;
    });
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Ordered Gallery',
        'slug' => 'ordered-gallery',
        'gallery_enabled' => true,
        'gallery_images' => [
            ['media_asset_id' => $assets[0]->getKey(), 'video_controls_enabled' => false],
            ['media_asset_id' => $assets[1]->getKey(), 'video_controls_enabled' => true],
            ['media_asset_id' => $assets[2]->getKey(), 'video_controls_enabled' => false],
        ],
    ]);

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()]);

    $rows = $editor->get('mountedActions.0.data.gallery_images');
    $keys = array_keys($rows);
    $before = AuditEvent::query()->count();
    $editor->call('sortExhibitionGalleryMedia', (string) $keys[0], 2)->assertHasNoFormErrors();

    $saved = JournalEntryMedia::query()
        ->where('exhibition_id', $entry->getKey())
        ->where('role', JournalEntryMedia::ROLE_GALLERY)
        ->orderBy('position')
        ->get();
    expect($saved->pluck('media_asset_id')->all())->toBe([
        $assets[1]->getKey(), $assets[2]->getKey(), $assets[0]->getKey(),
    ])->and($saved->pluck('position')->all())->toBe([1, 2, 3])
        ->and($saved->pluck('video_controls_enabled')->all())->toBe([true, false, false])
        ->and(AuditEvent::query()->count())->toBe($before + 1);

    $editor->call('sortExhibitionGalleryMedia', (string) $keys[0], 2)->assertHasNoFormErrors();
    expect(AuditEvent::query()->count())->toBe($before + 1);

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->assertActionDataSet(['gallery_images' => [
            ['media_asset_id' => $assets[1]->getKey(), 'video_controls_enabled' => true],
            ['media_asset_id' => $assets[2]->getKey(), 'video_controls_enabled' => false],
            ['media_asset_id' => $assets[0]->getKey(), 'video_controls_enabled' => false],
        ]]);
});

it('rejects an invalid Gallery media handle without changing the mounted form', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Handle protection', 'gallery-handle-protection', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Handle guard',
        'slug' => 'handle-guard',
    ]);
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()]);

    $editor->call('sortExhibitionGalleryMedia', 'unknown', 0)->assertHasNoFormErrors();
    expect(JournalEntryMedia::query()->where('exhibition_id', $entry->getKey())->count())->toBe(0);
});
