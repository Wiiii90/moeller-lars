<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminUndoService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Filament\Support\AdminActivityFeed;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminPublicationHistory;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;
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

            app(AdminNotifier::class)->toast(
                title: 'Undo unavailable',
                body: $message,
                status: 'warning',
            );

            return;
        }

        app(AdminNotifier::class)->toast(
            title: 'Change undone',
            body: $result['inverse'].' was applied as a new editorial action.',
            status: 'success',
        );
    }

    public function resetStagedChanges(): void
    {
        $actor = app(AdminAuditService::class)->requireActor();

        try {
            $changed = app(PublicationVersionService::class)->resetStagedChanges($actor);
        } catch (ValidationException $exception) {
            $this->publicationWarning($exception, 'Staged changes could not be reset.');

            return;
        }

        app(AdminNotifier::class)->toast(
            title: $changed > 0 ? 'Staged changes reset' : 'Nothing staged',
            body: $changed > 0
                ? number_format($changed).' working row'.($changed === 1 ? '' : 's').' restored from the current live version.'
                : 'The working state already matches the current live version.',
            status: $changed > 0 ? 'success' : 'info',
        );
    }

    public function restoreVersion(int $checkpointId): void
    {
        /** @var PublicationCheckpoint|null $checkpoint */
        $checkpoint = PublicationCheckpoint::query()->find($checkpointId);
        abort_unless($checkpoint instanceof PublicationCheckpoint, 404);

        try {
            app(PublicationVersionService::class)->stageVersion(
                $checkpoint,
                app(AdminAuditService::class)->requireActor(),
            );
        } catch (ValidationException $exception) {
            $this->publicationWarning($exception, 'This version cannot be restored safely.');

            return;
        }

        $staged = app(PublicationService::class)->pendingSummary()['total'];
        app(AdminNotifier::class)->toast(
            title: 'Version restored to working state',
            body: $checkpoint->shortHash().' is now staged with '.number_format($staged).' pending change'.($staged === 1 ? '' : 's').'.',
            status: 'success',
        );
    }

    public function revertCurrentCommit(): void
    {
        try {
            $result = app(PublicationVersionService::class)->stageRevertOfCurrent(
                app(AdminAuditService::class)->requireActor(),
            );
        } catch (ValidationException $exception) {
            $this->publicationWarning($exception, 'The current commit cannot be reverted safely.');

            return;
        }

        $staged = app(PublicationService::class)->pendingSummary()['total'];
        app(AdminNotifier::class)->toast(
            title: 'Commit revert staged',
            body: $result['reverted']->shortHash().' will be reversed by publishing the parent state '.$result['checkpoint']->shortHash().'. '.number_format($staged).' change'.($staged === 1 ? '' : 's').' staged.',
            status: 'success',
        );
    }

    public function openActivityDetails(int $eventId): void
    {
        abort_unless($this->activityEvent($eventId) !== null, 404);

        $this->mountAction('activityDetails', ['id' => $eventId]);
    }

    public function openPublicationReview(): void
    {
        $this->mountAction('publicationReview');
    }

    public function openCommitDetails(int $checkpointId): void
    {
        abort_unless($this->commitDetails(['id' => $checkpointId]) !== null, 404);

        $this->mountAction('commitDetails', ['id' => $checkpointId]);
    }

    public function activityDetailsAction(): Action
    {
        return Action::make('activityDetails')
            ->label('Details')
            ->modalHeading(fn (array $arguments): string => (string) ($this->activityDetails($arguments)['action'] ?? 'Activity details'))
            ->modalContent(fn (array $arguments): View => view(
                'filament.pages.partials.activity-details-dialog',
                ['event' => $this->activityDetails($arguments)],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->extraModalFooterActions(fn (array $arguments): array => $this->activityDetailsHeaderActions($arguments))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--default admin-dialog--header-actions',
            ]);
    }

    public function publicationReviewAction(): Action
    {
        return Action::make('publicationReview')
            ->label('Review changes')
            ->modalHeading('Review staged changes')
            ->modalContent(fn (): View => view(
                'filament.pages.partials.activity-publication-review-dialog',
                ['publication' => $this->publicationReview()],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--default admin-dialog--header-actions',
            ]);
    }

    public function commitDetailsAction(): Action
    {
        return Action::make('commitDetails')
            ->label('Commit details')
            ->modalHeading(fn (array $arguments): string => 'Commit '.($this->commitDetails($arguments)['short_hash'] ?? ''))
            ->modalContent(fn (array $arguments): View => view(
                'filament.pages.partials.activity-commit-details-dialog',
                ['commit' => $this->commitDetails($arguments)],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--default admin-dialog--header-actions',
            ]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $viewMode = request()->query('view') === 'commits' ? 'commits' : 'activity';
        if ($viewMode === 'commits') {
            return $this->commitViewData();
        }

        $area = request()->query('area');
        $family = request()->query('family');
        $search = request()->query('search');
        $area = is_string($area) && array_key_exists($area, AdminActionCatalog::areaOptions()) ? $area : null;
        $family = is_string($family) && array_key_exists($family, AdminActionCatalog::familyOptions()) ? $family : null;
        $search = is_string($search) ? trim($search) : '';

        $today = CarbonImmutable::today();
        $currentYear = (int) $today->format('Y');
        $activeDate = $this->requestedActivityDate($today);
        $activeHour = $activeDate !== null ? $this->requestedActivityHour() : null;
        $requestedYear = request()->query('calendar_year');
        $calendarYear = is_numeric($requestedYear)
            ? max(2000, min($currentYear, (int) $requestedYear))
            : ($activeDate?->year ?? $currentYear);
        if ($activeDate !== null && $activeDate->year !== $calendarYear) {
            $calendarYear = $activeDate->year;
        }

        $actor = app(AdminAuditService::class)->requireActor();
        $activityFeed = app(AdminActivityFeed::class);
        $activeDateValue = $activeDate?->format('Y-m-d');
        $feed = $activityFeed->page(
            $area,
            $family,
            actor: $actor,
            search: $search,
            date: $activeDateValue,
            hour: $activeHour,
        );
        $overview = $activityFeed->overview(
            $area,
            $family,
            search: $search,
            date: $activeDateValue,
            hour: $activeHour,
        );
        $publicationContext = $this->decoratePublicationContext($activityFeed->publicationContext());

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

        $selectedDate = $activeDate;
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
                'filtered' => $activeDate?->isSameDay($date) ?? false,
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
            'viewMode' => 'activity',
            'commits' => [],
            'commitPaginator' => null,
            'area' => $area,
            'family' => $family,
            'search' => $search,
            'areaOptions' => AdminActionCatalog::areaOptions(),
            'familyOptions' => AdminActionCatalog::familyOptions(),
            'activeDate' => $activeDateValue,
            'activeHour' => $activeHour,
            'todayDate' => $today->format('Y-m-d'),
            'activitySourceExists' => $activityFeed->exists(),
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

    /** @return array<string,mixed> */
    private function commitViewData(): array
    {
        $activityFeed = app(AdminActivityFeed::class);
        $publicationContext = $this->decoratePublicationContext($activityFeed->publicationContext());
        $history = app(AdminPublicationHistory::class)->page();

        return [
            'viewMode' => 'commits',
            'publicationContext' => $publicationContext,
            'commits' => $history['commits'],
            'commitPaginator' => $history['paginator'],
        ];
    }

    /** @param array<string,mixed> $context
     * @return array<string,mixed>
     */
    private function decoratePublicationContext(array $context): array
    {
        $liveCheckpoint = app(PublicationVersionService::class)->currentLiveCheckpoint();
        if (is_array($context['latest'] ?? null) && $liveCheckpoint instanceof PublicationCheckpoint) {
            $context['latest']['hash'] = (string) ($liveCheckpoint->getAttribute('hash') ?? '');
            $context['latest']['short_hash'] = $liveCheckpoint->shortHash();
            $context['latest']['snapshot_available'] = (bool) $liveCheckpoint->getAttribute('snapshot_available');
        }

        return $context;
    }

    private function requestedActivityDate(CarbonImmutable $today): ?CarbonImmutable
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

        if ($date->format('Y-m-d') !== $requested || $date->year < 2000 || $date->gt($today)) {
            return null;
        }

        return $date;
    }

    private function requestedActivityHour(): ?int
    {
        $requested = request()->query('hour');
        if (! is_numeric($requested)) {
            return null;
        }

        $hour = (int) $requested;

        return $hour >= 0 && $hour <= 23 ? $hour : null;
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function activityDetails(array $arguments): array
    {
        $eventId = is_numeric($arguments['id'] ?? null) ? (int) $arguments['id'] : 0;
        $event = $this->activityEvent($eventId);
        abort_unless(is_array($event), 404);

        return $event;
    }

    /** @return array<string, mixed>|null */
    private function activityEvent(int $eventId): ?array
    {
        if ($eventId <= 0) {
            return null;
        }

        return app(AdminActivityFeed::class)->event(
            $eventId,
            app(AdminAuditService::class)->requireActor(),
        );
    }

    /** @return array<string,mixed>|null */
    private function commitDetails(array $arguments): ?array
    {
        $checkpointId = is_numeric($arguments['id'] ?? null) ? (int) $arguments['id'] : 0;

        return $checkpointId > 0
            ? app(AdminPublicationHistory::class)->checkpoint($checkpointId)
            : null;
    }

    /** @return array{summary:array{total:int,groups:list<array{area:string,entity:string,count:int}>},preflight:array{status:string,label:string,blockers:list<string>}} */
    private function publicationReview(): array
    {
        $publication = app(PublicationService::class);
        $summary = $publication->pendingSummary();

        return [
            'summary' => $summary,
            'preflight' => $publication->preflight($summary),
        ];
    }

    /** @param array<string, mixed> $arguments
     * @return list<Action>
     */
    private function activityDetailsHeaderActions(array $arguments): array
    {
        $event = $this->activityDetails($arguments);
        $actions = [];

        if (is_array($event['undo'] ?? null)) {
            $receiptId = (int) $event['undo']['id'];
            $actions[] = Action::make('undoActivityEvent')
                ->label('Undo')
                ->icon(AdminIcon::Refresh->value)
                ->iconButton()
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Undo change?')
                ->modalDescription((string) $event['undo']['confirmation'])
                ->action(fn (): mixed => $this->undo($receiptId));
        }

        if (is_string($event['url'] ?? null) && $event['url'] !== '') {
            $actions[] = Action::make('openActivityRecord')
                ->label('Open record')
                ->icon(AdminIcon::OpenPublic->value)
                ->iconButton()
                ->color('gray')
                ->url($event['url']);
        }

        return $actions;
    }

    private function publicationWarning(ValidationException $exception, string $fallback): void
    {
        $message = $exception->errors()['publication'][0] ?? $fallback;

        app(AdminNotifier::class)->toast(
            title: 'Publication action unavailable',
            body: $message,
            status: 'warning',
        );
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
