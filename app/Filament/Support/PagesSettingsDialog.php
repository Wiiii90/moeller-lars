<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SiteSectionOrderService;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
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
        $childCount = SiteSection::query()->whereNotNull('parent_id')->count();

        return [
            Toggle::make('navigation_nesting_enabled')
                ->label('Allow nested pages')
                ->helperText('Allow one child level below top-level pages in the site navigation.')
                ->live(),
            Placeholder::make('navigation_nesting_warning')
                ->label('Flatten nested pages')
                ->content("Turning nesting off moves {$childCount} child ".($childCount === 1 ? 'page' : 'pages')." to the top level in canonical order. Example: 4, 4.1, 4.2, 5 becomes 4, 5, 6, 7. Re-enabling nesting later does not restore previous parents automatically.")
                ->visible(fn (callable $get): bool => $childCount > 0 && ! (bool) $get('navigation_nesting_enabled'))
                ->columnSpanFull(),
            ...$this->homeRouting->schema(),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): void
    {
        $settings = PublicContentSetting::general();
        $wasEnabled = PublicContentSetting::navigationNestingEnabled();
        $enabled = (bool) ($data['navigation_nesting_enabled'] ?? true);

        DB::transaction(function () use ($data, $settings, $wasEnabled, $enabled): void {
            if ($wasEnabled && ! $enabled) {
                $this->order->flattenHierarchy();
            }

            $this->settings->updatePublicContent($settings, [
                'navigation_nesting_enabled' => $enabled,
            ]);
            $this->homeRouting->save($data);
        });
    }
}
