<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminAutoPublishPreferences;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Support\Controls\AdminControl;
use App\Models\PublicContentSetting;
use App\Models\User;
use Closure;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

final class PagesSettingsDialog
{
    public function __construct(
        private readonly AdminSettingsService $settings,
        private readonly SiteSectionOrderService $order,
        private readonly HomeRoutingDialog $homeRouting,
    ) {}

    /** @return array{navigation_nesting_enabled:bool,skip_home:bool,skip_target_section_id:?int,pages_auto_publish:bool,pages_delete_without_confirmation:bool} */
    public function fill(): array
    {
        return [
            'navigation_nesting_enabled' => PublicContentSetting::navigationNestingEnabled(),
            'pages_delete_without_confirmation' => (bool) (Filament::auth()->user()?->getAttribute('pages_delete_without_confirmation') ?? false),
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
                    'pages_delete_without_confirmation' => $get('pages_delete_without_confirmation'),
                    'skip_home' => $get('skip_home'),
                    'skip_target_section_id' => $get('skip_target_section_id'),
                    'pages_auto_publish' => $get('pages_auto_publish'),
                ]);
            };

        $nesting = AdminControl::toggle('navigation_nesting_enabled', 'Allow nested pages')->live();
        if ($onToggleUpdated !== null) {
            $nesting->afterStateUpdated($onToggleUpdated);
        }

        $deleteWithoutConfirmation = AdminControl::toggle('pages_delete_without_confirmation', 'Delete without confirmation')->live();
        if ($onToggleUpdated !== null) {
            $deleteWithoutConfirmation->afterStateUpdated($onToggleUpdated);
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
                    $deleteWithoutConfirmation,
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
            ]);

            $user = Filament::auth()->user();
            $deleteChanged = false;
            if ($user instanceof User) {
                $persistedUser = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
                $deleteWithoutConfirmation = (bool) ($data['pages_delete_without_confirmation']
                    ?? $persistedUser->getAttribute('pages_delete_without_confirmation'));
                if ((bool) $persistedUser->getAttribute('pages_delete_without_confirmation') !== $deleteWithoutConfirmation) {
                    $persistedUser->forceFill(['pages_delete_without_confirmation' => $deleteWithoutConfirmation])->save();
                    $user->setAttribute('pages_delete_without_confirmation', $deleteWithoutConfirmation);
                    $deleteChanged = true;
                }
            }

            $publishChanged = app(AdminAutoPublishPreferences::class)->update(
                AdminAutoPublishPreferences::PAGES,
                (bool) ($data['pages_auto_publish'] ?? app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::PAGES)),
            );

            return $updated->wasChanged('navigation_nesting_enabled')
                || $changed || $publishChanged || $deleteChanged;
        });
    }
}
