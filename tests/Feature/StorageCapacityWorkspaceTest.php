<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\MediaCapacityService;
use App\Filament\Pages\StorageCapacity;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Storage::fake('local');
    config()->set('media.disk', 'local');
    config()->set('media.quota_bytes', 1_000_000);
    Cache::flush();
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function storageWorkspaceAsset(string $filename, int $bytes = 100, string $mime = 'image/jpeg'): MediaAsset
{
    $storageKey = 'originals/'.$filename;
    Storage::disk('local')->put($storageKey, str_repeat('x', $bytes));

    return MediaAsset::query()->create([
        'storage_key' => $storageKey,
        'original_filename' => $filename,
        'mime_type' => $mime,
        'byte_size' => $bytes,
        'sha256' => hash('sha256', $filename),
        'state' => 'available',
        'alt_text' => 'Storage test '.$filename,
    ]);
}

function storageWorkspaceGallery(string $name): array
{
    static $sequence = 0;
    $sequence++;

    $category = ArtworkCategory::query()->create([
        'slug' => str($name)->slug()->append('-storage-'.$sequence)->toString(),
        'name' => $name,
    ]);
    $section = SiteSection::query()->create([
        'type' => SiteNodeType::Gallery->value,
        'template' => null,
        'title' => $name,
        'navigation_label' => $name,
        'slug' => str($name)->slug()->append('-storage-page-'.$sequence)->toString(),
        'state' => 'hidden',
        'position' => 700 + $sequence,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => $category->getKey(),
    ]);

    return [$category, $section];
}

