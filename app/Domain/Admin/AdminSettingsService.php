<?php

namespace App\Domain\Admin;

use App\Domain\Content\SiteSectionSyncService;
use App\Models\BlogSetting;
use App\Models\PublicContentSetting;
use Illuminate\Support\Facades\DB;

final class AdminSettingsService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SiteSectionSyncService $siteSections,
    ) {}

    public function updatePublicContent(PublicContentSetting $setting, array $data): PublicContentSetting
    {
        $updated = $this->update($setting, $data, 'public_content_setting.updated', 'public_content_setting');

        /** @var PublicContentSetting $updated */
        return $updated;
    }

    public function updateBlog(BlogSetting $setting, array $data): BlogSetting
    {
        $updated = $this->update($setting, $data, 'blog_setting.updated', 'blog_setting');

        /** @var BlogSetting $updated */
        return $updated;
    }

    private function update(
        PublicContentSetting|BlogSetting $record,
        array $data,
        string $action,
        string $entityType,
    ): PublicContentSetting|BlogSetting {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($record, $data, $action, $entityType, $actor): PublicContentSetting|BlogSetting {
            $class = $record::class;
            /** @var PublicContentSetting|BlogSetting $fresh */
            $fresh = $class::query()->whereKey($record->getKey())->lockForUpdate()->firstOrFail();
            $fresh->fill($data);

            if ($fresh->isDirty()) {
                $fresh->save();

                if ($fresh instanceof PublicContentSetting) {
                    $this->siteSections->syncPublicContent($fresh);
                } else {
                    $this->siteSections->syncBlog($fresh);
                }

                $this->audit->record($actor, $action, $entityType, (int) $fresh->getKey());
            }

            return $fresh;
        });
    }
}
