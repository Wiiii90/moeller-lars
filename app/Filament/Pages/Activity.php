<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminUndoService;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class Activity extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClock;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $navigationLabel = 'Activity';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.activity';

    public function undo(int $receiptId): void
    {
        try {
            $result = app(AdminUndoService::class)->undo($receiptId);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['undo'][0] ?? 'This change can no longer be undone safely.';

            Notification::make()
                ->warning()
                ->title('Undo unavailable')
                ->body($message)
                ->send();

            return;
        }

        Notification::make()
            ->success()
            ->title('Change undone')
            ->body($result['inverse'].' was applied as a new editorial action.')
            ->send();
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $area = request()->query('area');
        $family = request()->query('family');
        $period = request()->query('period');
        $area = is_string($area) && array_key_exists($area, AdminActionCatalog::areaOptions()) ? $area : null;
        $family = is_string($family) && array_key_exists($family, AdminActionCatalog::familyOptions()) ? $family : null;
        $periodOptions = [
            '7d' => '7 days',
            '30d' => '30 days',
            '180d' => '180 days',
        ];
        $period = is_string($period) && array_key_exists($period, $periodOptions) ? $period : '180d';
        $days = match ($period) {
            '7d' => 7,
            '30d' => 30,
            default => AdminActivityFeed::ACTIVITY_WINDOW_DAYS,
        };
        $actor = app(AdminAuditService::class)->requireActor();
        $feed = app(AdminActivityFeed::class)->page($area, $family, actor: $actor, days: $days);

        return [
            ...$feed,
            'area' => $area,
            'family' => $family,
            'period' => $period,
            'areaOptions' => AdminActionCatalog::areaOptions(),
            'familyOptions' => AdminActionCatalog::familyOptions(),
            'periodOptions' => $periodOptions,
        ];
    }
}
