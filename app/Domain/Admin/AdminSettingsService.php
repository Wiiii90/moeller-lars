<?php

namespace App\Domain\Admin;

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
        $updated = $this->update($setting, $data, 'public_content_setting.updated', 'public_content_setting');

        return $updated;
    }

    public function updateJournal(JournalSetting $setting, array $data): JournalSetting
    {
        /** @var JournalSetting $updated */
        $updated = $this->update($setting, $data, 'journal_setting.updated', 'journal_setting');

        return $updated;
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
