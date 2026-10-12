<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\Controls\AdminControl;
use App\Models\PublicContentSetting;
use Illuminate\Support\Facades\DB;

final class PagesSettingsDialog
{
    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly SiteSectionOrderService $order,
        private readonly HomeRoutingDialog $homeRouting,
    ) {}

    /** @return array{navigation_nesting_enabled:bool,skip_home:bool,skip_target_section_id:?int} */
    public function fill(): array
    {
        return [
            'navigation_nesting_enabled' => PublicContentSetting::navigationNestingEnabled(),
            ...$this->homeRouting->fill(),
        ];
    }

    /** @return list<mixed> */
    public function schema(): array
    {
        return [
            AdminControl::toggle('navigation_nesting_enabled', 'Allow nested pages')
                ->live(),
            ...$this->homeRouting->schema(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): bool
    {
        $settings = PublicContentSetting::general();
        $wasEnabled = PublicContentSetting::navigationNestingEnabled();
        $enabled = (bool) ($data['navigation_nesting_enabled'] ?? true);

        return DB::transaction(function () use ($data, $settings, $wasEnabled, $enabled): bool {
            $changed = $this->homeRouting->save($data);

            if ($wasEnabled && ! $enabled) {
                $changed = $this->order->flattenHierarchy() > 0 || $changed;
            }

            $updated = $this->settings->updatePublicContent($settings, [
                'navigation_nesting_enabled' => $enabled,
            ]);

            return $updated->wasChanged('navigation_nesting_enabled') || $changed;
        });
    }
}
