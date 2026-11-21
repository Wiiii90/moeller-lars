<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminAutoPublishPreferences;
use App\Filament\Support\Controls\AdminControl;
use App\Models\User;
use Closure;
use Filament\Facades\Filament;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\DB;

final class PagesSettingsDialog
{
    /** @return array{pages_auto_publish:bool,pages_delete_without_confirmation:bool} */
    public function fill(): array
    {
        return [
            'pages_delete_without_confirmation' => (bool) (Filament::auth()->user()?->getAttribute('pages_delete_without_confirmation') ?? false),
            'pages_auto_publish' => app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::PAGES),
        ];
    }

    /** @return list<mixed> */
    public function schema(?Closure $persistOnToggle = null): array
    {
        // Persist through the existing dialog action after each completed
        // toggle change; no alternative settings mutation path is introduced.
        $onToggleUpdated = $persistOnToggle === null
            ? null
            : static function (Get $get) use ($persistOnToggle): void {
                $persistOnToggle([
                    'pages_delete_without_confirmation' => $get('pages_delete_without_confirmation'),
                    'pages_auto_publish' => $get('pages_auto_publish'),
                ]);
            };

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
                    $deleteWithoutConfirmation,
                    $autoPublish,
                ]),
        ];
    }

    /** @param array<string, mixed> $data */
    public function save(array $data): bool
    {
        return DB::transaction(function () use ($data): bool {
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

            return $publishChanged || $deleteChanged;
        });
    }
}
