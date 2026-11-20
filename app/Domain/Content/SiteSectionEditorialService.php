<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\GalleryTemplate;
use App\Models\GalleryHeroSetting;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\PublicContentSetting;
use App\Models\Redirect;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SiteSectionEditorialService
{
    use SiteSectionConversionWorkflows;

    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SiteSectionPathPolicy $pathPolicy,
    ) {}

    /**
     * Update the visible content-heading only. Page identity/navigation and
     * publication remain owned by their existing services.
     *
     * @param  array<string,mixed>  $data
     */
    public function updatePageHeading(SiteSection $section, array $data): SiteSection
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $data, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->nodeType()->hasPublicPage()) {
                throw ValidationException::withMessages(['content_heading_enabled' => 'This navigation-only node has no public content heading.']);
            }
            $values = SitePageHeading::validated([
                ...SitePageHeading::formState($fresh),
                ...$data,
            ]);
            $fresh->fill($values);
            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());
            }

            return $fresh;
        });
    }

    /** Metadata belongs to the public Page, not its Gallery/Journal settings. */
    public function updateMetaDescription(SiteSection $section, mixed $description): SiteSection
    {
        if ($description !== null && ! is_string($description)) {
            throw ValidationException::withMessages(['meta_description' => 'Enter plain text for the Meta Description.']);
        }

        $value = is_string($description) ? trim($description) : '';
        if (mb_strlen($value) > 10000) {
            throw ValidationException::withMessages(['meta_description' => 'Meta Description must not exceed 10000 characters.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $value, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->hasPublicPage()) {
                throw ValidationException::withMessages(['meta_description' => 'This navigation-only item has no public Meta Description.']);
            }

            $fresh->setAttribute('meta_description', $value === '' ? null : $value);
            if ($fresh->isDirty('meta_description')) {
                $fresh->save();
                $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());
            }

            return $fresh;
        });
    }

    /** Home-only public menu control; routing and publication stay independent. */
    /** Home's public-site gate is page identity, not a content template. */
    public function updateHomePublicSiteGate(SiteSection $section, bool $enabled): bool
    {
        return DB::transaction(function () use ($section, $enabled): bool {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->isHome()) {
                throw ValidationException::withMessages(['public_site_gate' => 'Only the Home page may gate the public site.']);
            }
            // A gate needs a publicly renderable Custom Page with the
            // actual Under Construction content configured by its editor.
            if ($enabled && $fresh->nodeType() !== SiteSectionType::CustomPage) {
                throw ValidationException::withMessages([
                    'public_site_gate' => 'Choose Custom Page before gating the public site.',
                ]);
            }
            $fresh->setAttribute('public_site_gate', $enabled);
            if (! $fresh->isDirty('public_site_gate')) {
                return false;
            }
            $fresh->save();
            $this->audit->record(
                $this->audit->requireActor(), 'site_section.updated', 'site_section', (int) $fresh->getKey(),
            );

            return true;
        });
    }

    public function updateHomeNavigationVisibility(SiteSection $section, bool $visible): bool
    {
        return DB::transaction(function () use ($section, $visible): bool {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if (! $fresh->isHome()) {
                throw ValidationException::withMessages(['show_in_navigation' => 'Only Home supports this navigation setting.']);
            }

            $fresh->setAttribute('show_in_navigation', $visible);
            if (! $fresh->isDirty('show_in_navigation')) {
                return false;
            }

            $fresh->save();
            $this->audit->record(
                $this->audit->requireActor(),
                'site_section.updated',
                'site_section',
                (int) $fresh->getKey(),
            );

            return true;
        });
    }

    public function createNavigationGroup(string $title): SiteSection
    {
        $title = $this->validatedTitle($title);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($title, $actor): SiteSection {
            $section = SiteSection::query()->create([
                'type' => SiteSectionType::NavigationNode->value,
                'template' => null,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => null,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);

            $this->audit->record($actor, 'site_section.created', 'site_section', (int) $section->getKey());

            return $section;
        });
    }

    public function createCustomPage(string $title, string $slug): SiteSection
    {
        $title = $this->validatedTitle($title);
        $slug = $this->validatedSlug($slug);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($title, $slug, $actor): SiteSection {
            $section = SiteSection::query()->create([
                'type' => SiteSectionType::CustomPage->value,
                'template' => null,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => $slug,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);

            $this->ensureCustomPageSetting($section);
            $this->audit->record($actor, 'site_section.created', 'site_section', (int) $section->getKey());

            return $section;
        });
    }

    /**
     * Hero Artworks is a Gallery page template without an Artwork collection.
     * This workflow never creates a synthetic ArtworkCategory.
     */
    public function createHeroGallery(string $title, string $slug): SiteSection
    {
        $title = $this->validatedTitle($title);
        $slug = $this->validatedSlug($slug);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($title, $slug, $actor): SiteSection {
            $section = SiteSection::query()->create([
                'type' => SiteSectionType::Gallery->value,
                'template' => GalleryTemplate::HeroArtworks->value,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => $slug,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);
            $this->ensureGalleryHeroSetting($section);
            $this->audit->record($actor, 'site_section.created', 'site_section', (int) $section->getKey());

            return $section;
        });
    }

    public function createJournal(string $title, string $slug, string $template): SiteSection
    {
        $title = $this->validatedTitle($title);
        $slug = $this->validatedSlug($slug);
        if (JournalTemplate::tryFrom($template) === null) {
            throw ValidationException::withMessages(['template' => 'Choose Blog or Exhibitions as the Journal template.']);
        }
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($title, $slug, $template, $actor): SiteSection {
            $section = SiteSection::query()->create([
                'type' => SiteSectionType::Journal->value,
                'template' => $template,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => $slug,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);

            $this->ensureJournalSetting($section);
            $this->audit->record($actor, 'site_section.created', 'site_section', (int) $section->getKey());

            return $section;
        });
    }

    public function deleteConfigurableSection(SiteSection $section): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($section, $actor): void {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            $type = $fresh->nodeType();

            $isStandaloneHero = $type === SiteSectionType::Gallery
                && $fresh->galleryTemplate() === GalleryTemplate::HeroArtworks
                && $fresh->getAttribute('artwork_category_id') === null;
            if (! $isStandaloneHero && ! in_array($type, [SiteSectionType::CustomPage, SiteSectionType::Journal, SiteSectionType::NavigationNode], true)) {
                throw ValidationException::withMessages(['section' => 'This page cannot be deleted from the configurable page workflow.']);
            }
            if ($fresh->isHome()) {
                throw ValidationException::withMessages(['section' => 'The protected Home page cannot be deleted.']);
            }
            if (
                $type !== SiteSectionType::Journal
                && (string) $fresh->getAttribute('state') !== 'hidden'
            ) {
                throw ValidationException::withMessages(['section' => 'Unpublish the page before deleting it.']);
            }
            if (SiteSection::query()->where('parent_id', $fresh->getKey())->exists()) {
                throw ValidationException::withMessages(['section' => 'Move or delete child pages before deleting their parent.']);
            }
            if (
                $type === SiteSectionType::Journal
                && (
                    BlogPost::query()->where('site_section_id', $fresh->getKey())->exists()
                    || Exhibition::query()->where('site_section_id', $fresh->getKey())->exists()
                )
            ) {
                throw ValidationException::withMessages(['section' => 'This Journal cannot be deleted while it still contains entries.']);
            }

            $sectionId = (int) $fresh->getKey();
            $this->audit->record($actor, 'site_section.deleted', 'site_section', $sectionId);
            $fresh->delete();
        });
    }

    public function updateCustomPageIdentity(
        SiteSection $section,
        string $title,
        ?string $navigationLabel,
        string $slug,
    ): SiteSection {
        if ($section->nodeType() !== SiteSectionType::CustomPage) {
            throw ValidationException::withMessages(['type' => 'Only Custom Pages support these page identity settings.']);
        }

        $title = $this->validatedTitle($title);
        $navigationLabel = trim((string) $navigationLabel);
        if ($navigationLabel === '') {
            $navigationLabel = $title;
        }
        if (mb_strlen($navigationLabel) > 160) {
            throw ValidationException::withMessages(['navigation_label' => 'The navigation label must be short text.']);
        }
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $title, $navigationLabel, $slug, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->nodeType() !== SiteSectionType::CustomPage) {
                throw ValidationException::withMessages(['type' => 'Only Custom Pages support these page identity settings.']);
            }

            $oldSlug = trim((string) $fresh->getAttribute('slug'));
            $newSlug = $this->validatedSlug($slug, (int) $fresh->getKey());
            if ($newSlug !== $oldSlug) {
                $this->retainCustomPagePath($oldSlug, $newSlug);
            }

            $fresh->fill([
                'title' => $title,
                'navigation_label' => $navigationLabel,
                'slug' => $newSlug,
            ]);

            if (! $fresh->isDirty()) {
                return $fresh;
            }

            $fresh->save();
            $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());

            return $fresh->fresh();
        });
    }

    public function updatePlacement(
        SiteSection $section,
        string $state,
        ?int $parentSectionId,
    ): SiteSection {
        if (! in_array($state, ['published', 'hidden'], true)) {
            throw ValidationException::withMessages(['state' => 'The section publication state is invalid.']);
        }
        if ($section->isHome() && $state !== 'published') {
            throw ValidationException::withMessages(['state' => 'Home is always published.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $state, $parentSectionId, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->isHome() && $state !== 'published') {
                throw ValidationException::withMessages(['state' => 'Home is always published.']);
            }

            $parent = $this->parentSection($fresh, $parentSectionId);
            $parentId = $parent?->getKey();
            $parentId = $parentId === null ? null : (int) $parentId;

            if ($parentId !== null && SiteSection::query()->where('parent_id', $fresh->getKey())->exists()) {
                throw ValidationException::withMessages([
                    'parent_id' => 'A page that already has child pages cannot itself become a child page.',
                ]);
            }

            $position = (int) $fresh->getAttribute('position');
            $parentChanged = $fresh->getAttribute('parent_id') !== $parentId;
            if ($parentChanged || $this->publishedPositionConflict($fresh, $state, $parentId, $position)) {
                $position = $this->nextSiblingPosition($fresh, $parentId);
            }

            $fresh->fill([
                'state' => $state,
                'parent_id' => $parentId,
                'position' => $position,
            ]);

            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());
            }

            // Hiding a Gallery revokes its source membership from every Hero
            // page. Republishing does not silently restore a prior selection.
            if ($fresh->nodeType() === SiteSectionType::Gallery && $state !== 'published'
                && is_numeric($fresh->getAttribute('artwork_category_id'))) {
                app(GalleryHeroConfigurationService::class)->disableGalleryAcrossHeroes(
                    (int) $fresh->getAttribute('artwork_category_id'),
                );
            }

            return $fresh;
        });
    }

    private function validatedTitle(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 160) {
            throw ValidationException::withMessages(['title' => 'A short page title is required.']);
        }

        return $title;
    }

    private function validatedSlug(string $slug, ?int $ignoreSiteSectionId = null): string
    {
        $slug = trim($slug);
        if ($slug === '' || mb_strlen($slug) > 80 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw ValidationException::withMessages(['slug' => 'Use lowercase letters, numbers and hyphens for the public URL slug.']);
        }
        if (! $this->pathPolicy->available($slug, $ignoreSiteSectionId)) {
            throw ValidationException::withMessages(['slug' => 'This public URL slug is reserved or already in use.']);
        }

        return $slug;
    }

    private function retainCustomPagePath(string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        $oldPath = '/'.$oldSlug;
        $newPath = '/'.$newSlug;
        $ownedReason = SiteSectionPathPolicy::CUSTOM_PAGE_SLUG_REDIRECT_REASON;

        Redirect::query()
            ->where('reason', $ownedReason)
            ->where('target_path', $oldPath)
            ->update(['target_path' => $newPath]);

        /** @var Redirect|null $sourceRedirect */
        $sourceRedirect = Redirect::query()->where('source_path', $oldPath)->lockForUpdate()->first();
        if ($sourceRedirect !== null && $sourceRedirect->getAttribute('reason') !== $ownedReason) {
            throw ValidationException::withMessages(['slug' => 'The previous public path is already reserved by another redirect.']);
        }

        if ($sourceRedirect === null) {
            Redirect::query()->create([
                'source_path' => $oldPath,
                'target_path' => $newPath,
                'status_code' => 301,
                'enabled' => true,
                'reason' => $ownedReason,
            ]);

            return;
        }

        $sourceRedirect->update([
            'target_path' => $newPath,
            'status_code' => 301,
            'enabled' => true,
        ]);
    }

    private function parentSection(SiteSection $section, ?int $parentSectionId): ?SiteSection
    {
        if ($parentSectionId === null) {
            return null;
        }
        if (! PublicContentSetting::navigationNestingEnabled()) {
            throw ValidationException::withMessages(['parent_id' => 'Enable nested pages in Pages settings before choosing a navigation parent.']);
        }
        if ($parentSectionId === (int) $section->getKey()) {
            throw ValidationException::withMessages(['parent_id' => 'A page cannot be its own parent.']);
        }

        /** @var SiteSection|null $parent */
        $parent = SiteSection::query()->whereKey($parentSectionId)->lockForUpdate()->first();
        if (! $parent instanceof SiteSection || $parent->getAttribute('parent_id') !== null) {
            throw ValidationException::withMessages(['parent_id' => 'The parent must be a top-level page.']);
        }

        return $parent;
    }

    private function publishedPositionConflict(
        SiteSection $section,
        string $state,
        ?int $parentId,
        int $position,
    ): bool {
        if ($state !== 'published') {
            return false;
        }

        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        $query->whereKeyNot($section->getKey())
            ->where('state', 'published')
            ->where('position', $position);
        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);

        return $query->exists();
    }

    private function nextSiblingPosition(?SiteSection $section, ?int $parentId): int
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        if ($section !== null) {
            $query->whereKeyNot($section->getKey());
        }
        $parentId === null ? $query->whereNull('parent_id') : $query->where('parent_id', $parentId);

        /** @var Collection<int, SiteSection> $siblings */
        $siblings = $query->lockForUpdate()->get(['id', 'position']);
        $maximum = $siblings->max('position');

        return $maximum === null ? 10 : ((int) $maximum) + 10;
    }

    private function ensureCustomPageSetting(SiteSection $section): CustomPageSetting
    {
        /** @var CustomPageSetting|null $settings */
        $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->first();
        if ($settings instanceof CustomPageSetting) {
            return $settings;
        }

        $settings = new CustomPageSetting;
        $settings->setAttribute('site_section_id', $section->getKey());
        $settings->setAttribute('blocks', []);
        $settings->save();

        return $settings;
    }

    private function ensureJournalSetting(SiteSection $section): JournalSetting
    {
        /** @var JournalSetting|null $settings */
        $settings = JournalSetting::query()->where('site_section_id', $section->getKey())->first();
        if ($settings instanceof JournalSetting) {
            return $settings;
        }

        $settings = new JournalSetting;
        $settings->setAttribute('site_section_id', $section->getKey());
        $settings->setAttribute('listing_title', (string) $section->getAttribute('title'));
        $settings->setAttribute('listing_intro', null);
        $settings->save();

        return $settings;
    }


}
