<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminUndoService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Filament\Support\ActivityClockProjection;
use App\Filament\Support\AdminActivityFeed;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminPublicationHistory;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use BackedEnum;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Actions\Action;
use Filament\Pages\Page;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use UnitEnum;

final class Activity extends Page
{
    protected static string|BackedEnum|null $navigationIcon = AdminIcon::Activity;

    protected static string|UnitEnum|null $navigationGroup = 'Insights';

    protected static ?string $navigationLabel = 'Activity';

    protected static ?string $title = 'Activity';

    protected static ?int $navigationSort = 45;

    protected string $view = 'filament.pages.activity';

    /** @var list<int> */
    private const PAGE_SIZES = [25, 50, 100];

    private const DEFAULT_PAGE_SIZE = 25;

    private const VIEW_ACTIVITY = 'activity';

    private const VIEW_COMMITS = 'commits';

    #[Url(as: 'view', except: self::VIEW_ACTIVITY, history: true)]
    public string $viewMode = self::VIEW_ACTIVITY;

    #[Url(as: 'per_page', except: self::DEFAULT_PAGE_SIZE, history: true)]
    public int $perPage = self::DEFAULT_PAGE_SIZE;

    #[Url(as: 'page', except: 1, history: true)]
    public int $activityPage = 1;

    #[Url(as: 'commits_page', except: 1, history: true)]
    public int $commitPage = 1;

    /** @var list<int> */
    public array $selectedActivityIds = [];

    /** @var list<int> */
    public array $selectedCommitIds = [];

    #[Url(as: 'search', except: '')]
    public string $search = '';

    #[Url(as: 'area', except: '')]
    public string $areaFilter = '';

    #[Url(as: 'family', except: '')]
    public string $familyFilter = '';

    #[Url(as: 'calendar_date', except: '')]
    public string $dateFilter = '';

    #[Url(as: 'hour', except: '')]
    public string $hourFilter = '';

    /** @var array<string, mixed> */
    public array $activityState = [];

    /** @var array<string, mixed> */
    public array $workspaceSnapshot = [];

    /** @var array<int, array<string, mixed>|null> */
    private array $activityEventCache = [];

    /** @var array<int, array<string, mixed>|null> */
    private array $commitDetailsCache = [];

    public function mount(): void
    {
        $requestedView = request()->query('view', $this->viewMode);
        $this->viewMode = $requestedView === self::VIEW_COMMITS
            ? self::VIEW_COMMITS
            : self::VIEW_ACTIVITY;
        $this->perPage = $this->normalizePageSize($this->perPage);
        $this->activityPage = max(1, $this->activityPage);
        $this->commitPage = max(1, $this->commitPage);
        $this->activityState = $this->activityStateFromRequest();
        $this->search = (string) ($this->activityState['search'] ?? '');
        $this->areaFilter = (string) ($this->activityState['area'] ?? '');
        $this->familyFilter = (string) ($this->activityState['family'] ?? '');
        $this->dateFilter = (string) ($this->activityState['activeDate'] ?? '');
        $this->hourFilter = isset($this->activityState['activeHour']) ? (string) $this->activityState['activeHour'] : '';
        $this->refreshWorkspaceSnapshot();
    }

    public function updatedSearch(): void
    {
        $this->search = trim($this->search);
        $this->activityState['search'] = $this->search;
        $this->refreshActivityFilters();
    }

    public function updatedAreaFilter(): void
    {
        if ($this->areaFilter !== '' && ! array_key_exists($this->areaFilter, AdminActionCatalog::areaOptions())) {
            $this->areaFilter = '';
        }

        $this->activityState['area'] = $this->areaFilter !== '' ? $this->areaFilter : null;
        $this->refreshActivityFilters();
    }

    public function updatedFamilyFilter(): void
    {
        if ($this->familyFilter !== '' && ! array_key_exists($this->familyFilter, AdminActionCatalog::familyOptions())) {
            $this->familyFilter = '';
        }

        $this->activityState['family'] = $this->familyFilter !== '' ? $this->familyFilter : null;
        $this->refreshActivityFilters();
    }

