<?php

use App\Domain\Content\PublicAppearance;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once __DIR__.'/../Admin/Gallery/GalleryFixtures.php';

it('validates the editable artist-name position and lettering', function (): void {
    expect(PublicAppearance::headerPosition(null))->toBe('inside')
        ->and(PublicAppearance::headerPosition('outside'))->toBe('outside')
        ->and(PublicAppearance::headerPosition('below'))->toBe('below')
        ->and(PublicAppearance::headerCaps(null))->toBe('small-caps')
        ->and(PublicAppearance::headerCaps('normal'))->toBe('normal');

    expect(fn () => PublicAppearance::headerPosition('overlap'))->toThrow(ValidationException::class);
    expect(fn () => PublicAppearance::headerCaps('uppercase'))->toThrow(ValidationException::class);
});

it('redirects a published gallery parent to its first eligible child in editorial order', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update(['template' => 'custom']);
    }

    $parent = galleryFixtureCategory();
    $first = galleryFixtureCategory();
    $second = galleryFixtureCategory();

    $parentSection = SiteSection::query()->where('artwork_category_id', $parent->getKey())->firstOrFail();
    $firstSection = SiteSection::query()->where('artwork_category_id', $first->getKey())->firstOrFail();
    $secondSection = SiteSection::query()->where('artwork_category_id', $second->getKey())->firstOrFail();
    $service = app(SiteSectionEditorialService::class);

    $service->updatePlacement($firstSection, 'hidden', (int) $parentSection->getKey());
    $service->updatePlacement($secondSection, 'published', (int) $parentSection->getKey());
    $service->updateNavigationChildTarget($parentSection, 'first_child');

    // The public tree must not include the unpublished first sibling.
    app(PublicationService::class)->commit($actor, 'Publish gallery child landing');

    $this->get('/'.$parent->slug)
        ->assertRedirect(route('site.section', ['section' => $second->slug]));

    $landing = $this->get('/'.$second->slug)->assertOk()->getContent();
    expect($landing)->toContain('site-header--name-inside')
        ->toContain('site-header--joined')
        ->toContain('site-header--caps-small-caps');

    // Changing the parent option restores its canonical Gallery URL.
    $service->updateNavigationChildTarget($parentSection, 'gallery');
    app(PublicationService::class)->commit($actor, 'Restore gallery parent landing');
    $this->get('/'.$parent->slug)->assertOk();
});

it('rejects unsupported gallery destination settings', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $category = galleryFixtureCategory();
    $section = SiteSection::query()->where('artwork_category_id', $category->getKey())->firstOrFail();

    expect(fn () => app(SiteSectionEditorialService::class)->updateNavigationChildTarget($section, 'missing'))
        ->toThrow(ValidationException::class);
});
