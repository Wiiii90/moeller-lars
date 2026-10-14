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
    public function update(SiteSection $section, array $data, ?array $expectedOriginal = null): SiteSection
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

        // Keep the mounted-dialog snapshot check and all canonical identity /
        // template mutations under the same row lock and transaction.
        return DB::transaction(function () use ($section, $templateValue, $title, $navigationLabel, $data, $expectedOriginal): SiteSection {
            /** @var SiteSection $fresh */
            $fresh = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
            if ($fresh->nodeType() !== SiteSectionType::Journal) {
                throw ValidationException::withMessages(['section' => 'This page is no longer a Journal.']);
            }
            if ($expectedOriginal !== null && $fresh->only(array_keys($expectedOriginal)) !== $expectedOriginal) {
                throw ValidationException::withMessages(['title' => 'This Journal changed. Reopen the settings dialog.']);
            }

            $updated = $this->identity->update(
                $fresh,
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
