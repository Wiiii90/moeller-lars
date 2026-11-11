<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminAutoPublishPreferences;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\Controls\AdminControl;
use Illuminate\Support\HtmlString;
use App\Models\PublicContentSetting;
use Closure;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;

final class PagesSettingsDialog
{
    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly SiteSectionOrderService $order,
        private readonly HomeRoutingDialog $homeRouting,
    ) {}

    /** @return array{navigation_nesting_enabled:bool,navigation_hover_preview:bool,skip_home:bool,skip_target_section_id:?int,pages_auto_publish:bool} */
    public function fill(): array
    {
        return [
            'navigation_nesting_enabled' => PublicContentSetting::navigationNestingEnabled(),
            'navigation_hover_preview' => (bool) PublicContentSetting::general()->getAttribute('navigation_hover_preview'),
            ...$this->homeRouting->fill(),
            'pages_auto_publish' => app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::PAGES),
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
                    'navigation_hover_preview' => $get('navigation_hover_preview'),
                    'skip_home' => $get('skip_home'),
                    'skip_target_section_id' => $get('skip_target_section_id'),
                    'pages_auto_publish' => $get('pages_auto_publish'),
                ]);
            };

        $nesting = AdminControl::toggle('navigation_nesting_enabled', 'Allow nested pages')->live();
        if ($onToggleUpdated !== null) {
            $nesting->afterStateUpdated($onToggleUpdated);
        }

        $hoverPreview = AdminControl::toggle('navigation_hover_preview')
            ->label(new HtmlString(
                '<span class="admin-form-label-with-help">'.e(__('Preview child pages on hover'))
                .AdminHelp::make(
                    __('About child page hover preview'),
                    __('Mouse hover temporarily reveals child links. Click the parent to open its submenu; touch navigation is unchanged.'),
                )->toHtml()
                .'</span>',
            ))
            ->live();
        if ($onToggleUpdated !== null) {
            $hoverPreview->afterStateUpdated($onToggleUpdated);
        }

        $autoPublish = AdminControl::toggle('pages_auto_publish', 'Auto-publish new pages')->live();
        if ($onToggleUpdated !== null) {
            $autoPublish->afterStateUpdated($onToggleUpdated);
        }

        return [
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    $nesting,
                    $hoverPreview,
                    Group::make($this->homeRouting->schema($onToggleUpdated)),
                    $autoPublish,
                ]),
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
                'navigation_hover_preview' => (bool) ($data['navigation_hover_preview'] ?? $settings->getAttribute('navigation_hover_preview')),
            ]);

            $publishChanged = app(AdminAutoPublishPreferences::class)->update(
                AdminAutoPublishPreferences::PAGES,
                (bool) ($data['pages_auto_publish'] ?? app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::PAGES)),
            );

            return $updated->wasChanged(['navigation_nesting_enabled', 'navigation_hover_preview'])
                || $changed || $publishChanged;
        });
    }
}