function storageWorkspaceAttachArtwork(MediaAsset $asset, ArtworkCategory $category, string $title): Artwork
{
    $artwork = Artwork::query()->create([
        'artwork_category_id' => $category->getKey(),
        'slug' => str($title)->slug()->append('-'.uniqid())->toString(),
        'title' => $title,
        'state' => 'draft',
        'position' => 0,
    ]);
    ArtworkMedia::query()->create([
        'artwork_id' => $artwork->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);

    return $artwork;
}

function storageWorkspaceJournal(string $title, string $template): SiteSection
{
    static $sequence = 0;
    $sequence++;

    return SiteSection::query()->create([
        'type' => SiteNodeType::Journal->value,
        'template' => $template,
        'title' => $title,
        'navigation_label' => $title,
        'slug' => str($title)->slug()->append('-storage-journal-'.$sequence)->toString(),
        'state' => 'hidden',
        'position' => 800 + $sequence,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
}

function storageWorkspaceCvPage(MediaAsset $portrait, string $title = 'Biography'): SiteSection
{
    static $sequence = 0;
    $sequence++;

    $section = SiteSection::query()->create([
        'type' => SiteNodeType::CustomPage->value,
        'template' => null,
        'title' => $title,
        'navigation_label' => $title,
        'slug' => str($title)->slug()->append('-storage-cv-'.$sequence)->toString(),
        'state' => 'hidden',
        'position' => 850 + $sequence,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    $section->customPageSetting()->create([
        'blocks' => [[
            'type' => 'cv_list',
            'published' => true,
            'media_asset_id' => $portrait->getKey(),
        ]],
    ]);

    return $section;
}

it('keeps allowance accounting authoritative-original-only while generated derivatives stay rebuildable', function (): void {
    config()->set('media.quota_bytes', 100);
    $asset = storageWorkspaceAsset('allowance.jpg', 80);
    Storage::disk('local')->put('variants/allowance.webp', str_repeat('v', 300));
    MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/allowance.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 300,
        'sha256' => hash('sha256', 'allowance-variant'),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    $snapshot = app(MediaCapacityService::class)->snapshot();

    expect($snapshot['authoritative_bytes'])->toBe(80)
        ->and($snapshot['generated_bytes'])->toBe(300)
        ->and($snapshot['remaining_bytes'])->toBe(20)
        ->and($snapshot['status'])->toBe('healthy');
});

it('distinguishes unconfigured allowance from unavailable configuration', function (): void {
    storageWorkspaceAsset('measured.jpg', 25);

    config()->set('media.quota_bytes', null);
    Cache::flush();
    $unconfigured = Livewire::test(StorageCapacity::class)->get('capacity');
    expect($unconfigured['status'])->toBe('unconfigured')
        ->and($unconfigured['measurement_available'])->toBeTrue()
        ->and($unconfigured['allowance'])->toBe('—')
        ->and($unconfigured['remaining'])->toBe('—');

    config()->set('media.quota_bytes', 'invalid');
    Cache::flush();
    $unavailable = Livewire::test(StorageCapacity::class)->get('capacity');
    expect($unavailable['status'])->toBe('unavailable')
        ->and($unavailable['measurement_available'])->toBeFalse()
        ->and($unavailable['status_label'])->toBe('Allowance unavailable');
});

it('recognizes concrete gallery journal and rendered cv_list references from the canonical reference world', function (): void {
    $galleryAsset = storageWorkspaceAsset('gallery-file.jpg', 120);
    [$gallery] = storageWorkspaceGallery('Selected Works');
    storageWorkspaceAttachArtwork($galleryAsset, $gallery, 'Blue field');

    $journalAsset = storageWorkspaceAsset('journal-file.jpg', 90);
    $journal = storageWorkspaceJournal('Studio Journal', JournalTemplate::Blog->value);
    $post = BlogPost::query()->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'storage-post',
        'title' => 'Inside the studio',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
    ]);
    JournalEntryMedia::query()->create([
        'blog_post_id' => $post->getKey(),
        'exhibition_id' => null,
        'media_asset_id' => $journalAsset->getKey(),
        'role' => JournalEntryMedia::ROLE_COVER,
        'position' => 0,
    ]);

    $cvAsset = storageWorkspaceAsset('cv-file.jpg', 70);
    storageWorkspaceCvPage($cvAsset, 'Biography');

    $rows = collect(Livewire::test(StorageCapacity::class)->get('files'))->keyBy('filename');

    expect($rows['gallery-file.jpg']['use_labels'])->toContain('Galleries')
        ->and(collect($rows['gallery-file.jpg']['references'])->pluck('target_label')->all())->toContain('Selected Works')
        ->and($rows['journal-file.jpg']['use_labels'])->toContain('Journal')
        ->and(collect($rows['journal-file.jpg']['references'])->pluck('target_label')->all())->toContain('Blog post · Inside the studio')
        ->and($rows['cv-file.jpg']['use_labels'])->toContain('CV')
        ->and(collect($rows['cv-file.jpg']['references'])->pluck('target_label')->all())->toContain('Biography');
});

it('keeps multi-use original bytes exclusive in the overview while exposing all uses', function (): void {
    $asset = storageWorkspaceAsset('shared.jpg', 250);
    [$gallery] = storageWorkspaceGallery('Shared Gallery');
    storageWorkspaceAttachArtwork($asset, $gallery, 'Shared work');

    $journal = storageWorkspaceJournal('Shared Journal', JournalTemplate::Blog->value);
    $post = BlogPost::query()->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'shared-post',
        'title' => 'Shared post',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
    ]);
    JournalEntryMedia::query()->create([
        'blog_post_id' => $post->getKey(),
        'exhibition_id' => null,
        'media_asset_id' => $asset->getKey(),
        'role' => JournalEntryMedia::ROLE_COVER,
        'position' => 0,
    ]);

    $component = Livewire::test(StorageCapacity::class);
    $row = collect($component->get('files'))->firstWhere('filename', 'shared.jpg');
    $breakdown = collect($component->get('breakdown'));

    expect($row['use_labels'])->toContain('Galleries', 'Journal')
        ->and($row['bucket_key'])->toBe('shared')
        ->and($breakdown->sum('bytes'))->toBe(250)
        ->and($breakdown->firstWhere('key', 'shared')['bytes'])->toBe(250);
});

it('marks unreferenced catalogued assets and measured uncatalogued originals separately', function (): void {
    storageWorkspaceAsset('unused.jpg', 80);
    Storage::disk('local')->put('originals/not-in-media-files.bin', str_repeat('z', 55));

    $rows = collect(Livewire::test(StorageCapacity::class)->get('files'));
    $unused = $rows->firstWhere('filename', 'unused.jpg');
    $uncatalogued = $rows->firstWhere('state', 'uncatalogued');

    expect($unused['state'])->toBe('unreferenced')
        ->and($unused['bucket_key'])->toBe('unassigned')
        ->and($uncatalogued)->not->toBeNull()
        ->and($uncatalogued['asset_id'])->toBeNull()
        ->and($uncatalogued['filename'])->toBe('Uncatalogued original');
});

it('filters file rows by search area reference and reference state and keeps visual selection synchronized', function (): void {
    $first = storageWorkspaceAsset('selected-large.jpg', 200);
    [$selectedGallery] = storageWorkspaceGallery('Selected Works');
    storageWorkspaceAttachArtwork($first, $selectedGallery, 'Selected painting');

    $second = storageWorkspaceAsset('other.jpg', 50);
    [$otherGallery] = storageWorkspaceGallery('Other Works');
    storageWorkspaceAttachArtwork($second, $otherGallery, 'Other painting');
    storageWorkspaceAsset('unused-filter.jpg', 40);

    $component = Livewire::test(StorageCapacity::class)
        ->set('search', 'selected-large')
        ->assertSet('total', 1)
        ->set('search', '')
        ->call('selectArea', 'galleries')
        ->assertSet('areaFilter', 'galleries')
        ->assertSet('total', 2);

    $selectedReference = collect($component->get('referenceOptions'))->firstWhere('label', 'Selected Works');
    expect($selectedReference)->not->toBeNull();

    $component->call('selectReference', $selectedReference['key'])
        ->assertSet('referenceFilter', $selectedReference['key'])
        ->assertSet('areaFilter', 'galleries')
        ->assertSet('referenceState', 'referenced')
        ->assertSet('total', 1)
        ->call('selectReferenceState', 'unreferenced')
        ->assertSet('referenceState', 'unreferenced')
        ->assertSet('areaFilter', 'all')
        ->assertSet('referenceFilter', 'all')
        ->assertSet('total', 1)
        ->call('resetTableFilters')
        ->assertSet('areaFilter', 'all')
        ->assertSet('referenceState', 'all')
        ->assertSet('referenceFilter', 'all');
});

it('keeps the full file analysis server-side while paging and filtering a larger library', function (): void {
    foreach (range(1, 130) as $index) {
        storageWorkspaceAsset(sprintf('page-%03d.jpg', $index), 30 + $index);
    }

    $component = Livewire::test(StorageCapacity::class)
        ->assertSet('total', 130)
        ->assertSet('pages', 6)
        ->assertCount('files', 25);

    $token = $component->get('analysisToken');
    $publicState = get_object_vars($component->instance());

    expect($token)->toBeString()->not->toBe('')
        ->and(Cache::has('media-storage:analysis:'.$token))->toBeTrue()
        ->and(property_exists(StorageCapacity::class, 'fileRows'))->toBeFalse()
        ->and($publicState)->not->toHaveKey('fileRows')
        ->and(count($component->get('files')))->toBeLessThanOrEqual(25);

    $component->call('nextPage')
        ->assertSet('page', 2)
        ->assertCount('files', 25)
        ->assertSet('analysisToken', $token)
        ->set('search', 'page-130')
        ->assertSet('page', 1)
        ->assertSet('total', 1)
        ->assertCount('files', 1)
        ->assertSet('analysisToken', $token);
});

it('renders one productive Storage table and no legacy Largest originals section', function (): void {
    storageWorkspaceAsset('single-table.jpg', 20);

    $html = Livewire::test(StorageCapacity::class)
        ->assertSee('Original size')
        ->assertSee('Reference state')
        ->assertDontSee('Largest originals')
        ->assertDontSee('Originals by library use')
        ->html();

    expect(substr_count($html, '<table'))->toBe(1)
        ->and($html)->toContain('admin-pager')
        ->and($html)->not->toContain('Add');
});

it('uses the shared outer Visual Stage contract for Storage without a local height family', function (): void {
    $adminCss = (string) file_get_contents(resource_path('css/admin.css'));
    $dataCss = (string) file_get_contents(resource_path('css/admin/data-workspace.css'));
    $storageCss = (string) file_get_contents(resource_path('css/admin/storage.css'));
    $view = (string) file_get_contents(resource_path('views/filament/pages/storage-capacity.blade.php'));

    expect($adminCss)->toContain('--admin-visual-stage-height:')
        ->and($dataCss)->toMatch('/\.admin-visual-stage\s*\{[^}]*height:\s*var\(--admin-visual-stage-height\);/s')
        ->and($view)->toContain('admin-storage__visual-stage admin-visual-stage admin-visual-stage--stackable')
        ->and($view)->toContain('admin-storage__visual-main admin-visual-stage__pane')
        ->and($view)->toContain('admin-storage__context admin-visual-stage__pane')
        ->and($storageCss)->toContain('.admin-storage__visual-stage')
        ->and($storageCss)->not->toContain('height: var(--admin-visual-stage-height)', 'min-height: var(--admin-visual-stage-height)')
        ->and($storageCss)->not->toMatch('/admin-storage__visual-stage\s*\{[^}]*height:\s*\d+(?:\.\d+)?(?:px|rem)/s');
});

it('does not resolve concrete references with one query per asset', function (): void {
    [$gallery] = storageWorkspaceGallery('Query Gallery');
    foreach (range(1, 12) as $index) {
        $asset = storageWorkspaceAsset('query-'.$index.'.jpg', 10 + $index);
        storageWorkspaceAttachArtwork($asset, $gallery, 'Query work '.$index);
    }

    DB::flushQueryLog();
    DB::enableQueryLog();
    Livewire::test(StorageCapacity::class);
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    $artworkRelationQueries = collect($queries)->filter(static function (array $query): bool {
        $sql = strtolower((string) ($query['query'] ?? ''));

        return str_contains($sql, 'artwork_media') && str_contains($sql, 'select');
    });

    expect($artworkRelationQueries->count())->toBeLessThanOrEqual(3);
});