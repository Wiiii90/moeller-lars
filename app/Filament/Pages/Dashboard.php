<?php

namespace App\Filament\Pages;

use App\Domain\Admin\DashboardFeed;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\DashboardOverview;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
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

    public function markFeedRead(string $key): void
    {
        if ($this->mutableFeedEntry($key) === null) {
            return;
        }

        app(DashboardFeed::class)->openEntry($key);
    }

    public function markFeedUnread(string $key): void
    {
        $entry = $this->mutableFeedEntry($key);
        if ($entry === null) {
            return;
        }

        $feed = app(DashboardFeed::class);
        $contactId = $entry['contact_id'] ?? null;
        if (is_int($contactId)) {
            $feed->markContactUnread($contactId);

            return;
        }

        $notificationId = $entry['notification_id'] ?? null;
        if (is_int($notificationId)) {
            $feed->markNotificationUnread($notificationId);
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
        if (count($selectedVisible) === count($visibleKeys)) {
            $this->selectedFeedKeys = array_values(array_diff($this->selectedFeedKeys, $visibleKeys));

            return;
        }

        $this->selectedFeedKeys = array_values(array_unique([...$this->selectedFeedKeys, ...$visibleKeys]));
    }

    public function bulkMarkRead(): void
    {
        $feed = app(DashboardFeed::class);
        foreach ($this->selectedFeedKeys as $key) {
            if ($this->mutableFeedEntry($key) !== null) {
                $feed->openEntry($key);
            }
        }

        $this->selectedFeedKeys = [];
    }

    public function bulkMarkUnread(): void
    {
        foreach ($this->selectedFeedKeys as $key) {
            $this->markFeedUnread($key);
        }

        $this->selectedFeedKeys = [];
    }

    public function bulkDelete(): void
    {
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

        Notification::make()
            ->title('Contact message marked unread')
            ->success()
            ->send();
    }

    public function deleteContactMessage(int $contactMessageId): void
    {
        app(DashboardFeed::class)->deleteContact($contactMessageId);

        Notification::make()
            ->title('Contact message deleted')
            ->success()
            ->send();

        $this->feedPage = $this->feedPagination()['page'];
    }

    public function markNotificationUnread(int $notificationId): void
    {
        app(DashboardFeed::class)->markNotificationUnread($notificationId);
    }

    public function deleteNotification(int $notificationId): void
    {
        app(DashboardFeed::class)->deleteNotification($notificationId);
        $this->feedPage = $this->feedPagination()['page'];
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
        $feedPagination = $this->feedPagination();

        $this->feedPage = $feedPagination['page'];
        $this->feedPageSize = $feedPagination['per_page'];

        return [
            ...$overview,
            'feed' => $feedPagination['items'],
            'feedTypes' => DashboardFeed::types(),
            'feedPagination' => $feedPagination,
            'notificationFilters' => self::NOTIFICATION_FILTERS,
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

    /** @return list<string> */
    private function currentSelectableFeedKeys(): array
    {
        return collect($this->feedPagination()['items'])
            ->filter(fn (array $item): bool => $this->isMutableFeedEntry($item))
            ->pluck('key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')
            ->values()
            ->all();
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
        $contactId = $entry['contact_id'] ?? null;
        if (is_int($contactId)) {
            $feed->deleteContact($contactId);

            return;
        }

        $notificationId = $entry['notification_id'] ?? null;
        if (is_int($notificationId)) {
            $feed->deleteNotification($notificationId);
        }
    }

    /** @param array<string, mixed> $arguments
     * @return array<string, mixed>
     */
    private function feedEntry(array $arguments): array
    {
        $key = is_string($arguments['key'] ?? null) ? $arguments['key'] : '';
        $entry = app(DashboardFeed::class)->entry($key);
        abort_unless(is_array($entry), 404);

        return $entry;
    }

    /** @param array<string, mixed> $arguments
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
            ->tooltip($isRead ? 'Mark unread' : 'Mark read')
            ->color('gray')
            ->action(function () use ($key, $isRead): void {
                if ($isRead) {
                    $this->markFeedUnread($key);

                    return;
                }

                $this->markFeedRead($key);
            });

        $contactId = $entry['contact_id'] ?? null;
        if (is_int($contactId)) {
            return [
                $readAction,
                Action::make('deleteContactMessage')
                    ->label('Delete')
                    ->icon(AdminIcon::Delete->value)
                    ->iconButton()
                    ->tooltip('Delete')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Delete contact message?')
                    ->modalDescription('This removes the locally stored inbox message. This cannot be undone.')
                    ->modalSubmitActionLabel('Delete')
                    ->cancelParentActions('feedEntry')
                    ->action(function () use ($contactId): void {
                        $this->deleteContactMessage($contactId);
                    }),
            ];
        }

        $notificationId = $entry['notification_id'] ?? null;
        if (! is_int($notificationId)) {
            return [$readAction];
        }

        return [
            $readAction,
            Action::make('deleteNotification')
                ->label('Delete')
                ->icon(AdminIcon::Delete->value)
                ->iconButton()
                ->tooltip('Delete')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('Delete notification?')
                ->modalDescription('This removes the stored dashboard notification history entry.')
                ->modalSubmitActionLabel('Delete')
                ->cancelParentActions('feedEntry')
                ->action(function () use ($notificationId): void {
                    $this->deleteNotification($notificationId);
                }),
        ];
    }
}
