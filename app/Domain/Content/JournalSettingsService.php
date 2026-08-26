<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JournalSettingsService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SiteSectionPathPolicy $pathPolicy,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(SiteSection $section, array $data): SiteSection
    {
        if ($section->nodeType() !== SiteNodeType::Journal) {
            throw ValidationException::withMessages(['section' => 'Only Journal pages have Journal settings.']);
        }

        $templateValue = $data['template'] ?? $section->getAttribute('template');
        if (! is_string($templateValue) || JournalTemplate::tryFrom($templateValue) === null) {
            throw ValidationException::withMessages(['template' => 'Choose Blog or Exhibitions.']);
        }
        $title = $this->requiredText($data['title'] ?? null, 'title', 160, 'A Journal title is required.');
        $navigationLabel = $this->requiredText($data['navigation_label'] ?? null, 'navigation_label', 120, 'A navigation label is required.');
        $slug = $this->slug($data['slug'] ?? null);
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($section, $templateValue, $title, $navigationLabel, $slug, $actor): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->nodeType() !== SiteNodeType::Journal) {
                throw ValidationException::withMessages(['section' => 'This page is no longer a Journal.']);
            }

            $currentSlug = (string) $fresh->getAttribute('slug');
            if ($slug !== $currentSlug && ! $this->pathPolicy->available($slug)) {
                throw ValidationException::withMessages(['slug' => 'This public URL slug is reserved or already in use.']);
            }

            $fresh->fill([
                'template' => $templateValue,
                'title' => $title,
                'navigation_label' => $navigationLabel,
                'slug' => $slug,
            ]);
            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $fresh->getKey());
            }

            return $fresh->fresh();
        });
    }

    private function requiredText(mixed $value, string $field, int $maxLength, string $message): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => $message]);
        }
        $value = trim($value);
        if ($value === '' || mb_strlen($value) > $maxLength) {
            throw ValidationException::withMessages([$field => $message]);
        }
        return $value;
    }

    private function slug(mixed $value): string
    {
        if (! is_string($value)) {
            throw ValidationException::withMessages(['slug' => 'A public URL slug is required.']);
        }
        $slug = trim($value);
        if ($slug === '' || mb_strlen($slug) > 80 || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
            throw ValidationException::withMessages(['slug' => 'Use lowercase letters, numbers and hyphens for the public URL slug.']);
        }
        return $slug;
    }
}
