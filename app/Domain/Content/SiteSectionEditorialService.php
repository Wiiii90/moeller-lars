<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CustomPageSetting;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\Redirect;
use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class SiteSectionEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SiteSectionPathPolicy $pathPolicy,
    ) {}

    public function createNavigationGroup(string $title): SiteSection
    {
        $title = $this->validatedTitle($title);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($title, $actor): SiteSection {
            $section = SiteSection::query()->create([
                'type' => SiteNodeType::NavigationNode->value,
                'template' => null,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => null,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'show_in_navigation' => true,
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
                'type' => SiteNodeType::CustomPage->value,
                'template' => null,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => $slug,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'show_in_navigation' => false,
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);

            $this->ensureCustomPageSetting($section);
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
                'type' => SiteNodeType::Journal->value,
                'template' => $template,
                'title' => $title,
                'navigation_label' => $title,
                'slug' => $slug,
                'state' => 'hidden',
                'position' => $this->nextSiblingPosition(null, null),
                'show_in_navigation' => false,
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

            if (! in_array($type, [SiteNodeType::CustomPage, SiteNodeType::Journal, SiteNodeType::NavigationNode], true)) {
                throw ValidationException::withMessages(['section' => 'This page cannot be deleted from the configurable page workflow.']);
            }
            if (
                $type !== SiteNodeType::Journal
                && ((string) $fresh->getAttribute('state') !== 'hidden' || (bool) $fresh->getAttribute('show_in_navigation'))
            ) {
                throw ValidationException::withMessages(['section' => 'Unpublish the page and remove it from navigation before deleting it.']);
            }
            if (SiteSection::query()->where('parent_id', $fresh->getKey())->exists()) {
                throw ValidationException::withMessages(['section' => 'Move or delete child pages before deleting their parent.']);
            }
            if (
                $type === SiteNodeType::Journal
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

    public function updateGallery(
        SiteSection $section,
        string $state,
        bool $showInNavigation,
        ?int $parentSectionId,
    ): SiteSection {
        if ($section->nodeType() !== SiteNodeType::Gallery) {
            throw ValidationException::withMessages(['type' => 'Only Gallery sections support Gallery placement controls.']);
        }

        return $this->updatePlacement($section, $state, $showInNavigation, $parentSectionId);
    }

    public function updateCustomPageIdentity(
        SiteSection $section,
        string $title,
        ?string $navigationLabel,
        string $slug,
    ): SiteSection {
        if ($section->nodeType() !== SiteNodeType::CustomPage) {
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
            if ($fresh->nodeType() !== SiteNodeType::CustomPage) {
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
        bool $showInNavigation,
        ?int $parentSectionId,
    ): SiteSection {
        if (! in_array($state, ['published', 'hidden'], true)) {
            throw ValidationException::withMessages(['state' => 'The section publication state is invalid.']);
        }
        if ($section->nodeType() === SiteNodeType::Home && $state !== 'published') {
            throw ValidationException::withMessages(['state' => 'Home is always published.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $state, $showInNavigation, $parentSectionId, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->nodeType() === SiteNodeType::Home && $state !== 'published') {
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
            if ($parentChanged || $this->visiblePositionConflict($fresh, $state, $showInNavigation, $parentId, $position)) {
                $position = $this->nextSiblingPosition($fresh, $parentId);
            }

            $fresh->fill([
                'state' => $state,
                'show_in_navigation' => $showInNavigation,
                'parent_id' => $parentId,
                'position' => $position,
            ]);
            $fresh->save();

            $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());

            return $fresh;
        });
    }

    public function convertType(SiteSection $section, string $targetType): SiteSection
    {
        $target = SiteNodeType::tryFrom($targetType);
        if ($target === null) {
            throw ValidationException::withMessages(['type' => 'Choose a supported page type.']);
        }
        if ($target === SiteNodeType::Home || $section->nodeType() === SiteNodeType::Home) {
            throw ValidationException::withMessages(['type' => 'Home cannot be converted to or from another page type.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $target, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            $source = $fresh->nodeType();
            if ($source === SiteNodeType::Home || $target === SiteNodeType::Home) {
                throw ValidationException::withMessages(['type' => 'Home cannot be converted to or from another page type.']);
            }
            if ($source === $target) {
                return $fresh;
            }

            $sourceGallery = $this->assertSourceConfigurationIsDiscardable($fresh, $source);
            [$slug, $template, $artworkCategoryId] = $this->prepareTargetConfiguration($fresh, $target);

            $fresh->fill([
                'type' => $target->value,
                'template' => $template,
                'slug' => $slug,
                'artwork_category_id' => $artworkCategoryId,
            ]);
            $fresh->save();

            $this->discardSourceConfiguration($fresh, $source, $sourceGallery);
            $this->audit->record(
                $actor,
                'site_section.type_converted',
                'site_section',
                (int) $fresh->getKey(),
            );

            /** @var SiteSection $converted */
            $converted = $fresh->fresh();

            return $converted;
        });
    }

    public function updateJournalTemplate(SiteSection $section, string $template): SiteSection
    {
        $target = JournalTemplate::tryFrom($template);
        if ($target === null) {
            throw ValidationException::withMessages(['template' => 'Choose Blog or Exhibitions as the Journal template.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $target, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->nodeType() !== SiteNodeType::Journal) {
                throw ValidationException::withMessages(['template' => 'Only Journal pages have a Journal template.']);
            }

            $current = $fresh->journalTemplate();
            if ($current === $target) {
                return $fresh;
            }

            if (
                BlogPost::query()->where('site_section_id', $fresh->getKey())->exists()
                || Exhibition::query()->where('site_section_id', $fresh->getKey())->exists()
            ) {
                throw ValidationException::withMessages([
                    'template' => 'Move or remove existing Journal entries before changing the Journal template.',
                ]);
            }

            $this->ensureJournalSetting($fresh);
            $fresh->setAttribute('template', $target->value);
            $fresh->save();

            $this->audit->record(
                $actor,
                'site_section.journal_template_updated',
                'site_section',
                (int) $fresh->getKey(),
            );

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

    private function visiblePositionConflict(
        SiteSection $section,
        string $state,
        bool $showInNavigation,
        ?int $parentId,
        int $position,
    ): bool {
        if ($state !== 'published' || ! $showInNavigation) {
            return false;
        }

        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query();
        $query->whereKeyNot($section->getKey())
            ->where('state', 'published')
            ->where('show_in_navigation', true)
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

    private function assertSourceConfigurationIsDiscardable(SiteSection $section, SiteNodeType $source): ?ArtworkCategory
    {
        if ($source === SiteNodeType::Home) {
            throw ValidationException::withMessages(['type' => 'Home cannot be converted to another page type.']);
        }

        if ($source === SiteNodeType::CustomPage) {
            /** @var CustomPageSetting|null $settings */
            $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->lockForUpdate()->first();
            if ($settings instanceof CustomPageSetting && $settings->getAttribute('blocks') !== []) {
                throw ValidationException::withMessages([
                    'type' => 'This Custom Page contains components. Remove or move that content before changing its page type.',
                ]);
            }

            return null;
        }

        if ($source === SiteNodeType::Journal) {
            if (
                BlogPost::query()->where('site_section_id', $section->getKey())->exists()
                || Exhibition::query()->where('site_section_id', $section->getKey())->exists()
            ) {
                throw ValidationException::withMessages([
                    'type' => 'This Journal contains entries. Move or remove them before changing its page type.',
                ]);
            }

            /** @var JournalSetting|null $settings */
            $settings = JournalSetting::query()->where('site_section_id', $section->getKey())->lockForUpdate()->first();
            if ($settings instanceof JournalSetting) {
                $listingTitle = trim((string) $settings->getAttribute('listing_title'));
                $listingIntro = trim((string) $settings->getAttribute('listing_intro'));
                if (
                    $listingIntro !== ''
                    || ($listingTitle !== '' && $listingTitle !== trim((string) $section->getAttribute('title')))
                ) {
                    throw ValidationException::withMessages([
                        'type' => 'This Journal has customized listing content. Clear it before changing its page type.',
                    ]);
                }
            }

            return null;
        }

        if ($source === SiteNodeType::Gallery) {
            /** @var ArtworkCategory|null $gallery */
            $gallery = ArtworkCategory::query()
                ->whereKey($section->getAttribute('artwork_category_id'))
                ->lockForUpdate()
                ->first();
            if (! $gallery instanceof ArtworkCategory) {
                throw ValidationException::withMessages(['type' => 'This Gallery is missing its Gallery configuration.']);
            }

            $description = trim((string) $gallery->getAttribute('description'));
            $nameMatches = trim((string) $gallery->getAttribute('name')) === trim((string) $section->getAttribute('title'));
            $slugMatches = trim((string) $gallery->getAttribute('slug')) === trim((string) $section->getAttribute('slug'));
            if (
                $gallery->artworks()->exists()
                || (bool) $gallery->getAttribute('show_on_home')
                || $description !== ''
                || ! $nameMatches
                || ! $slugMatches
            ) {
                throw ValidationException::withMessages([
                    'type' => 'This Gallery contains artwork or Gallery-specific content. Clear it before changing its page type.',
                ]);
            }

            return $gallery;
        }

        return null;
    }

    /** @return array{0:?string,1:?string,2:?int} */
    private function prepareTargetConfiguration(SiteSection $section, SiteNodeType $target): array
    {
        if ($target === SiteNodeType::NavigationNode) {
            return [null, null, null];
        }

        $slug = $this->conversionSlug($section, $target === SiteNodeType::Gallery);

        if ($target === SiteNodeType::CustomPage) {
            $this->ensureCustomPageSetting($section);

            return [$slug, null, null];
        }

        if ($target === SiteNodeType::Journal) {
            $this->ensureJournalSetting($section);

            return [$slug, JournalTemplate::Blog->value, null];
        }

        if ($target === SiteNodeType::Gallery) {
            if (ArtworkCategory::query()->where('slug', $slug)->exists()) {
                throw ValidationException::withMessages(['type' => 'A Gallery configuration already uses this page slug.']);
            }

            $gallery = ArtworkCategory::query()->create([
                'slug' => $slug,
                'name' => (string) $section->getAttribute('title'),
                'description' => null,
                'show_on_home' => false,
            ]);

            return [$slug, null, (int) $gallery->getKey()];
        }

        throw ValidationException::withMessages(['type' => 'Home cannot be selected as a conversion target.']);
    }

    private function conversionSlug(SiteSection $section, bool $forGallery): string
    {
        $current = $section->getAttribute('slug');
        if (is_string($current) && trim($current) !== '') {
            return trim($current);
        }

        $base = Str::slug((string) $section->getAttribute('title'));
        if ($base === '') {
            $base = 'page-'.(int) $section->getKey();
        }
        $base = mb_substr($base, 0, 70);

        for ($suffix = 0; $suffix < 100; $suffix++) {
            $candidate = $suffix === 0 ? $base : $base.'-'.($suffix + 1);
            if (! $this->pathPolicy->available($candidate)) {
                continue;
            }
            if ($forGallery && ArtworkCategory::query()->where('slug', $candidate)->exists()) {
                continue;
            }

            return $candidate;
        }

        throw ValidationException::withMessages(['slug' => 'A safe public URL slug could not be generated for this page type.']);
    }

    private function discardSourceConfiguration(
        SiteSection $section,
        SiteNodeType $source,
        ?ArtworkCategory $sourceGallery,
    ): void {
        if ($source === SiteNodeType::CustomPage) {
            CustomPageSetting::query()->where('site_section_id', $section->getKey())->delete();
        }

        if ($source === SiteNodeType::Journal) {
            JournalSetting::query()->where('site_section_id', $section->getKey())->delete();
        }

        if ($source === SiteNodeType::Gallery && $sourceGallery instanceof ArtworkCategory) {
            $sourceGallery->delete();
        }
    }
}