    public function updatedDateFilter(): void
    {
        $this->dateFilter = trim($this->dateFilter);
        $this->activityState['activeDate'] = $this->dateFilter !== '' ? $this->dateFilter : null;

        if ($this->dateFilter !== '') {
            $currentYear = (int) ($this->activityState['currentYear'] ?? now()->format('Y'));
            $this->activityState['calendarYear'] = max(2000, min($currentYear, (int) substr($this->dateFilter, 0, 4)));
        }

        $this->refreshActivityFilters();
    }

    public function updatedHourFilter(): void
    {
        $hour = filter_var($this->hourFilter, FILTER_VALIDATE_INT);
        if ($this->hourFilter === '' || $hour === false || $hour < 0 || $hour > 23) {
            $this->hourFilter = '';
            $this->activityState['activeHour'] = null;
        } else {
            $this->hourFilter = (string) $hour;
            $this->activityState['activeHour'] = $hour;
        }

        $this->refreshActivityFilters();
    }

    private function refreshActivityFilters(): void
    {
        $this->activityPage = 1;
        $this->commitPage = 1;
        $this->clearSelection();
        $this->refreshWorkspaceSnapshot();
    }

    public function updatedPerPage(mixed $value): void
    {
        $this->perPage = $this->normalizePageSize($value);
        $this->activityState['perPage'] = $this->perPage;
        $this->activityPage = 1;
        $this->commitPage = 1;
        $this->clearSelection();
    }

    public function previousActivityPage(): void
    {
        $this->activityPage = max(1, $this->activityPage - 1);
        $this->clearSelection();
    }

    public function nextActivityPage(): void
    {
        $this->activityPage++;
        $this->clearSelection();
    }

    public function previousCommitPage(): void
    {
        $this->commitPage = max(1, $this->commitPage - 1);
        $this->clearSelection();
    }

    public function nextCommitPage(): void
    {
        $this->commitPage++;
        $this->clearSelection();
    }

    public function setViewMode(string $viewMode): void
    {
        $next = $viewMode === self::VIEW_COMMITS
            ? self::VIEW_COMMITS
            : self::VIEW_ACTIVITY;

        if ($this->viewMode === $next) {
            return;
        }

        $this->viewMode = $next;
        $this->clearSelection();
    }

    public function toggleActivitySelection(int $eventId): void
    {
        $this->selectedActivityIds = $this->toggleSelectionId($this->selectedActivityIds, $eventId);
    }

    public function toggleCommitSelection(int $checkpointId): void
    {
        $this->selectedCommitIds = $this->toggleSelectionId($this->selectedCommitIds, $checkpointId);
    }

    /** @param array<int, int|string> $eventIds */
    public function toggleVisibleActivitySelection(array $eventIds): void
    {
        $this->selectedActivityIds = $this->toggleVisibleSelection($this->selectedActivityIds, $eventIds);
    }

    /** @param array<int, int|string> $checkpointIds */
    public function toggleVisibleCommitSelection(array $checkpointIds): void
    {
        $this->selectedCommitIds = $this->toggleVisibleSelection($this->selectedCommitIds, $checkpointIds);
    }

    public function clearSelection(): void
    {
        $this->selectedActivityIds = [];
        $this->selectedCommitIds = [];
    }

    public function openSelectedDetails(): void
    {
        $id = $this->viewMode === self::VIEW_COMMITS
            ? $this->singleSelectedId($this->selectedCommitIds)
            : $this->singleSelectedId($this->selectedActivityIds);

        if ($id === null) {
            $this->selectionWarning('Select exactly one row to open its details.');

            return;
        }

        if ($this->viewMode === self::VIEW_COMMITS) {
            $this->openCommitDetails($id);

            return;
        }

        $this->openActivityDetails($id);
    }

