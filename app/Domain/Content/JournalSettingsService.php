<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\JournalSetting;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Validator;

final class JournalSettingsService
{
    public function __construct(
        private readonly SiteSectionIdentityService $identity,
        private readonly SiteSectionEditorialService $sections,
        private readonly AdminAuditService $audit,
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
        $navigationLabel = $data['navigation_label'] ?? $section->getAttribute('navigation_label');
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
                is_string($data['slug'] ?? null) ? $data['slug'] : (string) $fresh->getAttribute('slug'),
                $navigationLabel,
            );
            if ((string) $updated->getAttribute('template') !== $templateValue) {
                $updated = $this->sections->updateJournalTemplate($updated, $templateValue);
            }

            return $updated;
        });
    }

    /** @return array<string, bool|string> */
    public function displayValues(SiteSection $section): array
    {
        return $this->valuesFor($section, JournalSetting::forSection($section));
    }

    /** @return array<string, bool|string> */
    public function editorDefaults(SiteSection $section): array
    {
        $values = $this->displayValues($section);

        return [
            'gallery_presentation' => $values['default_gallery_presentation'],
            'image_viewer_enabled' => $values['default_image_viewer_enabled'],
            ...($section->journalTemplate() === JournalTemplate::Exhibitions ? [
                'gallery_enabled' => $values['default_gallery_enabled'],
                'show_vernissage' => $values['default_show_vernissage'],
            ] : []),
        ];
    }

    /** @param array<string, mixed> $data */
    public function updateDisplay(SiteSection $section, array $data): bool
    {
        if ($section->nodeType() !== SiteSectionType::Journal) {
            throw ValidationException::withMessages(['section' => 'Only Journals have Journal presentation settings.']);
        }

        return DB::transaction(function () use ($section, $data): bool {
            $setting = JournalSetting::query()->where('site_section_id', $section->getKey())->lockForUpdate()->firstOrFail();
            $source = $this->valuesFor($section, $setting);
            $options = $section->journalTemplate() === JournalTemplate::Blog
                ? ['sequence', 'grid', 'mosaic', 'slideshow', 'carousel']
                : ['grid', 'mosaic', 'slideshow', 'carousel'];
            $values = Validator::make([...$source, ...array_intersect_key($data, $source)], [
                'default_gallery_presentation' => ['required', Rule::in($options)],
                'default_image_viewer_enabled' => ['required', 'boolean'],
                'default_gallery_enabled' => ['required', 'boolean'],
                'default_show_vernissage' => ['required', 'boolean'],
            ])->validate();

            $setting->fill($values);
            if (! $setting->isDirty()) {
                return false;
            }

            $setting->save();
            $this->audit->record($this->audit->requireActor(), 'journal_setting.updated', 'journal_setting', (int) $setting->getKey());

            return true;
        });
    }

    /** @return array<string, bool|string> */
    private function valuesFor(SiteSection $section, JournalSetting $setting): array
    {
        $presentation = trim((string) ($setting->getAttribute('default_gallery_presentation') ?? ''));

        return [
            'default_gallery_presentation' => $presentation !== ''
                ? $presentation : ($section->journalTemplate() === JournalTemplate::Blog ? 'sequence' : 'grid'),
            'default_image_viewer_enabled' => (bool) $setting->getAttribute('default_image_viewer_enabled'),
            'default_gallery_enabled' => (bool) $setting->getAttribute('default_gallery_enabled'),
            'default_show_vernissage' => (bool) $setting->getAttribute('default_show_vernissage'),
        ];
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
