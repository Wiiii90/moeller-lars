<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\DashboardFeed;
use App\Domain\Admin\DashboardFeedPins;
use App\Domain\Admin\DashboardNotificationRetention;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\DashboardOverview;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;

final class Dashboard extends Page
{
    private const PAGE_SIZES = [25, 50, 100];

    private const DEFAULT_PAGE_SIZE = 50;

    private const NOTIFICATION_FILTERS = [
        'all' => 'All notifications',
        'success' => 'Successful',
        'info' => 'Information',
        'warning' => 'Warnings',
        'danger' => 'Errors',
    ];

    protected static string|\BackedEnum|null $navigationIcon = AdminIcon::Dashboard;

    protected static ?string $navigationLabel = 'Dashboard';

    protected static ?string $title = 'Dashboard';

    protected static string|\UnitEnum|null $navigationGroup = null;

    protected static ?int $navigationSort = -100;

    protected static ?string $slug = '';

    protected string $view = 'filament.pages.dashboard';

    public string $feedSearch = '';

    public string $feedType = 'all';

    public int $feedPage = 1;

    public int $feedPageSize = self::DEFAULT_PAGE_SIZE;

    public string $notificationFilter = 'all';

    /** @var list<string> */
    public array $selectedFeedKeys = [];

    public function mount(): void
    {
        $filter = auth()->user()?->getAttribute('dashboard_notification_filter');
        $this->notificationFilter = is_string($filter) && array_key_exists($filter, self::NOTIFICATION_FILTERS)
            ? $filter
            : 'all';
    }

    public function updatedFeedSearch(): void
    {
        $this->refreshFeedFromFirstPage();
    }

    public function updatedFeedType(): void
    {
        if (! array_key_exists($this->feedType, DashboardFeed::types())) {
            $this->feedType = 'all';
        }

        $this->refreshFeedFromFirstPage();
    }

    public function updatedFeedPageSize(mixed $value): void
    {
        $size = is_numeric($value) ? (int) $value : self::DEFAULT_PAGE_SIZE;
        $this->feedPageSize = in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
        $this->refreshFeedFromFirstPage();
    }

    public function updatedNotificationFilter(): void
    {
        if (! array_key_exists($this->notificationFilter, self::NOTIFICATION_FILTERS)) {
            $this->notificationFilter = 'all';
        }

        $user = auth()->user();
        if ($user instanceof User) {
            $user->forceFill(['dashboard_notification_filter' => $this->notificationFilter])->save();
        }

        $this->refreshFeedFromFirstPage();
    }

    public function resetFeed(): void
    {
        $this->feedSearch = '';
        $this->feedType = 'all';
        $this->refreshFeedFromFirstPage();
    }

    public function previousFeedPage(): void
    {
        if ($this->feedPage > 1) {
            $this->feedPage--;
            $this->selectedFeedKeys = [];
        }
    }

    public function nextFeedPage(): void
    {
        $pages = $this->feedPagination()['pages'];
        if ($this->feedPage < $pages) {
            $this->feedPage++;
            $this->selectedFeedKeys = [];
        }
    }

    public function openFeedEntry(string $key): void
    {
        $entry = app(DashboardFeed::class)->openEntry($key);
        abort_unless(is_array($entry), 404);

        $this->mountAction('feedEntry', ['key' => $key]);
    }

    public function toggleFeedPin(string $key): void
    {
        app(DashboardFeedPins::class)->toggle($key);
    }

    public function reorderPinnedFeed(mixed $key, int $position): void
    {
        if (! is_string($key) || $key === '') {
            return;
        }

        app(DashboardFeedPins::class)->move($key, $position);
    }

    public function bulkPin(): void
    {
        app(DashboardFeedPins::class)->pinMany($this->validSelectedKeys());
        $this->selectedFeedKeys = [];
    }

    public function bulkUnpin(): void
    {
        app(DashboardFeedPins::class)->unpinMany($this->validSelectedKeys());
        $this->selectedFeedKeys = [];
    }

    public function markFeedRead(string $key): void
    {
        if ($this->mutableFeedEntry($key) !== null) {
            app(DashboardFeed::class)->openEntry($key);
        }
    }

    public function markFeedUnread(string $key): void
    {
        $entry = $this->mutableFeedEntry($key);
        if ($entry === null) {
            return;
        }

        $feed = app(DashboardFeed::class);
        if (is_int($entry['contact_id'] ?? null)) {
            $feed->markContactUnread($entry['contact_id']);
        } elseif (is_int($entry['notification_id'] ?? null)) {
            $feed->markNotificationUnread($entry['notification_id']);
        }
    }

