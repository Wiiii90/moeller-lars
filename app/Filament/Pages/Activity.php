<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminUndoService;
use App\Filament\Support\AdminActivityFeed;
use App\Filament\Support\AdminIcon;
use App\Models\AuditEvent;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class Activity extends Page
{
    protected static string|BackedEnum|null $navigationIcon = AdminIcon::Activity;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $navigationLabel = 'Activity';

    protected static ?string $title = 'Activity';

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

        $today = CarbonImmutable::today();
        $currentYear = (int) $today->format('Y');
        $requestedYear = request()->query('calendar_year');
        $calendarYear = is_numeric($requestedYear)
            ? max(2000, min($currentYear, (int) $requestedYear))
            : $currentYear;
        $calendarStart = CarbonImmutable::create($calendarYear, 1, 1)->startOfDay();
        $calendarEnd = CarbonImmutable::create($calendarYear, 12, 31)->endOfDay();
        $calendarQuery = $this->calendarQuery($area, $family, $search, $calendarStart, $calendarEnd);
        $driver = $calendarQuery->getModel()->getConnection()->getDriverName();
        $dateExpression = match ($driver) {
            'pgsql' => 'occurred_at::date',
            default => 'DATE(occurred_at)',
        };
        $hourExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%H', occurred_at) AS INTEGER)",
            'mysql', 'mariadb' => 'HOUR(occurred_at)',
            default => 'EXTRACT(HOUR FROM occurred_at)::int',
        };

        $calendarDaily = [];
        $dayRows = (clone $calendarQuery)
            ->toBase()
            ->selectRaw($dateExpression.' AS bucket, COUNT(*) AS aggregate')
            ->groupByRaw($dateExpression)
            ->orderBy('bucket')
            ->get();
        foreach ($dayRows as $row) {
            $calendarDaily[(string) $row->bucket] = (int) $row->aggregate;
        }

        $selectedDate = $this->requestedCalendarDate($calendarYear, $today);
        if ($selectedDate === null) {
            if ($calendarYear === $currentYear) {
                $selectedDate = $today;
            } elseif ($calendarDaily !== []) {
                $selectedDate = CarbonImmutable::parse((string) array_key_last($calendarDaily))->startOfDay();
            } else {
                $selectedDate = $calendarStart;
            }
        }

        $selectedDayQuery = (clone $calendarQuery)->whereBetween('occurred_at', [
            $selectedDate->startOfDay(),
            $selectedDate->endOfDay(),
        ]);
        $hourly = array_fill(0, 24, 0);
        $hourRows = (clone $selectedDayQuery)
            ->toBase()
            ->selectRaw($hourExpression.' AS bucket, COUNT(*) AS aggregate')
            ->groupByRaw($hourExpression)
            ->orderBy('bucket')
            ->get();
        foreach ($hourRows as $row) {
            $hour = (int) $row->bucket;
            if ($hour >= 0 && $hour <= 23) {
                $hourly[$hour] = (int) $row->aggregate;
            }
        }

        $clockActivity = [];
        foreach ($hourly as $hour => $count) {
            $clockActivity[] = [
                'hour' => (int) $hour,
                'count' => (int) $count,
            ];
        }
        $clockPeakCount = max($hourly);
        $clockPeakHour = $clockPeakCount > 0 ? (int) array_search($clockPeakCount, $hourly, true) : null;
        $selectedLatestAt = (clone $selectedDayQuery)->max('occurred_at');
        $clockIsLive = $selectedDate->isSameDay($today);
        $clockAt = $clockIsLive
            ? CarbonImmutable::now()
            : ($selectedLatestAt !== null
                ? CarbonImmutable::parse((string) $selectedLatestAt)
                : $selectedDate->startOfDay());

        $calendarMaximum = max(1, ...array_values($calendarDaily ?: [0]));
        $calendarGridStart = $calendarStart->startOfWeek(CarbonInterface::MONDAY);
        $calendarGridEnd = $calendarEnd->endOfWeek(CarbonInterface::SUNDAY);
        $calendarDays = [];

        for ($date = $calendarGridStart; $date->lte($calendarGridEnd); $date = $date->addDay()) {
            if ((int) $date->format('Y') !== $calendarYear) {
                $calendarDays[] = null;

                continue;
            }

            $dateKey = $date->format('Y-m-d');
            $count = (int) ($calendarDaily[$dateKey] ?? 0);
            $level = $count === 0
                ? 0
                : min(4, max(1, (int) ceil(($count / $calendarMaximum) * 4)));

            $calendarDays[] = [
                'date' => $dateKey,
                'label' => $date->format('D, M j'),
                'count' => $count,
                'level' => $level,
                'selected' => $date->isSameDay($selectedDate),
                'today' => $date->isSameDay($today),
                'future' => $date->gt($today),
            ];
        }

        $calendarWeeks = array_chunk($calendarDays, 7);
        $calendarWeeksPerBand = max(1, (int) ceil(count($calendarWeeks) / 2));
        $calendarBands = array_chunk($calendarWeeks, $calendarWeeksPerBand);

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
            'clockPeakHour' => $clockPeakHour,
            'clockPeakCount' => $clockPeakCount,
            'clockAtIso' => $clockAt->toIso8601String(),
            'clockIsLive' => $clockIsLive,
            'clockHasActivity' => $selectedLatestAt !== null || $clockIsLive,
            'calendarYear' => $calendarYear,
            'calendarPreviousYear' => $calendarYear > 2000 ? $calendarYear - 1 : null,
            'calendarNextYear' => $calendarYear < $currentYear ? $calendarYear + 1 : null,
            'calendarDays' => $calendarDays,
            'calendarBands' => $calendarBands,
            'calendarActiveDays' => count(array_filter($calendarDaily, static fn (int $count): bool => $count > 0)),
            'calendarMaximum' => $calendarMaximum,
            'selectedCalendarDate' => $selectedDate->format('Y-m-d'),
            'selectedCalendarLabel' => $selectedDate->format('M j, Y'),
            'selectedClockLabel' => $clockIsLive
                ? 'Live local time'
                : ($selectedLatestAt !== null ? 'Latest activity' : 'No activity'),
            'publicationContext' => $publicationContext,
        ];
    }

    private function requestedCalendarDate(int $calendarYear, CarbonImmutable $today): ?CarbonImmutable
    {
        $requested = request()->query('calendar_date');
        if (! is_string($requested) || preg_match('/^\d{4}-\d{2}-\d{2}$/', $requested) !== 1) {
            return null;
        }

        try {
            $date = CarbonImmutable::createFromFormat('Y-m-d', $requested)->startOfDay();
        } catch (\Throwable) {
            return null;
        }

        if ($date->format('Y-m-d') !== $requested || (int) $date->format('Y') !== $calendarYear || $date->gt($today)) {
            return null;
        }

        return $date;
    }

    private function calendarQuery(
        ?string $area,
        ?string $family,
        string $search,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Builder {
        $query = AuditEvent::query()->whereBetween('occurred_at', [$start, $end]);
        $areaKeys = $area !== null && $area !== '' ? AdminActionCatalog::keysForArea($area) : null;
        $familyKeys = $family !== null && $family !== '' ? AdminActionCatalog::keysForFamily($family) : null;
        $actionKeys = match (true) {
            $areaKeys === null && $familyKeys === null => null,
            $areaKeys === null => $familyKeys,
            $familyKeys === null => $areaKeys,
            default => array_values(array_intersect($areaKeys, $familyKeys)),
        };

        if ($actionKeys !== null) {
            $query->whereIn('action', $actionKeys);
        }

        $search = mb_strtolower(trim($search));
        if ($search === '') {
            return $query;
        }

        $searchActionKeys = array_values(array_filter(
            AdminActionCatalog::keys(),
            static function (string $key) use ($search): bool {
                $definition = AdminActionCatalog::definition($key);

                foreach ([$definition['label'], $definition['area'], $definition['family']] as $value) {
                    if (mb_stripos($value, $search) !== false) {
                        return true;
                    }
                }

                return false;
            },
        ));

        $query->where(function (Builder $query) use ($searchActionKeys, $search): void {
            if ($searchActionKeys !== []) {
                $query->whereIn('action', $searchActionKeys)
                    ->orWhereHas('adminUser', static function (Builder $adminUserQuery) use ($search): void {
                        $adminUserQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
                    });

                return;
            }

            $query->whereHas('adminUser', static function (Builder $adminUserQuery) use ($search): void {
                $adminUserQuery->whereRaw('LOWER(name) LIKE ?', ['%'.$search.'%']);
            });
        });

        return $query;
    }
}
