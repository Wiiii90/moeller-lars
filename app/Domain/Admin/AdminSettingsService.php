<?php

namespace App\Domain\Admin;

use App\Domain\Content\ContactSocialProfileCascade;
use App\Domain\Content\PublicAppearance;
use App\Domain\Content\SocialLinks;
use App\Models\JournalSetting;
use App\Models\PublicContentSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class AdminSettingsService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @param array<string,mixed> $expected Expected original values for optimistic editor concurrency. */
    public function updatePublicContent(PublicContentSetting $setting, array $data, array $expected = []): PublicContentSetting
    {
        $normalized = $this->normalizePublicContent($data);
        if (! array_key_exists('social_links', $normalized)) {
            /** @var PublicContentSetting $updated */
            $updated = $this->update($setting, $normalized, 'public_content_setting.updated', 'public_content_setting', $expected);

            return $updated;
        }

        // General owns the profile identity. A removal must clear every
        // dependent Contact selection in the same transaction as General.
        return DB::transaction(function () use ($setting, $normalized, $expected): PublicContentSetting {
            $before = PublicContentSetting::query()->whereKey($setting->getKey())->lockForUpdate()->firstOrFail();
            $previous = array_column(SocialLinks::configured($before->getAttribute('social_links')), 'platform');

            /** @var PublicContentSetting $updated */
            $updated = $this->update($setting, $normalized, 'public_content_setting.updated', 'public_content_setting', $expected);
            $current = array_column(SocialLinks::configured($updated->getAttribute('social_links')), 'platform');
            $removed = array_values(array_diff($previous, $current));

            if ($removed !== []) {
                app(ContactSocialProfileCascade::class)->removePlatforms($removed);
            }

            return $updated;
        });
    }

    public function updateJournal(JournalSetting $setting, array $data): JournalSetting
    {
        /** @var JournalSetting $updated */
        $updated = $this->update($setting, $data, 'journal_setting.updated', 'journal_setting');

        return $updated;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function normalizePublicContent(array $data): array
    {
        foreach (PublicAppearance::SURFACE_PREFIXES as $surface) {
            $mode = $surface.'_mode';
            if (array_key_exists($mode, $data)) {
                $data[$mode] = PublicAppearance::normalizeMode($data[$mode], $mode);
            }
            foreach (['_color', '_gradient_start', '_gradient_end'] as $suffix) {
                $field = $surface.$suffix;
                if (array_key_exists($field, $data)) {
                    $data[$field] = PublicAppearance::normalizeColor($data[$field], $field);
                }
            }
            $angle = $surface.'_gradient_angle';
            if (array_key_exists($angle, $data)) {
                $data[$angle] = PublicAppearance::normalizeAngle($data[$angle], $angle);
            }
            foreach ([
                '_gradient_stops' => 'normalizeStops',
                '_gradient_repeating' => 'normalizeRepeating',
                '_gradient_repeat_span' => 'normalizeRepeatSpan',
                '_gradient_center_x' => 'normalizeCenter',
                '_gradient_center_y' => 'normalizeCenter',
                '_gradient_shape' => 'normalizeShape',
            ] as $suffix => $method) {
                $field = $surface.$suffix;
                if (array_key_exists($field, $data)) {
                    $data[$field] = PublicAppearance::$method($data[$field], $field);
                }
            }
        }
        if (array_key_exists('header_divider_mode', $data)) {
            $data['header_divider_mode'] = PublicAppearance::headerDivider($data['header_divider_mode']);
        }
        if (array_key_exists('header_name_color', $data)) {
            $data['header_name_color'] = PublicAppearance::normalizeColor($data['header_name_color'], 'header_name_color');
        }
        if (array_key_exists('header_name_size', $data)) {
            $data['header_name_size'] = PublicAppearance::normalizeTitleSize($data['header_name_size']);
        }
        if (array_key_exists('public_page_width', $data)) {
            $data['public_page_width'] = PublicAppearance::normalizePageWidth($data['public_page_width']);
        }
        if (array_key_exists('public_content_padding', $data)) {
            $data['public_content_padding'] = PublicAppearance::normalizeContentPadding($data['public_content_padding']);
        }

        return $data;
    }

    private function update(Model $record, array $data, string $action, string $entityType, array $expected = []): Model
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $data, $action, $entityType, $actor, $expected): Model {
            $class = $record::class;
            /** @var Model $fresh */
            $fresh = $class::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            foreach ($expected as $field => $value) {
                if ($this->comparisonValue($fresh->getAttribute($field)) !== $this->comparisonValue($value)) {
                    throw ValidationException::withMessages([$field => 'This setting changed since the editor was opened. Reopen the editor.']);
                }
            }
            $fresh->fill($data);

            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, $action, $entityType, (int) $fresh->getKey());
            }

            return $fresh;
        });
    }

    // JSONB object key order is immaterial; list order and scalar types remain significant.
    private function comparisonValue(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        $value = array_map($this->comparisonValue(...), $value);
        if (! array_is_list($value)) {
            ksort($value);
        }

        return $value;
    }
}
