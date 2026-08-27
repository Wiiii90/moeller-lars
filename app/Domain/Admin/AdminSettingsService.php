<?php

namespace App\Domain\Admin;

use App\Domain\Content\PublicAppearance;
use App\Models\JournalSetting;
use App\Models\PublicContentSetting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

final class AdminSettingsService
{
    public function __construct(private readonly AdminAuditService $audit) {}

    public function updatePublicContent(PublicContentSetting $setting, array $data): PublicContentSetting
    {
        /** @var PublicContentSetting $updated */
        $updated = $this->update(
            $setting,
            $this->normalizePublicContent($data),
            'public_content_setting.updated',
            'public_content_setting',
        );

        return $updated;
    }

    public function updateJournal(JournalSetting $setting, array $data): JournalSetting
    {
        /** @var JournalSetting $updated */
        $updated = $this->update($setting, $data, 'journal_setting.updated', 'journal_setting');

        return $updated;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizePublicContent(array $data): array
    {
        if (array_key_exists('background_mode', $data)) {
            $data['background_mode'] = PublicAppearance::normalizeMode($data['background_mode']);
        }
        if (array_key_exists('background_color', $data)) {
            $data['background_color'] = PublicAppearance::normalizeColor($data['background_color'], 'background_color');
        }
        if (array_key_exists('background_gradient_start', $data)) {
            $data['background_gradient_start'] = PublicAppearance::normalizeColor($data['background_gradient_start'], 'background_gradient_start');
        }
        if (array_key_exists('background_gradient_end', $data)) {
            $data['background_gradient_end'] = PublicAppearance::normalizeColor($data['background_gradient_end'], 'background_gradient_end');
        }
        if (array_key_exists('background_gradient_angle', $data)) {
            $data['background_gradient_angle'] = PublicAppearance::normalizeAngle($data['background_gradient_angle']);
        }

        return $data;
    }

    private function update(Model $record, array $data, string $action, string $entityType): Model
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $data, $action, $entityType, $actor): Model {
            $class = $record::class;
            /** @var Model $fresh */
            $fresh = $class::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $fresh->fill($data);

            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, $action, $entityType, (int) $fresh->getKey());
            }

            return $fresh;
        });
    }
}
