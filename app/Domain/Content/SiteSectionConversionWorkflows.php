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

trait SiteSectionConversionWorkflows
{
    public function convertType(SiteSection $section, string $targetType): SiteSection
    {
        $target = SiteSectionType::tryFrom($targetType);
        if ($target === null) {
            throw ValidationException::withMessages(['type' => 'Choose a supported page type.']);
        }
        if ($section->isHome() && $target === SiteSectionType::NavigationNode) {
            throw ValidationException::withMessages(['type' => 'Home must use Gallery, Journal or Custom Page.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $target, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            $source = $fresh->nodeType();
            if ($fresh->isHome() && $target === SiteSectionType::NavigationNode) {
                throw ValidationException::withMessages(['type' => 'Home must use Gallery, Journal or Custom Page.']);
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
                'public_site_gate' => $fresh->isHome() && $target === SiteSectionType::CustomPage
                    ? (bool) $fresh->getAttribute('public_site_gate')
                    : false,
            ]);
            $fresh->save();

            $this->discardSourceConfiguration($fresh, $source, $sourceGallery);
            if ($target === SiteSectionType::Gallery && $fresh->galleryTemplate() === GalleryTemplate::HeroArtworks) {
                $this->ensureGalleryHeroSetting($fresh);
            }
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


    public function updateGalleryTemplate(SiteSection $section, string $template): SiteSection
    {
        $target = GalleryTemplate::tryFrom($template);
        if ($target === null) {
            throw ValidationException::withMessages(['template' => 'Choose Standard or Hero Artworks.']);
        }

        return DB::transaction(function () use ($section, $target): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->nodeType() !== SiteSectionType::Gallery) {
                throw ValidationException::withMessages(['template' => 'Only Gallery pages may select a Gallery template.']);
            }

            if ($fresh->galleryTemplate() === $target) {
                return $fresh;
            }
            if ($target === GalleryTemplate::HeroArtworks) {
                $this->ensureGalleryHeroSetting($fresh);
            } elseif ($fresh->getAttribute('artwork_category_id') === null) {
                // Standard requires a real Artwork collection; Hero does not.
                $slug = $fresh->isHome()
                    ? 'system-home-gallery-'.(int) $fresh->getKey()
                    : (string) $fresh->getAttribute('slug');
                if (ArtworkCategory::query()->where('slug', $slug)->exists()) {
                    throw ValidationException::withMessages(['template' => 'The Gallery collection slug is already in use.']);
                }
                $category = ArtworkCategory::query()->create([
                    'slug' => $slug,
                    'name' => (string) $fresh->getAttribute('title'),
                    ]);
                $fresh->setAttribute('artwork_category_id', $category->getKey());
            }
            $fresh->setAttribute('template', $target->value);
            $fresh->save();
            $this->audit->record(
                $this->audit->requireActor(), 'site_section.gallery_template_updated', 'site_section', (int) $fresh->getKey(),
            );

            return $fresh;
        });
    }

    private function ensureGalleryHeroSetting(SiteSection $section): void
    {
        if (GalleryHeroSetting::query()->where('site_section_id', $section->getKey())->exists()) {
            return;
        }

        $settings = new GalleryHeroSetting;
        $settings->setAttribute('site_section_id', $section->getKey());
        $settings->setAttribute('configuration', []);
        $settings->save();
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
            if ($fresh->nodeType() !== SiteSectionType::Journal) {
                throw ValidationException::withMessages(['template' => 'Only Journal pages have a Journal template.']);
            }

            $current = $fresh->journalTemplate();
            if ($current === $target) {
                return $fresh;
            }

            // A Journal template is a view over retained entries. Switching
            // it never deletes or rewrites posts/exhibitions.
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

    private function assertSourceConfigurationIsDiscardable(SiteSection $section, SiteSectionType $source): ?ArtworkCategory
    {
        if ($source === SiteSectionType::CustomPage) {
            /** @var CustomPageSetting|null $settings */
            $settings = CustomPageSetting::query()->where('site_section_id', $section->getKey())->lockForUpdate()->first();
            if (! $section->isHome() && $settings instanceof CustomPageSetting && $settings->getAttribute('blocks') !== []) {
                throw ValidationException::withMessages([
                    'type' => 'This Custom Page contains components. Remove or move that content before changing its page type.',
                ]);
            }

            return null;
        }

        if ($source === SiteSectionType::Journal) {
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

        if ($source === SiteSectionType::Gallery) {
            // Gallery configuration is artist data even when no Artwork is
            // attached. Never discard a normal Gallery record as a side effect
            // of changing a page type. Home alone may reset its old content.
            if (! $section->isHome() && $section->getAttribute('artwork_category_id') !== null) {
                throw ValidationException::withMessages([
                    'type' => 'This Gallery owns a collection. Remove it explicitly in Gallery management before changing the page type.',
                ]);
            }
            /** @var GalleryHeroSetting|null $heroSetting */
            $heroSetting = GalleryHeroSetting::query()
                ->where('site_section_id', $section->getKey())->lockForUpdate()->first();
            if (! $section->isHome() && $heroSetting instanceof GalleryHeroSetting
                && $heroSetting->configuration() !== []) {
                throw ValidationException::withMessages([
                    'type' => 'This Hero Artworks page contains saved settings. Clear them before changing the page type.',
                ]);
            }

            /** @var ArtworkCategory|null $gallery */
            $gallery = ArtworkCategory::query()
                ->whereKey($section->getAttribute('artwork_category_id'))
                ->lockForUpdate()
                ->first();
            if (! $gallery instanceof ArtworkCategory) {
                if ($section->galleryTemplate() === GalleryTemplate::HeroArtworks) {
                    return null;
                }

                throw ValidationException::withMessages(['type' => 'This Standard is missing its Gallery configuration.']);
            }

            $nameMatches = trim((string) $gallery->getAttribute('name')) === trim((string) $section->getAttribute('title'));
            $slugMatches = trim((string) $gallery->getAttribute('slug')) === trim((string) $section->getAttribute('slug'));
            if (
                $gallery->artworks()->exists()
                || (! $section->isHome() && (! $nameMatches || ! $slugMatches))
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
    private function prepareTargetConfiguration(SiteSection $section, SiteSectionType $target): array
    {
        if ($target === SiteSectionType::NavigationNode) {
            return [null, null, null];
        }

        $slug = $section->isHome() ? null : $this->conversionSlug($section, $target === SiteSectionType::Gallery);

        if ($target === SiteSectionType::CustomPage) {
            $this->ensureCustomPageSetting($section);

            return [$slug, null, null];
        }

        if ($target === SiteSectionType::Journal) {
            $this->ensureJournalSetting($section);

            return [$slug, JournalTemplate::Blog->value, null];
        }

        if ($target === SiteSectionType::Gallery) {
            if ($section->isHome()) {
                // The protected start page is a Hero presentation, not an
                // empty Artwork collection. Keep it independent of Galleries.
                return [null, GalleryTemplate::HeroArtworks->value, null];
            }

            $gallerySlug = $slug;
            if (ArtworkCategory::query()->where('slug', $gallerySlug)->exists()) {
                throw ValidationException::withMessages(['type' => 'A Gallery configuration already uses this page slug.']);
            }

            $gallery = ArtworkCategory::query()->create([
                'slug' => $gallerySlug,
                'name' => (string) $section->getAttribute('title'),
            ]);

            return [$slug, $section->isHome() ? GalleryTemplate::HeroArtworks->value : GalleryTemplate::Standard->value, (int) $gallery->getKey()];
        }

        throw ValidationException::withMessages(['type' => 'Choose a supported page type.']);
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
        SiteSectionType $source,
        ?ArtworkCategory $sourceGallery,
    ): void {
        if ($source === SiteSectionType::CustomPage) {
            CustomPageSetting::query()->where('site_section_id', $section->getKey())->delete();
        }

        if ($source === SiteSectionType::Journal) {
            JournalSetting::query()->where('site_section_id', $section->getKey())->delete();
        }

        if ($source === SiteSectionType::Gallery) {
            GalleryHeroSetting::query()->where('site_section_id', $section->getKey())->delete();
            if ($sourceGallery instanceof ArtworkCategory) {
                $sourceGallery->delete();
            }
        }
    }
}
