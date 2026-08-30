<?php

use App\Domain\Content\HomeTemplate;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Pages\StorageCapacity;
use App\Models\BlogPost;
use App\Models\HomePresentationSetting;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('media.disk', 'local');
    config()->set('media.quota_bytes', 1_000_000);
    Cache::flush();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function storageCoverageAsset(string $filename): MediaAsset
{
    $storageKey = 'originals/'.$filename;
    Storage::disk('local')->put($storageKey, str_repeat('x', 64));

    return MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => $filename,
        'mime_type' => 'image/jpeg',
        'byte_size' => 64,
        'sha256' => hash('sha256', 'storage-coverage-'.$filename),
        'state' => 'available',
        'alt_text' => 'Storage reference coverage',
    ]);
}

it('includes custom page home site identity and rich text paths in the Storage reference model', function (): void {
    $customAsset = storageCoverageAsset('custom-page.jpg');
    $homeAsset = storageCoverageAsset('home.jpg');
    $identityAsset = storageCoverageAsset('identity.jpg');
    $richTextAsset = storageCoverageAsset('rich-text.jpg');

    $customPage = SiteSection::query()->create([
        'type' => SiteNodeType::CustomPage->value,
        'template' => null,
        'title' => 'Workshop',
        'navigation_label' => 'Workshop',
        'slug' => 'storage-reference-workshop',
        'state' => 'hidden',
        'position' => 980,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    $customPage->customPageSetting()->create([
        'blocks' => [[
            'type' => 'image',
            'published' => true,
            'media_asset_id' => $customAsset->getKey(),
            'image_decorative' => false,
        ]],
    ]);

    $home = HomePresentationSetting::query()->firstOrFail();
    $configuration = $home->configuration();
    $configuration[HomeTemplate::Custom->value] = [
        'components' => [[
            'type' => 'image',
            'media_asset_id' => $homeAsset->getKey(),
            'image_decorative' => false,
        ]],
    ];
    $home->update(['configuration' => $configuration]);

    $general = PublicContentSetting::general();
    $general->update(['favicon_media_asset_id' => $identityAsset->getKey()]);

    $journal = SiteSection::query()->create([
        'type' => SiteNodeType::Journal->value,
        'template' => JournalTemplate::Blog->value,
        'title' => 'Notes',
        'navigation_label' => 'Notes',
        'slug' => 'storage-reference-notes',
        'state' => 'hidden',
        'position' => 990,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    BlogPost::query()->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'storage-rich-text-reference',
        'title' => 'Rich text reference',
        'body' => '![](media:'.$richTextAsset->getKey().')',
        'state' => 'draft',
        'position' => 0,
    ]);

    $rows = collect(Livewire::test(StorageCapacity::class)->get('fileRows'))->keyBy('filename');

    expect($rows['custom-page.jpg']['state'])->toBe('referenced')
        ->and($rows['custom-page.jpg']['use_labels'])->toContain('Custom pages')
        ->and(collect($rows['custom-page.jpg']['references'])->pluck('target_label')->all())->toContain('Workshop')
        ->and($rows['home.jpg']['state'])->toBe('referenced')
        ->and($rows['home.jpg']['use_labels'])->toContain('Home')
        ->and($rows['identity.jpg']['state'])->toBe('referenced')
        ->and($rows['identity.jpg']['use_labels'])->toContain('Site identity')
        ->and($rows['rich-text.jpg']['state'])->toBe('referenced')
        ->and($rows['rich-text.jpg']['use_labels'])->toContain('Journal');
});