    public function deleteFeedEntry(string $key): void
    {
        $entry = $this->mutableFeedEntry($key);
        if ($entry === null) {
            return;
        }

        $this->deleteProjectedFeedEntry($entry);
        $this->selectedFeedKeys = array_values(array_diff($this->selectedFeedKeys, [$key]));
        $this->feedPage = $this->feedPagination()['page'];
    }

    public function toggleSelectAll(): void
    {
        $visibleKeys = $this->currentSelectableFeedKeys();
        if ($visibleKeys === []) {
            $this->selectedFeedKeys = [];

            return;
        }

        $selectedVisible = array_values(array_intersect($visibleKeys, $this->selectedFeedKeys));
        $this->selectedFeedKeys = count($selectedVisible) === count($visibleKeys)
            ? array_values(array_diff($this->selectedFeedKeys, $visibleKeys))
            : array_values(array_unique([...$this->selectedFeedKeys, ...$visibleKeys]));
    }

    public function bulkMarkRead(): void
    {
        if (! $this->selectionAllMutable()) {
            return;
        }

        foreach ($this->selectedFeedKeys as $key) {
            app(DashboardFeed::class)->openEntry($key);
        }

        $this->selectedFeedKeys = [];
    }

    public function bulkMarkUnread(): void
    {
        if (! $this->selectionAllMutable()) {
            return;
        }

        foreach ($this->selectedFeedKeys as $key) {
            $this->markFeedUnread($key);
        }

        $this->selectedFeedKeys = [];
    }

    public function bulkDelete(): void
    {
        if (! $this->selectionAllMutable()) {
            return;
        }

        foreach ($this->selectedFeedKeys as $key) {
            $entry = $this->mutableFeedEntry($key);
            if ($entry !== null) {
                $this->deleteProjectedFeedEntry($entry);
            }
        }

        $this->selectedFeedKeys = [];
        $this->feedPage = $this->feedPagination()['page'];
    }

    public function markContactUnread(int $contactMessageId): void
    {
        app(DashboardFeed::class)->markContactUnread($contactMessageId);

        app(AdminNotifier::class)->toast(
            title: 'Contact message marked unread',
            status: 'success',
        );
    }

    public function deleteContactMessage(int $contactMessageId): void
    {
        app(DashboardFeedPins::class)->forget('contact:'.$contactMessageId);
        app(DashboardFeed::class)->deleteContact($contactMessageId);

        app(AdminNotifier::class)->toast(
            title: 'Contact message deleted',
            status: 'success',
        );

        $this->feedPage = $this->feedPagination()['page'];
    }

    public function markNotificationUnread(int $notificationId): void
    {
        app(DashboardFeed::class)->markNotificationUnread($notificationId);
    }

    public function deleteNotification(int $notificationId): void
    {
        app(DashboardFeedPins::class)->forget('notification:'.$notificationId);
        app(DashboardFeed::class)->deleteNotification($notificationId);
        $this->feedPage = $this->feedPagination()['page'];
    }

