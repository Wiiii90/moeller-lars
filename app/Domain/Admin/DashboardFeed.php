<?php

namespace App\Domain\Admin;

use App\Models\AdminNotification;
use App\Models\ContactMessage;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class DashboardFeed
{
    /** @return array<string, string> */
    public static function types(): array
    {
        return [
            'all' => 'All',
            'announcement' => 'Announcements',
            'changelog' => 'Changelog',
            'contact' => 'Contact',
            'notification' => 'Notifications',
        ];
    }

    /**
     * @return array{
     *   items:list<array<string,mixed>>,
     *   page:int,
     *   per_page:int,
     *   total:int,
     *   pages:int,
     *   start:int,
     *   end:int
     * }
     */
    public function paginate(string $search = '', string $type = 'all', int $page = 1, int $perPage = 50): array
    {
        $type = array_key_exists($type, self::types()) ? $type : 'all';
        $perPage = in_array($perPage, [25, 50, 100], true) ? $perPage : 50;
        $search = trim($search);

        if ($type === 'contact') {
            return $this->paginateContacts($search, $page, $perPage);
        }

        if ($type === 'notification') {
            return $this->paginateNotifications($search, $page, $perPage);
        }

        if (in_array($type, ['announcement', 'changelog'], true)) {
            return $this->paginateStatic($search, $type, $page, $perPage);
        }

        return $this->paginateAll($search, $page, $perPage);
    }

    /** @return array<string, mixed>|null */
    public function openEntry(string $key): ?array
    {
        $entry = $this->entry($key);
        if (! is_array($entry)) {
            return null;
        }

        $contactId = $entry['contact_id'] ?? null;
        if (is_int($contactId)) {
            $message = ContactMessage::query()->find($contactId);
            if ($message instanceof ContactMessage) {
                $message->markRead();

                return $this->projectContact($message->refresh());
            }
        }

        $notificationId = $entry['notification_id'] ?? null;
        if (is_int($notificationId)) {
            $notification = $this->notificationForUser($notificationId);
            if ($notification instanceof AdminNotification) {
                $notification->markRead();

                return $this->projectNotification($notification->refresh());
            }
        }

        return $entry;
    }

    public function markContactUnread(int $contactMessageId): void
    {
        ContactMessage::query()->findOrFail($contactMessageId)->markUnread();
    }

    public function deleteContact(int $contactMessageId): void
    {
        ContactMessage::query()->findOrFail($contactMessageId)->delete();
    }

    public function markNotificationUnread(int $notificationId): void
    {
        $this->notificationForUser($notificationId, true)->markUnread();
    }

    public function deleteNotification(int $notificationId): void
    {
        $this->notificationForUser($notificationId, true)->delete();
    }

    /** @return array<string, mixed>|null */
    public function entry(string $key): ?array
    {
        if (str_starts_with($key, 'contact:')) {
            $id = substr($key, strlen('contact:'));
            if (! ctype_digit($id)) {
                return null;
            }

            $message = ContactMessage::query()->find((int) $id);

            return $message instanceof ContactMessage ? $this->projectContact($message) : null;
        }

        if (str_starts_with($key, 'notification:')) {
            $id = substr($key, strlen('notification:'));
            if (! ctype_digit($id)) {
                return null;
            }

            $notification = $this->notificationForUser((int) $id);

            return $notification instanceof AdminNotification ? $this->projectNotification($notification) : null;
        }

        if (! str_starts_with($key, 'static:')) {
            return null;
        }

        $id = substr($key, strlen('static:'));

        return $this->staticItems()
            ->first(static fn (array $item): bool => $item['id'] === $id);
    }

    /** @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int,start:int,end:int} */
    private function paginateContacts(string $search, int $page, int $perPage): array
    {
        $query = $this->contactQuery($search);
        $total = (clone $query)->count();
        [$page, $pages, $offset] = $this->pageState($total, $page, $perPage);

        $items = $this->orderedContacts($query)
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (ContactMessage $message): array => $this->projectContact($message))
            ->values()
            ->all();

        return $this->paginationResult($items, $page, $perPage, $total, $pages, $offset);
    }

    /** @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int,start:int,end:int} */
    private function paginateNotifications(string $search, int $page, int $perPage): array
    {
        $query = $this->notificationQuery($search);
        $total = (clone $query)->count();
        [$page, $pages, $offset] = $this->pageState($total, $page, $perPage);

        $items = $this->orderedNotifications($query)
            ->offset($offset)
            ->limit($perPage)
            ->get()
            ->map(fn (AdminNotification $notification): array => $this->projectNotification($notification))
            ->values()
            ->all();

        return $this->paginationResult($items, $page, $perPage, $total, $pages, $offset);
    }

    /** @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int,start:int,end:int} */
    private function paginateStatic(string $search, string $type, int $page, int $perPage): array
    {
        $items = $this->filteredStaticItems($search, $type);
        $total = $items->count();
        [$page, $pages, $offset] = $this->pageState($total, $page, $perPage);
        $visible = $items->slice($offset, $perPage)->values()->all();

        return $this->paginationResult($visible, $page, $perPage, $total, $pages, $offset);
    }

    /**
     * Merge the tiny static source with a bounded SQL union of Contacts and
     * persisted admin notifications. Only the dynamic rows required to
     * reconstruct the requested global page are hydrated.
     *
     * @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int,start:int,end:int}
     */
    private function paginateAll(string $search, int $page, int $perPage): array
    {
        $staticItems = $this->filteredStaticItems($search);
        $staticCount = $staticItems->count();
        $dynamicQuery = $this->dynamicIdentityQuery($search);
        $dynamicTotal = (clone $dynamicQuery)->count();
        $total = $staticCount + $dynamicTotal;
        [$page, $pages, $offset] = $this->pageState($total, $page, $perPage);

        $dynamicOffset = max(0, $offset - $staticCount);
        $dynamicLimit = $perPage + $staticCount;
        $identityRows = (clone $dynamicQuery)
            ->orderByDesc('sort_at')
            ->orderByDesc('source_id')
            ->orderBy('source_type')
            ->offset($dynamicOffset)
            ->limit($dynamicLimit)
            ->get();

        $contactIds = $identityRows
            ->where('source_type', 'contact')
            ->pluck('source_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();
        $notificationIds = $identityRows
            ->where('source_type', 'notification')
            ->pluck('source_id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        $contacts = ContactMessage::query()->whereIn('id', $contactIds)->get()->keyBy('id');
        $notifications = AdminNotification::query()
            ->where('user_id', $this->userId() ?? 0)
            ->whereIn('id', $notificationIds)
            ->get()
            ->keyBy('id');

        $dynamicItems = $identityRows->map(function (object $row) use ($contacts, $notifications): ?array {
            $id = (int) $row->source_id;

            if ($row->source_type === 'contact') {
                $message = $contacts->get($id);

                return $message instanceof ContactMessage ? $this->projectContact($message) : null;
            }

            $notification = $notifications->get($id);

            return $notification instanceof AdminNotification ? $this->projectNotification($notification) : null;
        })->filter()->values();

        $merged = $this->sortItems($staticItems->concat($dynamicItems)->values()->all());
        $visible = array_slice($merged, $offset - $dynamicOffset, $perPage);

        return $this->paginationResult($visible, $page, $perPage, $total, $pages, $offset);
    }

    /** @return Builder<ContactMessage> */
    private function contactQuery(string $search): Builder
    {
        $query = ContactMessage::query();

        if ($search === '') {
            return $query;
        }

        $pattern = '%'.$search.'%';

        return $query->where(static function (Builder $match) use ($pattern): void {
            $match->where('sender_name', 'ilike', $pattern)
                ->orWhere('sender_email', 'ilike', $pattern)
                ->orWhere('message', 'ilike', $pattern);
        });
    }

    /** @return Builder<AdminNotification> */
    private function notificationQuery(string $search): Builder
    {
        $query = AdminNotification::query()->where('user_id', $this->userId() ?? 0);
        $filter = $this->notificationFilter();

        if ($filter !== 'all') {
            $query->where('status', $filter);
        }

        if ($search === '') {
            return $query;
        }

        $pattern = '%'.$search.'%';

        return $query->where(static function (Builder $match) use ($pattern): void {
            $match->where('title', 'ilike', $pattern)
                ->orWhere('body', 'ilike', $pattern)
                ->orWhere('status', 'ilike', $pattern);
        });
    }

    private function dynamicIdentityQuery(string $search): QueryBuilder
    {
        $pattern = '%'.$search.'%';
        $contacts = DB::table('contact_messages')
            ->selectRaw("'contact' as source_type, id as source_id, created_at as sort_at");
        if ($search !== '') {
            $contacts->where(static function (QueryBuilder $match) use ($pattern): void {
                $match->where('sender_name', 'ilike', $pattern)
                    ->orWhere('sender_email', 'ilike', $pattern)
                    ->orWhere('message', 'ilike', $pattern);
            });
        }

        $notifications = DB::table('admin_notifications')
            ->selectRaw("'notification' as source_type, id as source_id, created_at as sort_at")
            ->where('user_id', $this->userId() ?? 0);
        $filter = $this->notificationFilter();
        if ($filter !== 'all') {
            $notifications->where('status', $filter);
        }
        if ($search !== '') {
            $notifications->where(static function (QueryBuilder $match) use ($pattern): void {
                $match->where('title', 'ilike', $pattern)
                    ->orWhere('body', 'ilike', $pattern)
                    ->orWhere('status', 'ilike', $pattern);
            });
        }

        return DB::query()->fromSub($contacts->unionAll($notifications), 'dashboard_feed_dynamic');
    }

    /** @param Builder<ContactMessage> $query
     * @return Builder<ContactMessage>
     */
    private function orderedContacts(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** @param Builder<AdminNotification> $query
     * @return Builder<AdminNotification>
     */
    private function orderedNotifications(Builder $query): Builder
    {
        return $query
            ->orderByDesc('created_at')
            ->orderByDesc('id');
    }

    /** @return Collection<int, array<string, mixed>> */
    private function filteredStaticItems(string $search, ?string $type = null): Collection
    {
        $items = $this->staticItems();

        if ($type !== null) {
            $items = $items->filter(static fn (array $item): bool => $item['type'] === $type);
        }

        $term = Str::lower($search);
        if ($term !== '') {
            $items = $items->filter(static function (array $item) use ($term): bool {
                return Str::contains(Str::lower(implode(' ', [
                    (string) ($item['title'] ?? ''),
                    (string) ($item['body'] ?? ''),
                ])), $term);
            });
        }

        return collect($this->sortItems($items->values()->all()));
    }

    /** @param list<array<string, mixed>> $items
     * @return list<array<string, mixed>>
     */
    private function sortItems(array $items): array
    {
        usort($items, static function (array $left, array $right): int {
            $timestamp = ((int) $right['sort_timestamp']) <=> ((int) $left['sort_timestamp']);
            if ($timestamp !== 0) {
                return $timestamp;
            }

            $microsecond = ((int) $right['sort_microsecond']) <=> ((int) $left['sort_microsecond']);
            if ($microsecond !== 0) {
                return $microsecond;
            }

            return strcmp((string) $right['key'], (string) $left['key']);
        });

        return array_values($items);
    }

    /** @return array{0:int,1:int,2:int} */
    private function pageState(int $total, int $page, int $perPage): array
    {
        $pages = max(1, (int) ceil($total / $perPage));
        $page = min(max(1, $page), $pages);

        return [$page, $pages, ($page - 1) * $perPage];
    }

    /** @param list<array<string, mixed>> $items
     * @return array{items:list<array<string,mixed>>,page:int,per_page:int,total:int,pages:int,start:int,end:int}
     */
    private function paginationResult(array $items, int $page, int $perPage, int $total, int $pages, int $offset): array
    {
        return [
            'items' => $items,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'pages' => $pages,
            'start' => $total === 0 ? 0 : $offset + 1,
            'end' => $total === 0 ? 0 : min($total, $offset + count($items)),
        ];
    }

    /** @return Collection<int, array<string, mixed>> */
    private function staticItems(): Collection
    {
        return collect(config('dashboard-feed.items', []))
            ->filter(static fn (mixed $item): bool => is_array($item))
            ->map(function (array $item): ?array {
                $id = is_string($item['id'] ?? null) ? trim($item['id']) : '';
                $type = is_string($item['type'] ?? null) ? trim($item['type']) : '';
                $date = is_string($item['date'] ?? null) ? trim($item['date']) : '';
                $title = is_string($item['title'] ?? null) ? trim($item['title']) : '';
                $body = is_string($item['body'] ?? null) ? trim($item['body']) : '';

                if ($id === '' || ! in_array($type, ['announcement', 'changelog'], true) || $date === '' || $title === '' || $body === '') {
                    return null;
                }

                try {
                    $parsedDate = Carbon::parse($date)->startOfDay();
                } catch (\Throwable) {
                    return null;
                }

                $link = is_string($item['link'] ?? null) && trim($item['link']) !== '' ? trim($item['link']) : null;
                $linkLabel = is_string($item['link_label'] ?? null) && trim($item['link_label']) !== '' ? trim($item['link_label']) : null;

                return [
                    'key' => 'static:'.$id,
                    'id' => $id,
                    'contact_id' => null,
                    'notification_id' => null,
                    'notification_status' => null,
                    'type' => $type,
                    'type_label' => $type === 'announcement' ? 'Announcement' : 'Changelog',
                    'sort_at' => $parsedDate->toIso8601String(),
                    'sort_timestamp' => $parsedDate->getTimestamp(),
                    'sort_microsecond' => (int) $parsedDate->format('u'),
                    'date' => $parsedDate->toDateString(),
                    'date_display' => $parsedDate->format('M j, Y'),
                    'title' => $title,
                    'sender' => '—',
                    'sender_name' => '',
                    'sender_email' => '',
                    'message_excerpt' => Str::limit($body, 120),
                    'body' => $body,
                    'status' => '—',
                    'mail_delivery_status' => null,
                    'mail_delivered_at' => null,
                    'link' => $link,
                    'link_label' => $linkLabel,
                ];
            })
            ->filter()
            ->values();
    }

    /** @return array<string, mixed> */
    private function projectContact(ContactMessage $message): array
    {
        $createdAt = $message->getAttribute('created_at');
        $receivedAt = $createdAt instanceof CarbonInterface ? $createdAt : now();
        $senderName = (string) $message->getAttribute('sender_name');
        $senderEmail = (string) $message->getAttribute('sender_email');
        $body = (string) $message->getAttribute('message');
        $readAt = $message->getAttribute('read_at');
        $deliveredAt = $message->getAttribute('mail_delivered_at');

        return [
            'key' => 'contact:'.$message->getKey(),
            'id' => (string) $message->getKey(),
            'contact_id' => (int) $message->getKey(),
            'notification_id' => null,
            'notification_status' => null,
            'type' => 'contact',
            'type_label' => self::types()['contact'],
            'sort_at' => $receivedAt->toIso8601String(),
            'sort_timestamp' => $receivedAt->getTimestamp(),
            'sort_microsecond' => (int) $receivedAt->format('u'),
            'date' => $receivedAt->toIso8601String(),
            'date_display' => $receivedAt->format('M j, Y · H:i'),
            'title' => 'Contact message',
            'sender' => trim($senderName.' · '.$senderEmail),
            'sender_name' => $senderName,
            'sender_email' => $senderEmail,
            'message_excerpt' => Str::limit(preg_replace('/\s+/u', ' ', trim($body)) ?: $body, 120),
            'body' => $body,
            'status' => $readAt === null ? 'Unread' : 'Read',
            'mail_delivery_status' => (string) $message->getAttribute('mail_delivery_status'),
            'mail_delivered_at' => $deliveredAt instanceof CarbonInterface ? $deliveredAt->format('M j, Y · H:i') : null,
            'link' => null,
            'link_label' => null,
        ];
    }

    /** @return array<string, mixed> */
    private function projectNotification(AdminNotification $notification): array
    {
        $createdAt = $notification->getAttribute('created_at');
        $receivedAt = $createdAt instanceof CarbonInterface ? $createdAt : now();
        $body = trim((string) ($notification->getAttribute('body') ?? ''));
        $status = (string) $notification->getAttribute('status');
        $readAt = $notification->getAttribute('read_at');
        $title = (string) $notification->getAttribute('title');

        return [
            'key' => 'notification:'.$notification->getKey(),
            'id' => (string) $notification->getKey(),
            'contact_id' => null,
            'notification_id' => (int) $notification->getKey(),
            'notification_status' => $status,
            'type' => 'notification',
            'type_label' => self::types()['notification'],
            'sort_at' => $receivedAt->toIso8601String(),
            'sort_timestamp' => $receivedAt->getTimestamp(),
            'sort_microsecond' => (int) $receivedAt->format('u'),
            'date' => $receivedAt->toIso8601String(),
            'date_display' => $receivedAt->format('M j, Y · H:i'),
            'title' => $title,
            'sender' => 'Admin',
            'sender_name' => 'Admin',
            'sender_email' => '',
            'message_excerpt' => Str::limit($body !== '' ? preg_replace('/\s+/u', ' ', $body) : $title, 120),
            'body' => $body !== '' ? $body : $title,
            'status' => ($readAt === null ? 'Unread' : 'Read').' · '.ucfirst($status),
            'mail_delivery_status' => null,
            'mail_delivered_at' => null,
            'link' => null,
            'link_label' => null,
        ];
    }

    private function notificationForUser(int $notificationId, bool $fail = false): ?AdminNotification
    {
        $query = AdminNotification::query()
            ->where('user_id', $this->userId() ?? 0)
            ->whereKey($notificationId);

        if ($fail) {
            /** @var AdminNotification $notification */
            $notification = $query->firstOrFail();

            return $notification;
        }

        /** @var AdminNotification|null $notification */
        $notification = $query->first();

        return $notification;
    }

    private function userId(): ?int
    {
        $id = auth()->id();

        return is_numeric($id) ? (int) $id : null;
    }

    private function notificationFilter(): string
    {
        $filter = auth()->user()?->getAttribute('dashboard_notification_filter');

        return is_string($filter) && in_array($filter, ['all', 'success', 'warning', 'danger'], true)
            ? $filter
            : 'all';
    }
}
