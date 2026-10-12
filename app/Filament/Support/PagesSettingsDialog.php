<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\Controls\AdminControl;
use App\Models\PublicContentSetting;
use Closure;
use Filament\Schemas\Components\Utilities\Get;
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
    public function schema(?Closure $persistOnToggle = null): array
    {
        // Filament's live toggles update their own state, but the shared
        // modal wire:change handler does not reliably submit that update.
        // Use the same mutation owner as the native dialog action.
        $onToggleUpdated = $persistOnToggle === null
            ? null
            : static function (Get $get) use ($persistOnToggle): void {
                $persistOnToggle([
                    'navigation_nesting_enabled' => $get('navigation_nesting_enabled'),
                    'skip_home' => $get('skip_home'),
                    'skip_target_section_id' => $get('skip_target_section_id'),
                ]);
            };

        $nesting = AdminControl::toggle('navigation_nesting_enabled', 'Allow nested pages')->live();
        if ($onToggleUpdated !== null) {
            $nesting->afterStateUpdated($onToggleUpdated);
        }

        return [
            $nesting,
            ...$this->homeRouting->schema($onToggleUpdated),
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