    private function performUndoSelectedActivity(): void
    {
        $eventIds = $this->normalizeSelectionIds($this->selectedActivityIds);
        rsort($eventIds, SORT_NUMERIC);

        if ($eventIds === []) {
            $this->selectionWarning('Select at least one activity event first.');

            return;
        }

        $actor = app(AdminAuditService::class)->requireActor();
        $feed = app(AdminActivityFeed::class);
        $undo = app(AdminUndoService::class);
        $undone = 0;
        $skipped = 0;

        foreach ($eventIds as $eventId) {
            $event = $feed->event($eventId, $actor);
            $receiptId = is_array($event['undo'] ?? null) && is_numeric($event['undo']['id'] ?? null)
                ? (int) $event['undo']['id']
                : 0;

            if ($receiptId <= 0) {
                $skipped++;

                continue;
            }

            try {
                $undo->undo($receiptId);
                $undone++;
            } catch (ValidationException) {
                $skipped++;
            }
        }

        $this->selectedActivityIds = [];
        $this->refreshWorkspaceSnapshot();

        app(AdminNotifier::class)->notification(
            title: $undone > 0 ? 'Selected changes undone' : 'Undo unavailable',
            body: number_format($undone).' change'.($undone === 1 ? '' : 's').' undone'
                .($skipped > 0 ? ' · '.number_format($skipped).' skipped because Undo was unavailable.' : '.'),
            status: $undone > 0 ? 'success' : 'warning',
        );
    }

    private function performRestoreSelectedCommit(): void
    {
        $checkpointId = $this->singleSelectedId($this->selectedCommitIds);
        if ($checkpointId === null) {
            $this->selectionWarning('Select exactly one commit to restore.');

            return;
        }

        $commit = app(AdminPublicationHistory::class)->checkpoint($checkpointId);
        if (! is_array($commit) || ($commit['can_restore'] ?? false) !== true) {
            $this->selectionWarning('The selected commit is not available for restore.');

            return;
        }

        $this->performRestoreVersion($checkpointId);
        $this->selectedCommitIds = [];
    }

    private function performRevertSelectedCommit(): void
    {
        $checkpointId = $this->singleSelectedId($this->selectedCommitIds);
        if ($checkpointId === null) {
            $this->selectionWarning('Select exactly one commit to revert.');

            return;
        }

        $commit = app(AdminPublicationHistory::class)->checkpoint($checkpointId);
        if (! is_array($commit) || ($commit['can_revert'] ?? false) !== true) {
            $this->selectionWarning('Only the current LIVE commit with a restorable parent can be reverted.');

            return;
        }

        $this->performRevertCurrentCommit();
        $this->selectedCommitIds = [];
    }

    #[On('publication-state-changed')]
    public function refreshWorkspaceSnapshot(): void
    {
        if ($this->activityState === []) {
            return;
        }

        $this->workspaceSnapshot = $this->buildWorkspaceSnapshot();
    }

    private function performUndo(int $receiptId): void
    {
        try {
            $result = app(AdminUndoService::class)->undo($receiptId);
        } catch (ValidationException $exception) {
            $message = $exception->errors()['undo'][0] ?? 'This change can no longer be undone safely.';

            app(AdminNotifier::class)->notification(
                title: 'Undo unavailable',
                body: $message,
                status: 'warning',
            );

            return;
        }

        $this->refreshWorkspaceSnapshot();

        app(AdminNotifier::class)->notification(
            title: 'Change undone',
            body: $result['inverse'].' was applied as a new editorial action.',
            status: 'success',
        );
    }

    private function performResetStagedChanges(): void
    {
        $actor = app(AdminAuditService::class)->requireActor();

        try {
            $changed = app(PublicationVersionService::class)->resetStagedChanges($actor);
        } catch (ValidationException $exception) {
            $this->publicationWarning($exception, 'Staged changes could not be reset.');

            return;
        }

        $this->refreshWorkspaceSnapshot();

        app(AdminNotifier::class)->notification(
            title: $changed > 0 ? 'Staged changes reset' : 'Nothing staged',
            body: $changed > 0
                ? number_format($changed).' working row'.($changed === 1 ? '' : 's').' restored from the current live version.'
                : 'The working state already matches the current live version.',
            status: $changed > 0 ? 'success' : 'info',
        );
    }

