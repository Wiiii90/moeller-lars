<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\JournalSetting;
use App\Models\Redirect;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class SiteSectionIdentityService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SiteSectionPathPolicy $pathPolicy,
    ) {}

    public function update(SiteSection $section, string $name, ?string $slug): SiteSection
    {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages(['name' => 'A short page name is required.']);
        }

        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $name, $slug, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            $type = $fresh->nodeType();
            if ($type === SiteSectionType::Gallery) {
                throw ValidationException::withMessages(['name' => 'Gallery identity must be updated through the Gallery workflow.']);
            }

            $oldName = trim((string) $fresh->getAttribute('title'));
            $oldSlug = trim((string) $fresh->getAttribute('slug'));
            $newSlug = null;

            if ($type->requiresSlug()) {
                $newSlug = $this->validatedSlug($slug, (int) $fresh->getKey(), $oldSlug);
                if ($newSlug !== $oldSlug) {
                    $this->retainPublicPath($oldSlug, $newSlug);
                }
            }

            if ($type === SiteSectionType::Journal) {
                /** @var JournalSetting|null $settings */
                $settings = JournalSetting::query()->where('site_section_id', $fresh->getKey())->lockForUpdate()->first();
                if ($settings instanceof JournalSetting && trim((string) $settings->getAttribute('listing_title')) === $oldName) {
                    $settings->setAttribute('listing_title', $name);
                    $settings->save();
                }
            }

            $fresh->fill([
                'title' => $name,
                'navigation_label' => $name,
                'slug' => $newSlug,
            ]);

            if (! $fresh->isDirty()) {
                return $fresh;
            }

            $fresh->save();
            $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());

            /** @var SiteSection $updated */
            $updated = $fresh->fresh();

            return $updated;
        });
    }

    private function validatedSlug(?string $slug, int $ignoreSiteSectionId, string $oldSlug): string
    {
        $slug = trim((string) $slug);
        if ($slug === '' || mb_strlen($slug) > 80 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw ValidationException::withMessages(['slug' => 'Use lowercase letters, numbers and hyphens for the public URL slug.']);
        }

        if ($slug !== $oldSlug && ! $this->pathPolicy->available($slug, $ignoreSiteSectionId)) {
            throw ValidationException::withMessages(['slug' => 'This public URL slug is reserved or already in use.']);
        }

        return $slug;
    }

    private function retainPublicPath(string $oldSlug, string $newSlug): void
    {
        if ($oldSlug === '' || $oldSlug === $newSlug) {
            return;
        }

        $oldPath = '/'.$oldSlug;
        $newPath = '/'.$newSlug;
        $ownedReason = SiteSectionPathPolicy::CUSTOM_PAGE_SLUG_REDIRECT_REASON;

        Redirect::query()->where('reason', $ownedReason)->where('target_path', $oldPath)->update(['target_path' => $newPath]);

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
}
