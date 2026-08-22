<?php

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\Dashboard;
use App\Filament\Pages\HomePresentation;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('renders clickable typed Gallery nodes in the Pages sidebar', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $parent = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'Paintings',
        'slug' => 'paintings-ui-system',
        'description' => null,
        'show_on_home' => false,
    ]);
    $parentSection = $parent->siteSection()->firstOrFail();

    $child = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'Test',
        'slug' => 'test-ui-system',
        'description' => null,
        'show_on_home' => false,
        'parent_section_id' => (int) $parentSection->getKey(),
    ]);

    $parentUrl = ArtworkResource::getUrl('gallery', ['gallery' => $parent->getKey()]);
    $childUrl = ArtworkResource::getUrl('gallery', ['gallery' => $child->getKey()]);

    $this->get(Dashboard::getUrl())
        ->assertSuccessful()
        ->assertSee('data-artist-tree-branch="true"', false)
        ->assertSee('data-artist-site-section-depth="1"', false)
        ->assertSee('data-artist-site-section-type="gallery"', false)
        ->assertSee('href="'.e($parentUrl).'"', false)
        ->assertSee('href="'.e($childUrl).'"', false);
});

it('links Pages rows directly to their typed content workspaces', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $gallery = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'Drawings',
        'slug' => 'drawings-ui-system',
        'description' => null,
        'show_on_home' => false,
    ]);

    $sections = app(SiteSectionEditorialService::class);
    $custom = $sections->createCustomPage('Studio', 'studio-ui-system');
    $blog = $sections->createJournal('Blog', 'blog-ui-system', SiteSection::JOURNAL_TEMPLATE_BLOG);
    $exhibitions = $sections->createJournal('Exhibitions', 'exhibitions-ui-system', SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS);

    $customSettings = CustomPageSetting::query()->where('site_section_id', $custom->getKey())->firstOrFail();

    $response = $this->get(SitePages::getUrl())->assertSuccessful();

    foreach ([
        HomePresentation::getUrl(),
        ArtworkResource::getUrl('gallery', ['gallery' => $gallery->getKey()]),
        CustomPageSettingResource::getUrl('edit', ['record' => $customSettings]),
        BlogPostResource::getUrl('index', ['section' => $blog->getKey()]),
        ExhibitionResource::getUrl('index', ['section' => $exhibitions->getKey()]),
    ] as $url) {
        $response->assertSee('href="'.e($url).'"', false);
    }
});

it('opens typed Pages destinations without server errors', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $paintings = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'Paintings',
        'slug' => 'paintings-destination-test',
        'description' => null,
        'show_on_home' => false,
    ]);
    $paintingsSection = $paintings->siteSection()->firstOrFail();

    $childGallery = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'Small Works',
        'slug' => 'small-works-destination-test',
        'description' => null,
        'show_on_home' => false,
        'parent_section_id' => (int) $paintingsSection->getKey(),
    ]);

    $sections = app(SiteSectionEditorialService::class);
    $custom = $sections->createCustomPage('Studio', 'studio-destination-test');
    $blog = $sections->createJournal('Blog', 'blog-destination-test', SiteSection::JOURNAL_TEMPLATE_BLOG);
    $exhibitions = $sections->createJournal('Exhibitions', 'exhibitions-destination-test', SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS);
    $customSettings = CustomPageSetting::query()->where('site_section_id', $custom->getKey())->firstOrFail();

    foreach ([
        HomePresentation::getUrl(),
        ArtworkResource::getUrl('gallery', ['gallery' => $paintings->getKey()]),
        ArtworkResource::getUrl('gallery', ['gallery' => $childGallery->getKey()]),
        CustomPageSettingResource::getUrl('edit', ['record' => $customSettings]),
        BlogPostResource::getUrl('index', ['section' => $blog->getKey()]),
        ExhibitionResource::getUrl('index', ['section' => $exhibitions->getKey()]),
    ] as $url) {
        $this->get($url)->assertSuccessful();
    }
});

it('keeps direct editor routes usable as flat fallback workspaces', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $gallery = app(ArtworkCategoryEditorialService::class)->create([
        'name' => 'Prints',
        'slug' => 'prints-ui-system',
        'description' => null,
        'show_on_home' => false,
    ]);
    $custom = app(SiteSectionEditorialService::class)->createCustomPage('About', 'about-ui-system');
    $customSettings = CustomPageSetting::query()->where('site_section_id', $custom->getKey())->firstOrFail();

    $this->get(ArtworkResource::getUrl('create', ['gallery' => $gallery->getKey()]))
        ->assertSuccessful();

    $this->get(CustomPageSettingResource::getUrl('edit', ['record' => $customSettings]))
        ->assertSuccessful();
});

it('renders migrated CV Custom Page data through the canonical CV structure', function (): void {
    $section = SiteSection::query()->where('slug', 'cv')->firstOrFail();
    $section->forceFill(['state' => 'published'])->save();

    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $settings->setAttribute('blocks', [
        [
            'type' => 'list',
            'divider' => true,
            'items' => [[
                'visible' => true,
                'date' => '2026',
                'title' => 'Test exhibition',
                'meta' => 'Test institution',
                'location' => 'Hamburg',
                'body' => null,
                'url' => null,
            ]],
        ],
        [
            'type' => 'text',
            'divider' => false,
            'title' => 'Statement',
            'body' => 'A short profile text.',
        ],
    ]);
    $settings->save();

    $this->get('/cv')
        ->assertSuccessful()
        ->assertSee('class="cv-page"', false)
        ->assertSee('class="cv-legacy-layout"', false)
        ->assertSee('class="cv-entry"', false)
        ->assertSee('class="cv-text-blocks"', false)
        ->assertDontSee('custom-page--cv', false);
});

it('renders migrated Contact Custom Page data with the canonical contact form classes', function (): void {
    PublicContentSetting::contact()->update(['contact_state' => 'enabled']);

    $section = SiteSection::query()->where('slug', 'contact')->firstOrFail();
    $section->forceFill(['state' => 'published'])->save();

    $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->firstOrFail();
    $settings->setAttribute('blocks', [[
        'type' => 'contact',
        'divider' => false,
        'show_email' => true,
        'show_form' => true,
        'social_platforms' => [],
    ]]);
    $settings->save();

    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('class="contact-page', false)
        ->assertSee('class="contact-form"', false)
        ->assertSee('class="contact-form__field"', false);
});
