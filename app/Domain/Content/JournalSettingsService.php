<?php

namespace App\Domain\Content;

use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class JournalSettingsService
{
    public function __construct(
        private readonly SiteSectionIdentityService $identity,
        private readonly SiteSectionEditorialService $sections,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(SiteSection $section, array $data): SiteSection
    {
        if ($section->nodeType() !== SiteSectionType::Journal) {
            throw ValidationException::withMessages(['section' => 'Only Journal pages have Journal settings.']);
        }

        $templateValue = $data['template'] ?? $section->getAttribute('template');
        if (! is_string($templateValue) || JournalTemplate::tryFrom($templateValue) === null) {
            throw ValidationException::withMessages(['template' => 'Choose Blog or Exhibitions.']);
        }
        $title = $this->requiredText($data['title'] ?? null, 'title', 160, 'A Journal title is required.');
        $navigationLabel = $data['navigation_label'] ?? null;
        if ($navigationLabel !== null && ! is_string($navigationLabel)) {
            throw ValidationException::withMessages(['navigation_label' => 'The navigation label must be text.']);
        }
        // If the original label just repeated the old title, changing the
        // title updates that label too. Explicitly customized labels remain.
        $currentTitle = trim((string) $section->getAttribute('title'));
        $currentLabel = trim((string) $section->getAttribute('navigation_label'));
        if ($navigationLabel === null || trim($navigationLabel) === '' || (
            $currentLabel === $currentTitle && trim($navigationLabel) === $currentLabel
        )) {
            $navigationLabel = $title;
        }

        // Use Pages' existing identity and template owners. The outer
        // transaction keeps redirects, title/label, and template atomic.
        return DB::transaction(function () use ($section, $title, $navigationLabel, $templateValue, $data): SiteSection {
            $updated = $this->identity->update(
                $section,
                $title,
                is_string($data['slug'] ?? null) ? $data['slug'] : null,
                $navigationLabel,
            );

            if ((string) $updated->getAttribute('template') !== $templateValue) {
                $updated = $this->sections->updateJournalTemplate($updated, $templateValue);
            }

            return $updated;
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
}
