<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminUndoService;
use BackedEnum;
use Carbon\CarbonImmutable;
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
        $search = request()->query('search');
        $area = is_string($area) && array_key_exists($area, AdminActionCatalog::areaOptions()) ? $area : null;
        $family = is_string($family) && array_key_exists($family, AdminActionCatalog::familyOptions()) ? $family : null;
        $search = is_string($search) ? trim($search) : '';
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
        $feed = app(AdminActivityFeed::class)->page($area, $family, actor: $actor, days: $days, search: $search);
        $activity = $feed['activity'];
        $latest = $activity[0] ?? null;
        $calendarMonth = $latest !== null
            ? CarbonImmutable::parse((string) $latest['timestamp'])
            : CarbonImmutable::now();
        $calendarMonth = $calendarMonth->startOfMonth();
        $activityByDate = collect($activity)
            ->countBy(static fn (array $event): string => substr((string) $event['timestamp'], 0, 10))
            ->all();
        $calendarDays = [];

        for ($offset = 1; $offset < $calendarMonth->dayOfWeekIso; $offset++) {
            $calendarDays[] = null;
        }

        for ($day = 1; $day <= $calendarMonth->daysInMonth; $day++) {
            $date = $calendarMonth->setDay($day);
            $dateKey = $date->format('Y-m-d');
            $calendarDays[] = [
                'day' => $day,
                'date' => $dateKey,
                'count' => (int) ($activityByDate[$dateKey] ?? 0),
            ];
        }

        while (count($calendarDays) % 7 !== 0) {
            $calendarDays[] = null;
        }

        $clockActivity = array_map(static function (array $event): array {
            $timestamp = (string) $event['timestamp'];
            $hour = (int) substr($timestamp, 11, 2);
            $minute = (int) substr($timestamp, 14, 2);

            $angle = (($hour * 60) + $minute) / (24 * 60) * 2 * pi();

            return [
                ...$event,
                'clock_x' => 50 + (42 * sin($angle)),
                'clock_y' => 50 - (42 * cos($angle)),
            ];
        }, $activity);

        return [
            ...$feed,
            'area' => $area,
            'family' => $family,
            'search' => $search,
            'period' => $period,
            'areaOptions' => AdminActionCatalog::areaOptions(),
            'familyOptions' => AdminActionCatalog::familyOptions(),
            'periodOptions' => $periodOptions,
            'activityMetrics' => [
                'changes' => $feed['paginator']->total(),
                'on_page' => count($activity),
                'areas' => collect($activity)->pluck('area')->unique()->count(),
                'families' => collect($activity)->pluck('family')->unique()->count(),
                'undoable' => collect($activity)->filter(static fn (array $event): bool => $event['undo'] !== null)->count(),
                'latest' => $latest,
            ],
            'clockActivity' => $clockActivity,
            'calendarLabel' => $calendarMonth->format('F Y'),
            'calendarDays' => $calendarDays,
        ];
    }
}
