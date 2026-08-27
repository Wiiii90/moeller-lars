<?php

use App\Domain\Admin\EditorialRecordService;
use App\Domain\Artwork\ArtworkDraftService;
use App\Domain\Artwork\ArtworkGalleryAssignmentService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\ExhibitionDraftService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\PublicNavigationService;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\ExhibitionMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

function acceptanceAsset(string $suffix): MediaAsset
{
    $asset = MediaAsset::query()->create([
        'storage_key' => 'originals/acceptance-'.$suffix.'.jpg',
        'original_filename' => 'acceptance-'.$suffix.'.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'acceptance-'.$suffix),
        'state' => 'available',
        'alt_text' => 'Acceptance image '.$suffix,
        'width' => 2,
        'height' => 2,
    ]);

    MediaVariant::query()->create([
        'media_asset_id' => $asset->getKey(),
        'variant_kind' => 'thumbnail',
        'storage_key' => 'variants/acceptance-'.$suffix.'.webp',
        'mime_type' => 'image/webp',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'acceptance-variant-'.$suffix),
        'transform_profile' => 'public-v1',
        'state' => 'available',
    ]);

    return $asset;
}

it('keeps Home singular published navigable and undeletable', function (): void {
    $home = SiteSection::query()->where('type', SiteNodeType::Home->value)->sole();

    expect($home->getAttribute('slug'))->toBeNull()
        ->and($home->getAttribute('state'))->toBe('published')
        ->and($home->getAttribute('show_in_navigation'))->toBeTrue()
        ->and($home->getAttribute('navigation_label'))->toBe('Home')
        ->and(app(PublicNavigationService::class)->items()->pluck('label')->all())->toContain('Home');

    expect(fn () => $home->delete())
        ->toThrow(ValidationException::class, 'Home cannot be deleted.');
});

it('persists Gallery identity settings slug redirects and homepage eligibility', function (): void {
    $service = app(GalleryEditorialService::class);
    $gallery = $service->create([
        'name' => 'Acceptance Gallery',
        'slug' => 'acceptance-gallery',
        'description' => null,
        'show_on_home' => false,
        'parent_section_id' => null,
    ]);

    $service->update($gallery, [
        'name' => 'Renamed Gallery',
        'description' => 'Persistent description',
        'show_on_home' => true,
    ]);
    $service->changeSlug($gallery, 'renamed-gallery');

    $gallery->refresh();
    $section = $gallery->siteSection()->firstOrFail();

    expect($gallery->getAttribute('name'))->toBe('Renamed Gallery')
        ->and($gallery->getAttribute('slug'))->toBe('renamed-gallery')
        ->and($gallery->getAttribute('description'))->toBe('Persistent description')
        ->and($gallery->getAttribute('show_on_home'))->toBeTrue()
        ->and($section->getAttribute('title'))->toBe('Renamed Gallery')
        ->and($section->getAttribute('navigation_label'))->toBe('Renamed Gallery')
        ->and($section->getAttribute('slug'))->toBe('renamed-gallery')
        ->and($this->get('/acceptance-gallery')->status())->toBeIn([301, 302]);
});

it('restricts destructive SiteSection deletion while descendants or Journal entries exist', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $parent = $sections->createNavigationGroup('Acceptance Parent');
    $child = $sections->createCustomPage('Acceptance Child', 'acceptance-child');
    $sections->updatePlacement($parent, 'hidden', false, null);
    $sections->updatePlacement($child, 'hidden', false, (int) $parent->getKey());

    expect(fn () => $sections->deleteConfigurableSection($parent))
        ->toThrow(ValidationException::class, 'Move or delete child pages before deleting their parent.');

    $journal = $sections->createJournal('Acceptance Blog', 'acceptance-blog', JournalTemplate::Blog->value);
    app(BlogEditorialService::class)->createDraft([
        'site_section_id' => $journal->getKey(),
        'title' => 'Draft post',
        'slug' => 'acceptance-draft-post',
        'body' => 'Draft body',
    ]);

    expect(fn () => $sections->deleteConfigurableSection($journal))
        ->toThrow(ValidationException::class, 'This Journal cannot be deleted while it still contains entries.');
});

it('persists top-level and nested SiteSection reorder independently', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $order = app(SiteSectionOrderService::class);
    $first = $sections->createCustomPage('First page', 'acceptance-first-page');
    $second = $sections->createCustomPage('Second page', 'acceptance-second-page');

    expect($order->move($second, 'up'))->toBeTrue();
    expect($second->fresh()->getAttribute('position'))->toBeLessThan($first->fresh()->getAttribute('position'));

    $parent = $sections->createNavigationGroup('Nested parent');
    $sections->updatePlacement($parent, 'hidden', false, null);
    $childOne = $sections->createCustomPage('Child one', 'acceptance-child-one');
    $childTwo = $sections->createCustomPage('Child two', 'acceptance-child-two');
    $sections->updatePlacement($childOne, 'hidden', false, (int) $parent->getKey());
    $sections->updatePlacement($childTwo, 'hidden', false, (int) $parent->getKey());

    expect($order->move($childTwo->fresh(), 'up'))->toBeTrue();
    expect($childTwo->fresh()->getAttribute('position'))->toBeLessThan($childOne->fresh()->getAttribute('position'));
});

