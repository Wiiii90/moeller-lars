<?php

namespace App\Filament\Pages;

use App\Domain\Admin\DashboardFeed;
use App\Domain\Admin\DashboardOverview;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\View\View;

final class Dashboard extends Page
{
    private const PAGE_SIZES = [25, 50, 100];

    private const DEFAULT_PAGE_SIZE = 50;

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-home';

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
        }
    }

    public function nextFeedPage(): void
    {
        $pages = $this->feedPagination()['pages'];
        if ($this->feedPage < $pages) {
            $this->feedPage++;
        }
    }

    public function openFeedEntry(string $key): void
    {
        $entry = app(DashboardFeed::class)->openEntry($key);
        abort_unless(is_array($entry), 404);

        $this->mountAction('feedEntry', ['key' => $key]);
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
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Close')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->extraModalFooterActions(fn (array $arguments): array => $this->feedEntryFooterActions($arguments))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog']);
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
        ];
    }

    private function refreshFeedFromFirstPage(): void
    {
        $this->feedPage = 1;
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
    private function feedEntryFooterActions(array $arguments): array
    {
        $entry = $this->feedEntry($arguments);
        $contactId = $entry['contact_id'] ?? null;

        if (! is_int($contactId)) {
            return [];
        }

        return [
            Action::make('markContactUnread')
                ->label('Mark unread')
                ->color('gray')
                ->action(function () use ($contactId): void {
                    $this->markContactUnread($contactId);
                }),
            Action::make('deleteContactMessage')
                ->label('Delete')
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
}
