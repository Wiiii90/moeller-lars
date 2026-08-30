<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminUndoService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
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
        $activityFeed = app(AdminActivityFeed::class);
        $feed = $activityFeed->page($area, $family, actor: $actor, days: $days, search: $search);
        $overview = $activityFeed->overview($area, $family, days: $days, search: $search);
        $publicationContext = $activityFeed->publicationContext();
        $hourly = $overview['hourly'];
        $clockMaximum = max(1, ...$hourly);
        $clockActivity = [];

        foreach ($hourly as $hour => $count) {
            $angle = (((int) $hour / 24) * 2 * pi()) - (pi() / 2);
            $innerRadius = 79;
            $barLength = $count > 0
                ? 10 + (25 * sqrt($count / $clockMaximum))
                : 4;
            $outerRadius = $innerRadius + $barLength;

            $clockActivity[] = [
                'hour' => (int) $hour,
                'count' => (int) $count,
                'x1' => 120 + ($innerRadius * cos($angle)),
                'y1' => 120 + ($innerRadius * sin($angle)),
                'x2' => 120 + ($outerRadius * cos($angle)),
                'y2' => 120 + ($outerRadius * sin($angle)),
            ];
        }

        $peakHour = null;
        if (array_sum($hourly) > 0) {
            $peakCount = max($hourly);
            $peakHour = (int) array_search($peakCount, $hourly, true);
        }

        $calendarStart = CarbonImmutable::now()->startOfDay()->subDays($days - 1);
        $calendarEnd = CarbonImmutable::now()->startOfDay();
        $calendarGridStart = $calendarStart->startOfWeek(CarbonInterface::MONDAY);
        $calendarGridEnd = $calendarEnd->endOfWeek(CarbonInterface::SUNDAY);
        $calendarMaximum = max(1, ...array_values($overview['daily'] ?: [0]));
        $calendarDays = [];

        for ($date = $calendarGridStart; $date->lte($calendarGridEnd); $date = $date->addDay()) {
            if ($date->lt($calendarStart) || $date->gt($calendarEnd)) {
                $calendarDays[] = null;

                continue;
            }

            $dateKey = $date->format('Y-m-d');
            $count = (int) ($overview['daily'][$dateKey] ?? 0);
            $level = $count === 0
                ? 0
                : min(4, max(1, (int) ceil(($count / $calendarMaximum) * 4)));

            $calendarDays[] = [
                'date' => $dateKey,
                'label' => $date->format('D, M j'),
                'count' => $count,
                'level' => $level,
            ];
        }

        $latestAt = $overview['latest_at'] !== null
            ? CarbonImmutable::parse((string) $overview['latest_at'])
            : null;

        return [
            ...$feed,
            'area' => $area,
            'family' => $family,
            'search' => $search,
            'period' => $period,
            'areaOptions' => AdminActionCatalog::areaOptions(),
            'familyOptions' => AdminActionCatalog::familyOptions(),
            'periodOptions' => $periodOptions,
            'selectedPeriodLabel' => $periodOptions[$period],
            'activityMetrics' => [
                'changes' => $overview['total'],
                'active_days' => $overview['active_days'],
                'areas' => $overview['areas'],
                'families' => $overview['families'],
                'actors' => $overview['actors'],
                'latest_when' => $latestAt?->diffForHumans() ?? '—',
                'latest_at' => $latestAt?->format('Y-m-d H:i'),
            ],
            'clockActivity' => $clockActivity,
            'clockTotal' => array_sum($hourly),
            'clockPeakHour' => $peakHour,
            'clockPeakCount' => $peakHour !== null ? (int) $hourly[$peakHour] : 0,
            'calendarLabel' => $calendarStart->format('M j').' – '.$calendarEnd->format('M j, Y'),
            'calendarDays' => $calendarDays,
            'calendarActiveDays' => $overview['active_days'],
            'calendarMaximum' => $calendarMaximum,
            'publicationContext' => $publicationContext,
        ];
    }
}