    private function performRestoreVersion(int $checkpointId): void
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
        $this->refreshWorkspaceSnapshot();
        app(AdminNotifier::class)->notification(
            title: 'Version restored to working state',
            body: $checkpoint->shortHash().' is now staged with '.number_format($staged).' pending change'.($staged === 1 ? '' : 's').'.',
            status: 'success',
        );
    }

    private function performRevertCurrentCommit(): void
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
        $this->refreshWorkspaceSnapshot();
        app(AdminNotifier::class)->notification(
            title: 'Commit revert staged',
            body: $result['reverted']->shortHash().' will be reversed by publishing the parent state '.$result['checkpoint']->shortHash().'. '.number_format($staged).' change'.($staged === 1 ? '' : 's').' staged.',
            status: 'success',
        );
    }

    public function openActivityDetails(int $eventId): void
    {
        abort_unless($eventId > 0, 404);

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

    public function resetStagedChangesAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('resetStagedChanges')
                ->label('Reset')
                ->action(function (): void {
                    $this->performResetStagedChanges();
                }),
            heading: 'Reset staged changes?',
            description: 'The working state will be restored exactly to the current LIVE version. Activity history is preserved.',
            submitLabel: 'Reset',
            icon: AdminIcon::Undo,
        );
    }

    public function undoSelectedActivityAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('undoSelectedActivity')
                ->label('Undo selected')
                ->action(function (): void {
                    $this->performUndoSelectedActivity();
                }),
            heading: 'Undo selected changes?',
            description: 'Newer selected changes are undone first. Changes without a safe Undo receipt are skipped. Activity history remains available.',
            submitLabel: 'Undo',
            icon: AdminIcon::Undo,
        );
    }

    public function restoreSelectedCommitAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('restoreSelectedCommit')
                ->label('Restore selected')
                ->action(function (): void {
                    $this->performRestoreSelectedCommit();
                }),
            heading: 'Restore selected version?',
            description: 'The selected version will replace all current staged work. The LIVE site will not change until you commit.',
            submitLabel: 'Restore',
            icon: AdminIcon::Undo,
        );
    }

    public function revertSelectedCommitAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('revertSelectedCommit')
                ->label('Revert selected')
                ->action(function (): void {
                    $this->performRevertSelectedCommit();
                }),
            heading: 'Revert selected LIVE commit?',
            description: 'Its parent version will replace all current staged work for review. Nothing is published until you commit.',
            submitLabel: 'Revert',
            icon: AdminIcon::Undo,
        );
    }

    public function undoActivityAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('undoActivity')
                ->label('Undo')
                ->action(function (array $arguments): void {
                    $event = $this->activityDetails($arguments);
                    $receiptId = is_array($event) && is_array($event['undo'] ?? null) && is_numeric($event['undo']['id'] ?? null)
                        ? (int) $event['undo']['id']
                        : 0;

                    if ($receiptId <= 0) {
                        $this->selectionWarning('This change can no longer be undone safely.');

                        return;
                    }

                    $this->performUndo($receiptId);
                }),
            heading: 'Undo change?',
            description: function (array $arguments): string {
                $event = $this->activityDetails($arguments);

                return is_array($event) && is_array($event['undo'] ?? null)
                    ? (string) ($event['undo']['confirmation'] ?? 'Undo this change? Activity history remains available.')
                    : 'Undo this change? Activity history remains available.';
            },
            submitLabel: 'Undo',
            icon: AdminIcon::Undo,
        );
    }

    public function restoreVersionAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('restoreVersion')
                ->label('Restore')
                ->action(function (array $arguments): void {
                    $commit = $this->commitDetails($arguments);
                    if (! is_array($commit) || ($commit['can_restore'] ?? false) !== true) {
                        $this->selectionWarning('This commit is not available for restore.');

                        return;
                    }

                    $this->performRestoreVersion((int) $commit['id']);
                }),
            heading: fn (array $arguments): string => 'Restore version '.($this->commitDetails($arguments)['short_hash'] ?? '').'?',
            description: 'This replaces all current staged work with the selected version. The LIVE site will not change until you commit.',
            submitLabel: 'Restore',
            icon: AdminIcon::Undo,
        );
    }

    public function revertCurrentCommitAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('revertCurrentCommit')
                ->label('Revert')
                ->action(function (array $arguments): void {
                    $commit = $this->commitDetails($arguments);
                    if (! is_array($commit) || ($commit['can_revert'] ?? false) !== true) {
                        $this->selectionWarning('Only the current LIVE commit with a restorable parent can be reverted.');

                        return;
                    }

                    $this->performRevertCurrentCommit();
                }),
            heading: fn (array $arguments): string => 'Revert LIVE commit '.($this->commitDetails($arguments)['short_hash'] ?? '').'?',
            description: 'Its parent version will replace all current staged work for review. Nothing is published until you commit.',
            submitLabel: 'Revert',
            icon: AdminIcon::Undo,
        );
    }

    public function activityDetailsAction(): Action
    {
        return AdminDialog::viewer(
            Action::make('activityDetails')
                ->label('Details')
                ->modalHeading(fn (array $arguments): string => (string) ($this->activityDetails($arguments)['change'] ?? 'Activity details'))
                ->modalContent(fn (array $arguments): View => view(
                    'filament.pages.partials.activity-details-dialog',
                    ['event' => $this->activityDetails($arguments)],
                ))
                ->extraModalFooterActions(fn (array $arguments): array => $this->activityDetailsHeaderActions($arguments)),
            AdminDialogSize::Default,
        );
    }

    public function publicationReviewAction(): Action
    {
        return AdminDialog::viewer(
            Action::make('publicationReview')
                ->label('Review changes')
                ->modalHeading('Review staged changes')
                ->modalContent(fn (): View => view(
                    'filament.pages.partials.activity-publication-review-dialog',
                    ['publication' => $this->publicationReview()],
                )),
            AdminDialogSize::Default,
        );
    }

    public function commitDetailsAction(): Action
    {
        return AdminDialog::viewer(
            Action::make('commitDetails')
                ->label('Commit details')
                ->modalHeading(fn (array $arguments): string => 'Commit '.($this->commitDetails($arguments)['short_hash'] ?? ''))
                ->modalContent(fn (array $arguments): View => view(
                    'filament.pages.partials.activity-commit-details-dialog',
                    ['commit' => $this->commitDetails($arguments)],
                )),
            AdminDialogSize::Default,
        );
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $state = $this->activityState;
        $area = is_string($state['area'] ?? null) ? $state['area'] : null;
        $family = is_string($state['family'] ?? null) ? $state['family'] : null;
        $search = is_string($state['search'] ?? null) ? $state['search'] : '';
        $activeDateValue = is_string($state['activeDate'] ?? null) ? $state['activeDate'] : null;
        $activeHour = is_int($state['activeHour'] ?? null) ? $state['activeHour'] : null;
        $perPage = $this->normalizePageSize($this->perPage);
        $viewMode = $this->viewMode === self::VIEW_COMMITS ? self::VIEW_COMMITS : self::VIEW_ACTIVITY;

        $activity = [];
        $paginator = null;
        $commits = [];
        $commitPaginator = null;
        $sourceExists = false;

        if ($viewMode === self::VIEW_COMMITS) {
            $history = app(AdminPublicationHistory::class)->page(
                area: $area,
                family: $family,
                search: $search,
                date: $activeDateValue,
                hour: $activeHour,
                perPage: $perPage,
                page: $this->commitPage,
            );
            $commits = $history['commits'];
            $commitPaginator = $history['paginator'];
            $sourceExists = PublicationCheckpoint::query()->exists();
        } else {
            $feed = app(AdminActivityFeed::class)->page(
                $area,
                $family,
                perPage: $perPage,
                actor: app(AdminAuditService::class)->requireActor(),
                search: $search,
                date: $activeDateValue,
                hour: $activeHour,
                page: $this->activityPage,
            );
            $activity = $feed['activity'];
            $paginator = $feed['paginator'];
            $sourceExists = app(AdminActivityFeed::class)->exists();
        }

        return [
            'viewMode' => $viewMode,
            'activity' => $activity,
            'paginator' => $paginator,
            'commits' => $commits,
            'commitPaginator' => $commitPaginator,
            'selectedActivityIds' => $this->selectedActivityIds,
            'selectedCommitIds' => $this->selectedCommitIds,
            'area' => $area,
            'family' => $family,
            'search' => $search,
            'perPage' => $perPage,
            'pageSizes' => self::PAGE_SIZES,
            'areaOptions' => AdminActionCatalog::areaOptions(),
            'familyOptions' => AdminActionCatalog::familyOptions(),
            'activeDate' => $activeDateValue,
            'activeHour' => $activeHour,
            'todayDate' => (string) ($state['todayDate'] ?? CarbonImmutable::today()->format('Y-m-d')),
            'activitySourceExists' => $sourceExists,
            ...$this->workspaceSnapshot,
        ];
    }

    /** @return array<string, mixed> */
    private function activityStateFromRequest(): array
    {
        $area = request()->query('area');
        $family = request()->query('family');
        $search = request()->query('search');
        $area = is_string($area) && array_key_exists($area, AdminActionCatalog::areaOptions()) ? $area : null;
        $family = is_string($family) && array_key_exists($family, AdminActionCatalog::familyOptions()) ? $family : null;
        $search = is_string($search) ? trim($search) : '';
        $perPage = $this->normalizePageSize($this->perPage);

        $today = CarbonImmutable::today();
        $currentYear = (int) $today->format('Y');
        $activeDate = $this->requestedActivityDate($today);
        $activeHour = $this->requestedActivityHour();
        $requestedYear = request()->query('calendar_year');
        $calendarYear = is_numeric($requestedYear)
            ? max(2000, min($currentYear, (int) $requestedYear))
            : ($activeDate->year ?? $currentYear);
        if ($activeDate !== null && $activeDate->year !== $calendarYear) {
            $calendarYear = $activeDate->year;
        }

        return [
            'area' => $area,
            'family' => $family,
            'search' => $search,
            'perPage' => $perPage,
            'activeDate' => $activeDate?->format('Y-m-d'),
            'activeHour' => $activeHour,
            'calendarYear' => $calendarYear,
            'currentYear' => $currentYear,
            'todayDate' => $today->format('Y-m-d'),
        ];
    }

    /** @return array<string, mixed> */
    private function buildWorkspaceSnapshot(): array
    {
        $state = $this->activityState;
        $area = is_string($state['area'] ?? null) ? $state['area'] : null;
        $family = is_string($state['family'] ?? null) ? $state['family'] : null;
        $search = is_string($state['search'] ?? null) ? $state['search'] : '';
        $activeDateValue = is_string($state['activeDate'] ?? null) ? $state['activeDate'] : null;
        $activeHour = is_int($state['activeHour'] ?? null) ? $state['activeHour'] : null;
        $calendarYear = is_int($state['calendarYear'] ?? null) ? $state['calendarYear'] : (int) now()->format('Y');
        $currentYear = is_int($state['currentYear'] ?? null) ? $state['currentYear'] : (int) now()->format('Y');
        $today = CarbonImmutable::parse((string) ($state['todayDate'] ?? CarbonImmutable::today()->format('Y-m-d')))->startOfDay();
        $activeDate = $activeDateValue !== null ? CarbonImmutable::parse($activeDateValue)->startOfDay() : null;
        $calendarStart = CarbonImmutable::create($calendarYear, 1, 1)->startOfDay();
        $calendarEnd = CarbonImmutable::create($calendarYear, 12, 31)->endOfDay();

        $activityFeed = app(AdminActivityFeed::class);
        $historyService = app(AdminPublicationHistory::class);
        $overview = $activityFeed->overview(
            $area,
            $family,
            search: $search,
            date: $activeDateValue,
            hour: $activeHour,
        );
        $publicationContext = $this->decoratePublicationContext($activityFeed->publicationContext());

        $commitSummary = $historyService->queryForFilters(
            $area,
            $family,
            $search,
            $activeDateValue,
            $activeHour,
        )
            ->toBase()
            ->selectRaw('COUNT(*) AS aggregate, COALESCE(SUM(change_count), 0) AS changed_rows, MAX(published_at) AS latest_at')
            ->first();

        $activityLatest = $overview['latest_at'] !== null
            ? CarbonImmutable::parse((string) $overview['latest_at'])
            : null;
        $commitLatest = isset($commitSummary->latest_at)
            ? CarbonImmutable::parse((string) $commitSummary->latest_at)
            : null;
        $latestAt = match (true) {
            $activityLatest === null => $commitLatest,
            $commitLatest === null => $activityLatest,
            $commitLatest->gt($activityLatest) => $commitLatest,
            default => $activityLatest,
        };

        $workspaceMetrics = [
            ['label' => 'Changes', 'value' => number_format($overview['total']), 'description' => 'Matching activity events'],
            ['label' => 'Commits', 'value' => number_format((int) ($commitSummary->aggregate ?? 0)), 'description' => 'Matching published versions'],
            ['label' => 'Committed changes', 'value' => number_format((int) ($commitSummary->changed_rows ?? 0)), 'description' => 'Rows in matching commits'],
            ['label' => 'Active days', 'value' => number_format($overview['active_days']), 'description' => 'Days with matching activity'],
            ['label' => 'Pending', 'value' => number_format((int) $publicationContext['staged']), 'description' => 'Working changes not LIVE yet'],
            ['label' => 'Latest', 'value' => $latestAt?->diffForHumans() ?? '—', 'description' => $latestAt?->format('Y-m-d H:i') ?? 'No matching activity'],
        ];

        $timeline = $this->timelinePresentation(
            $this->activityCalendarQuery(
                $area,
                $family,
                $search,
                $calendarStart,
                $calendarEnd,
            ),
            'occurred_at',
            $calendarStart,
            $calendarEnd,
            $today,
            $activeDate,
            $calendarYear,
            $currentYear,
        );

        return [
            'workspaceMetrics' => $workspaceMetrics,
            'publicationContext' => $publicationContext,
            ...$timeline,
        ];
    }

    /**
     * @param  Builder<*>  $calendarQuery
     * @return array<string, mixed>
     */
    private function timelinePresentation(
        Builder $calendarQuery,
        string $timestampColumn,
        CarbonImmutable $calendarStart,
        CarbonImmutable $calendarEnd,
        CarbonImmutable $today,
        ?CarbonImmutable $activeDate,
        int $calendarYear,
        int $currentYear,
    ): array {
        $driver = $calendarQuery->getModel()->getConnection()->getDriverName();
        $dateExpression = match ($driver) {
            'pgsql' => $timestampColumn.'::date',
            default => 'DATE('.$timestampColumn.')',
        };
        $hourExpression = match ($driver) {
            'sqlite' => "CAST(strftime('%H', {$timestampColumn}) AS INTEGER)",
            'mysql', 'mariadb' => 'HOUR('.$timestampColumn.')',
            default => 'EXTRACT(HOUR FROM '.$timestampColumn.')::int',
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

        $selectedDayQuery = (clone $calendarQuery)->whereBetween($timestampColumn, [
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

        $clock = app(ActivityClockProjection::class)->fromHourly($hourly);
        $clockActivity = $clock['activity'];
        $clockPeakCount = $clock['peak_count'];
        $clockPeakHour = $clock['peak_hour'];
        $selectedLatestAt = (clone $selectedDayQuery)->max($timestampColumn);
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
        $hasTimelineActivity = $selectedLatestAt !== null;

        return [
            'clockActivity' => $clockActivity,
            'clockPeakHour' => $clockPeakHour,
            'clockPeakCount' => $clockPeakCount,
            'clockAtIso' => $clockAt->toIso8601String(),
            'clockIsLive' => $clockIsLive,
            'clockHasActivity' => $hasTimelineActivity || $clockIsLive,
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
                : ($hasTimelineActivity ? 'Latest activity' : 'No activity'),
            'timelineItemLabel' => 'changes',
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

        if (array_key_exists($eventId, $this->activityEventCache)) {
            return $this->activityEventCache[$eventId];
        }

        return $this->activityEventCache[$eventId] = app(AdminActivityFeed::class)->event(
            $eventId,
            app(AdminAuditService::class)->requireActor(),
        );
    }

    /** @return array<string,mixed>|null */
    private function commitDetails(array $arguments): ?array
    {
        $checkpointId = is_numeric($arguments['id'] ?? null) ? (int) $arguments['id'] : 0;
        if ($checkpointId <= 0) {
            return null;
        }

        if (array_key_exists($checkpointId, $this->commitDetailsCache)) {
            return $this->commitDetailsCache[$checkpointId];
        }

        return $this->commitDetailsCache[$checkpointId] = app(AdminPublicationHistory::class)->checkpoint($checkpointId);
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
            $actions[] = AdminDialog::confirm(
                Action::make('undoActivityEvent')
                    ->label('Undo')
                    ->icon(AdminIcon::Undo->value)
                    ->iconButton()
                    ->color('gray')
                    ->action(function () use ($receiptId): void {
                        $this->performUndo($receiptId);
                    }),
                heading: 'Undo change?',
                description: (string) $event['undo']['confirmation'],
                submitLabel: 'Undo',
                danger: false,
                icon: AdminIcon::Undo,
            );
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

    /** @param array<int, int|string> $ids @return list<int> */
    private function normalizeSelectionIds(array $ids): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (int|string $id): int => (int) $id, $ids),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /** @param array<int, int|string> $ids @return list<int> */
    private function toggleSelectionId(array $ids, int $id): array
    {
        $selected = $this->normalizeSelectionIds($ids);
        if ($id <= 0) {
            return $selected;
        }

        if (in_array($id, $selected, true)) {
            return array_values(array_diff($selected, [$id]));
        }

        $selected[] = $id;

        return array_values(array_unique($selected));
    }

    /** @param array<int, int|string> $selectedIds @param array<int, int|string> $visibleIds @return list<int> */
    private function toggleVisibleSelection(array $selectedIds, array $visibleIds): array
    {
        $selected = $this->normalizeSelectionIds($selectedIds);
        $visible = $this->normalizeSelectionIds($visibleIds);
        if ($visible === []) {
            return $selected;
        }

        if (array_diff($visible, $selected) === []) {
            return array_values(array_diff($selected, $visible));
        }

        return array_values(array_unique([...$selected, ...$visible]));
    }

    /** @param array<int, int|string> $ids */
    private function singleSelectedId(array $ids): ?int
    {
        $selected = $this->normalizeSelectionIds($ids);

        return count($selected) === 1 ? $selected[0] : null;
    }

    private function selectionWarning(string $message): void
    {
        app(AdminNotifier::class)->notification(
            title: 'Selection action unavailable',
            body: $message,
            status: 'warning',
        );
    }

    private function normalizePageSize(mixed $value): int
    {
        $pageSize = is_numeric($value) ? (int) $value : self::DEFAULT_PAGE_SIZE;

        return in_array($pageSize, self::PAGE_SIZES, true)
            ? $pageSize
            : self::DEFAULT_PAGE_SIZE;
    }

    private function publicationWarning(ValidationException $exception, string $fallback): void
    {
        $message = $exception->errors()['publication'][0] ?? $fallback;

        app(AdminNotifier::class)->notification(
            title: 'Publication action unavailable',
            body: $message,
            status: 'warning',
        );
    }

    /** @return Builder<AuditEvent> */
    private function activityCalendarQuery(
        ?string $area,
        ?string $family,
        string $search,
        CarbonImmutable $start,
        CarbonImmutable $end,
    ): Builder {
        return app(AdminActivityFeed::class)
            ->query($area, $family, search: $search)
            ->whereBetween('occurred_at', [$start, $end]);
    }
}
