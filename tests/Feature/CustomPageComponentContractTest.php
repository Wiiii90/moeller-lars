<?php

use App\Domain\Admin\EditorialRecordService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function componentContractAsset(string $suffix): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/component-contract-'.$suffix.'.jpg',
        'original_filename' => 'component-contract-'.$suffix.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'component-contract-'.$suffix),
        'state' => 'available',
        'alt_text' => 'Component contract '.$suffix,
        'width' => 2,
        'height' => 2,
    ]);
}

it('persists ordered Custom Page components across add remove and reorder changes', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Component page', 'component-contract-page');
    /** @var CustomPageSetting $settings */
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $asset = componentContractAsset('order');

    $settings->update(['blocks' => [
        ['type' => 'image', 'media_asset_id' => $asset->getKey(), 'image_decorative' => false],
        ['type' => 'cv_list'],
        ['type' => 'divider'],
        ['type' => 'contact', 'show_email' => true, 'show_form' => true, 'social_platforms' => [], 'form_state' => 'enabled'],
        ['type' => 'text', 'title' => 'Statement', 'body' => 'Persistent text'],
        ['type' => 'list', 'title' => 'Links', 'items' => [[
            'visible' => true,
            'title' => 'Example',
            'url' => 'https://example.com',
        ]]],
    ]]);

    expect(array_column($settings->fresh()->components(), 'type'))->toBe([
        'image', 'cv_list', 'divider', 'contact', 'text', 'list',
    ]);

    $components = $settings->fresh()->components();
    $settings->update(['blocks' => [
        $components[3],
        $components[1],
        $components[0],
        $components[2],
        $components[5],
    ]]);

    expect(array_column($settings->fresh()->components(), 'type'))->toBe([
        'contact', 'cv_list', 'image', 'divider', 'list',
    ]);
});

it('keeps CV List components as references to canonical CV records instead of copied entries', function (): void {
    $section = app(SiteSectionEditorialService::class)->createCustomPage('CV contract', 'cv-contract-page');
    /** @var CustomPageSetting $settings */
    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $entry = CvEntry::query()->create([
        'section' => 'Exhibitions',
        'title' => 'Original CV title',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2026',
    ]);

    $settings->update(['blocks' => [['type' => 'cv_list']]]);
    $stored = $settings->fresh()->components()[0];

    expect($stored)->toBe(['type' => 'cv_list'])
        ->and($stored)->not->toHaveKey('items');

    $entry->update(['title' => 'Updated canonical CV title']);

    expect($settings->fresh()->components()[0])->toBe(['type' => 'cv_list'])
        ->and(CvEntry::query()->findOrFail($entry->getKey())->title)->toBe('Updated canonical CV title');
});

it('removes CV records without deleting their canonical Media assets', function (): void {
    $asset = componentContractAsset('cv-delete');
    $entry = CvEntry::query()->create([
        'section' => 'Biography',
        'title' => 'Removable CV entry',
        'state' => 'draft',
        'position' => 0,
        'year_text' => '2026',
        'image_media_asset_id' => $asset->getKey(),
    ]);

    app(EditorialRecordService::class)->deleteCv($entry);

    expect(CvEntry::query()->whereKey($entry->getKey())->exists())->toBeFalse()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
});

it('deletes an empty Journal directly blocks Journals with entries and allows recreation', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $empty = $sections->createJournal('Disposable Blog', 'disposable-blog', JournalTemplate::Blog->value);
    $sections->updatePlacement($empty, 'published', true, null);

    $sections->deleteConfigurableSection($empty);

    expect(SiteSection::query()->whereKey($empty->getKey())->exists())->toBeFalse();

    $replacement = $sections->createJournal('Blog', 'blog-recreated', JournalTemplate::Blog->value);
    expect($replacement->getAttribute('template'))->toBe(JournalTemplate::Blog->value);

    BlogPost::query()->create([
        'site_section_id' => $replacement->getKey(),
        'title' => 'Protected entry',
        'slug' => 'protected-entry',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
    ]);

    expect(fn () => $sections->deleteConfigurableSection($replacement))
        ->toThrow(ValidationException::class, 'This Journal cannot be deleted while it still contains entries.');
});