it('assigns Artwork draft positions moves without losing media and deletes only the usage', function (): void {
    $galleries = app(GalleryEditorialService::class);
    $source = $galleries->create([
        'name' => 'Artwork source',
        'slug' => 'artwork-source',
        'description' => null,
        'show_on_home' => false,
        'parent_section_id' => null,
    ]);
    $destination = $galleries->create([
        'name' => 'Artwork destination',
        'slug' => 'artwork-destination',
        'description' => null,
        'show_on_home' => false,
        'parent_section_id' => null,
    ]);
    $drafts = app(ArtworkDraftService::class);
    $first = $drafts->create([
        'artwork_category_id' => $source->getKey(),
        'title' => 'First artwork',
        'slug' => 'acceptance-first-artwork',
        'work_date' => null,
    ]);
    $second = $drafts->create([
        'artwork_category_id' => $source->getKey(),
        'title' => 'Second artwork',
        'slug' => 'acceptance-second-artwork',
        'work_date' => null,
    ]);

    expect($first->getAttribute('position'))->toBe(0)
        ->and($second->getAttribute('position'))->toBe(1);

    $asset = acceptanceAsset('artwork-move');
    ArtworkMedia::query()->create([
        'artwork_id' => $second->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'primary',
        'position' => 0,
    ]);

    $moved = app(ArtworkGalleryAssignmentService::class)->reassign($second, $destination);
    expect((int) $moved->getAttribute('artwork_category_id'))->toBe((int) $destination->getKey())
        ->and($moved->artworkMedia()->where('media_asset_id', $asset->getKey())->exists())->toBeTrue();

    $drafts->delete($moved);
    expect(Artwork::query()->whereKey($moved->getKey())->exists())->toBeFalse()
        ->and(ArtworkMedia::query()->where('media_asset_id', $asset->getKey())->exists())->toBeFalse()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
});

it('covers Blog draft edit publication unpublication and safe deletion', function (): void {
    $journal = SiteSection::query()
        ->where('type', SiteNodeType::Journal->value)
        ->where('template', JournalTemplate::Blog->value)
        ->firstOrFail();
    $service = app(BlogEditorialService::class);
    $post = $service->createDraft([
        'site_section_id' => $journal->getKey(),
        'title' => 'Acceptance post',
        'slug' => 'acceptance-post',
        'body' => 'Initial body',
    ]);

    $post = $service->update($post, [
        'title' => 'Acceptance post edited',
        'slug' => 'acceptance-post-edited',
        'body' => 'Edited body',
        'excerpt' => 'Edited excerpt',
        'cover_media_asset_id' => null,
        'state' => $post->getAttribute('state'),
        'position' => $post->getAttribute('position'),
        'published_at' => null,
        'scheduled_at' => null,
    ]);
    $post = $service->publish($post);

    expect($post->getAttribute('state'))->toBe('published')
        ->and($post->getAttribute('published_at'))->not->toBeNull();

    expect(fn () => $service->delete($post))
        ->toThrow(ValidationException::class, 'Unpublish or cancel the schedule');

    $post = $service->unpublish($post);
    $service->delete($post);

    expect(BlogPost::query()->whereKey($post->getKey())->exists())->toBeFalse();
});

it('keeps Exhibition order scoped per Journal and allows equal published positions across Journals', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $firstJournal = $sections->createJournal('Shows one', 'acceptance-shows-one', JournalTemplate::Exhibitions->value);
    $secondJournal = $sections->createJournal('Shows two', 'acceptance-shows-two', JournalTemplate::Exhibitions->value);
    $drafts = app(ExhibitionDraftService::class);
    $first = $drafts->create([
        'site_section_id' => $firstJournal->getKey(),
        'slug' => 'acceptance-show-one',
        'title' => 'Show one',
        'date_text' => '2026',
    ]);
    $firstSibling = $drafts->create([
        'site_section_id' => $firstJournal->getKey(),
        'slug' => 'acceptance-show-one-b',
        'title' => 'Show one B',
        'date_text' => '2027',
        'starts_on' => '2027-01-01',
    ]);
    $second = $drafts->create([
        'site_section_id' => $secondJournal->getKey(),
        'slug' => 'acceptance-show-two',
        'title' => 'Show two',
        'date_text' => '2026',
        'starts_on' => '2026-01-01',
    ]);

    expect($first->getAttribute('position'))->toBe(0)
        ->and($second->getAttribute('position'))->toBe(0);

    $editorial = app(EditorialRecordService::class);
    expect($editorial->move($firstSibling, 'up'))->toBeTrue();
    expect($firstSibling->fresh()->getAttribute('position'))->toBe(0)
        ->and($first->fresh()->getAttribute('position'))->toBe(1)
        ->and($second->fresh()->getAttribute('position'))->toBe(0);

    $editorial->publish($firstSibling->fresh());
    $editorial->publish($second->fresh());

    expect($firstSibling->fresh()->getAttribute('state'))->toBe('published')
        ->and($second->fresh()->getAttribute('state'))->toBe('published');
});

it('deletes only Exhibition media usages and retains referenced MediaAssets', function (): void {
    $journal = app(SiteSectionEditorialService::class)
        ->createJournal('Delete shows', 'acceptance-delete-shows', JournalTemplate::Exhibitions->value);
    $exhibition = app(ExhibitionDraftService::class)->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'acceptance-delete-show',
        'title' => 'Delete show',
        'date_text' => '2026',
    ]);
    $asset = acceptanceAsset('exhibition-delete');
    ExhibitionMedia::query()->create([
        'exhibition_id' => $exhibition->getKey(),
        'media_asset_id' => $asset->getKey(),
        'role' => 'hero',
        'position' => 0,
    ]);

    app(EditorialRecordService::class)->deleteExhibition($exhibition);

    expect(Exhibition::query()->whereKey($exhibition->getKey())->exists())->toBeFalse()
        ->and(ExhibitionMedia::query()->where('media_asset_id', $asset->getKey())->exists())->toBeFalse()
        ->and(MediaAsset::query()->whereKey($asset->getKey())->exists())->toBeTrue();
});