    public function dashboardSettingsAction(): Action
    {
        return Action::make('dashboardSettings')
            ->label('Settings')
            ->modalHeading('Dashboard settings')
            ->fillForm(function (): array {
                $user = auth()->user();

                return [
                    'notification_filter' => $this->notificationFilter,
                    'notification_retention' => app(DashboardNotificationRetention::class)->limitFor($user instanceof User ? $user : 0),
                    'delete_without_confirmation' => (bool) $user?->getAttribute('dashboard_delete_without_confirmation'),
                ];
            })
            ->schema([
                Select::make('notification_filter')
                    ->label('Notification history')
                    ->options(self::NOTIFICATION_FILTERS)
                    ->required(),
                Select::make('notification_retention')
                    ->label('Keep notification history')
                    ->options(DashboardNotificationRetention::options())
                    ->required(),
                Checkbox::make('delete_without_confirmation')
                    ->label('Delete messages without confirmation'),
            ])
            ->action(function (array $data): void {
                $user = auth()->user();
                if (! $user instanceof User) {
                    return;
                }

                $filter = $data['notification_filter'] ?? 'all';
                $this->notificationFilter = is_string($filter) && array_key_exists($filter, self::NOTIFICATION_FILTERS)
                    ? $filter
                    : 'all';
                $retention = app(DashboardNotificationRetention::class)->normalize($data['notification_retention'] ?? 10);

                $user->forceFill([
                    'dashboard_notification_filter' => $this->notificationFilter,
                    'dashboard_notification_retention' => $retention,
                    'dashboard_delete_without_confirmation' => (bool) ($data['delete_without_confirmation'] ?? false),
                ])->save();

                app(DashboardNotificationRetention::class)->pruneFor($user);
                $this->refreshFeedFromFirstPage();
            })
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save settings')
                ->icon(AdminIcon::Commit->value)
                ->iconButton()
                ->extraAttributes(['class' => 'admin-dialog__header-action is-primary']))
            ->modalCancelAction(false)
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--small admin-dialog--header-actions',
            ]);
    }

    public function deleteFeedEntryAction(): Action
    {
        return $this->confirmationAction(
            Action::make('deleteFeedEntry')
                ->label('Delete')
                ->color('danger')
                ->action(fn (array $arguments): mixed => $this->deleteFeedEntry((string) ($arguments['key'] ?? ''))),
            'Delete message?',
            'This removes the stored dashboard message.',
        );
    }

    public function deleteSelectedAction(): Action
    {
        return $this->confirmationAction(
            Action::make('deleteSelected')
                ->label('Delete selected')
                ->color('danger')
                ->action(fn (): mixed => $this->bulkDelete()),
            'Delete selected messages?',
            'This removes the selected contact messages and notifications.',
        );
    }

    public function feedEntryAction(): Action
    {
        return Action::make('feedEntry')
            ->label('Open')
            ->modalHeading(fn (array $arguments): string => (string) $this->feedEntry($arguments)['title'])
            ->modalContent(fn (array $arguments): View => view(
                'filament.pages.partials.dashboard-feed-dialog',
                ['entry' => $this->feedEntry($arguments)],
            ))
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->extraModalFooterActions(fn (array $arguments): array => $this->feedEntryHeaderActions($arguments))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--default admin-dialog--header-actions',
            ]);
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $overview = app(DashboardOverview::class)->snapshot();
        $pagination = $this->feedPagination();

        $this->feedPage = $pagination['page'];
        $this->feedPageSize = $pagination['per_page'];

        return [
            ...$overview,
            'feed' => $this->feedViewItems($pagination),
            'feedTypes' => DashboardFeed::types(),
            'feedPagination' => $pagination,
            'notificationFilters' => self::NOTIFICATION_FILTERS,
            'selectionCapabilities' => $this->selectionCapabilities(),
            'deleteWithoutConfirmation' => $this->deleteWithoutConfirmation(),
        ];
    }

    private function refreshFeedFromFirstPage(): void
    {
        $this->feedPage = 1;
        $this->selectedFeedKeys = [];
    }

    /** @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int,start:int,end:int} */
    private function feedPagination(): array
    {
        return app(DashboardFeed::class)->paginate(
            $this->feedSearch,
            $this->feedType,
            $this->feedPage,
            $this->feedPageSize,
        );
    }

    /**
     * @param array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int,start:int,end:int} $pagination
     * @return list<array<string,mixed>>
     */
    private function feedViewItems(array $pagination): array
    {
        $pinned = app(DashboardFeedPins::class)->entries(
            $this->feedSearch,
            $this->feedType,
            $this->notificationFilter,
        );
        $pinnedKeys = collect($pinned)
            ->pluck('key')
            ->map(static fn (mixed $key): string => (string) $key)
            ->all();

        $priority = array_map(static function (array $item): array {
            $item['feed_position'] = null;

            return $item;
        }, $pinned);

        $chronological = [];
        foreach ($pagination['items'] as $index => $item) {
            if (in_array((string) $item['key'], $pinnedKeys, true)) {
                continue;
            }

            $item['pinned'] = false;
            $item['pin_position'] = null;
            $item['feed_position'] = $pagination['start'] + $index;
            $chronological[] = $item;
        }

        return [...$priority, ...$chronological];
    }

    /** @return list<string> */
    private function currentSelectableFeedKeys(): array
    {
        return collect($this->feedViewItems($this->feedPagination()))
            ->pluck('key')
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
    }

    /** @return list<string> */
    private function validSelectedKeys(): array
    {
        $feed = app(DashboardFeed::class);

        return collect($this->selectedFeedKeys)
            ->filter(static fn (mixed $key): bool => is_string($key) && $key !== '')
            ->filter(fn (string $key): bool => is_array($feed->entry($key)))
            ->values()
            ->all();
    }

    /** @return array{has_selection:bool,all_mutable:bool} */
    private function selectionCapabilities(): array
    {
        $keys = $this->validSelectedKeys();

        return [
            'has_selection' => $keys !== [],
            'all_mutable' => $keys !== [] && collect($keys)->every(
                fn (string $key): bool => $this->mutableFeedEntry($key) !== null,
            ),
        ];
    }

    private function selectionAllMutable(): bool
    {
        return $this->selectionCapabilities()['all_mutable'];
    }

    /** @return array<string, mixed>|null */
    private function mutableFeedEntry(string $key): ?array
    {
        $entry = app(DashboardFeed::class)->entry($key);

        return is_array($entry) && $this->isMutableFeedEntry($entry) ? $entry : null;
    }

    /** @param array<string, mixed> $entry */
    private function isMutableFeedEntry(array $entry): bool
    {
        return is_int($entry['contact_id'] ?? null) || is_int($entry['notification_id'] ?? null);
    }

    /** @param array<string, mixed> $entry */
    private function deleteProjectedFeedEntry(array $entry): void
    {
        $feed = app(DashboardFeed::class);
        $key = is_string($entry['key'] ?? null) ? $entry['key'] : '';
        if ($key !== '') {
            app(DashboardFeedPins::class)->forget($key);
        }

        if (is_int($entry['contact_id'] ?? null)) {
            $feed->deleteContact($entry['contact_id']);
        } elseif (is_int($entry['notification_id'] ?? null)) {
            $feed->deleteNotification($entry['notification_id']);
        }
    }

    /**
     * @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function feedEntry(array $arguments): array
    {
        $key = is_string($arguments['key'] ?? null) ? $arguments['key'] : '';
        $entry = app(DashboardFeed::class)->entry($key);
        abort_unless(is_array($entry), 404);

        return $entry;
    }

    /**
     * @param array<string, mixed> $arguments
     * @return list<Action>
     */
    private function feedEntryHeaderActions(array $arguments): array
    {
        $entry = $this->feedEntry($arguments);
        if (! $this->isMutableFeedEntry($entry)) {
            return [];
        }

        $key = (string) $entry['key'];
        $isRead = str_starts_with((string) $entry['status'], 'Read');
        $readAction = Action::make($isRead ? 'markFeedUnread' : 'markFeedRead')
            ->label($isRead ? 'Mark unread' : 'Mark read')
            ->icon(($isRead ? AdminIcon::MarkUnread : AdminIcon::MarkRead)->value)
            ->iconButton()
            ->color('gray')
            ->extraAttributes(['class' => 'admin-dialog__header-action'])
            ->action(fn (): mixed => $isRead ? $this->markFeedUnread($key) : $this->markFeedRead($key));

        $deleteAction = Action::make('deleteDialogEntry')
            ->label('Delete')
            ->icon(AdminIcon::Delete->value)
            ->iconButton()
            ->color('danger')
            ->extraAttributes(['class' => 'admin-dialog__header-action is-danger']);

        if (is_int($entry['contact_id'] ?? null)) {
            $contactId = $entry['contact_id'];
            $deleteAction->action(fn (): mixed => $this->deleteContactMessage($contactId));
        } else {
            $notificationId = $entry['notification_id'];
            $deleteAction->action(fn (): mixed => $this->deleteNotification($notificationId));
        }

        if (! $this->deleteWithoutConfirmation()) {
            $deleteAction = $this->configureConfirmation(
                $deleteAction,
                'Delete message?',
                'This removes the stored dashboard message.',
            );
        }

        return [$readAction, $deleteAction];
    }

    private function deleteWithoutConfirmation(): bool
    {
        return (bool) auth()->user()?->getAttribute('dashboard_delete_without_confirmation');
    }

    private function confirmationAction(Action $action, string $heading, string $description): Action
    {
        return $this->configureConfirmation($action, $heading, $description);
    }

    private function configureConfirmation(Action $action, string $heading, string $description): Action
    {
        return $action
            ->requiresConfirmation()
            ->modalHeading($heading)
            ->modalDescription($description)
            ->modalSubmitAction(fn (Action $submit): Action => $submit
                ->label('Confirm')
                ->icon(AdminIcon::Commit->value)
                ->iconButton()
                ->extraAttributes(['class' => 'admin-dialog__header-action is-primary']))
            ->modalCancelAction(false)
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--mini admin-dialog--header-actions admin-dialog--confirmation',
            ]);
    }
}
