<?php

namespace App\Domain\Admin;

use App\Models\AdminNotification;
use App\Models\User;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\LivewireManager;

final class AdminNotifier
{
    /** @var list<string> */
    private const STATUSES = ['success', 'warning', 'danger', 'info'];

    private const HEADER_NOTIFICATION_SESSION_KEY = 'admin.header.notification';

    private const MAX_PENDING_NOTIFICATIONS = 9;

    public function __construct(private readonly AdminActivityNotificationContext $notificationContext) {}

    /**
     * Immediate notification for the current admin action.
     *
     * Livewire requests dispatch directly to the project-owned header notification surface.
     * Non-Livewire requests keep bounded pending notifications for the next admin page render.
     */
    public function notification(
        string $title,
        ?string $body = null,
        string $status = 'info',
        User|int|null $user = null,
        ?string $sourceId = null,
        array $context = [],
    ): void {
        [$title, $body, $status] = $this->normalizeMessage($title, $body, $status);
        $recipientId = $this->recipientId($user);

        if ($status === 'success') {
            $activityMessages = $this->notificationContext->consumeRecorded();

            if ($activityMessages !== []) {
                foreach ($activityMessages as $activityMessage) {
                    $auditEventId = (int) ($activityMessage['audit_event_id'] ?? 0);
                    $activityType = is_string($context['type'] ?? null) && trim((string) $context['type']) !== ''
                        ? trim((string) $context['type'])
                        : 'activity';

                    $this->recordAndSurface(
                        recipientId: $recipientId,
                        sourceId: $auditEventId > 0 ? 'activity:'.$auditEventId : null,
                        title: (string) $activityMessage['change'],
                        body: $activityMessage['details'] ?? null,
                        status: 'success',
                        context: [
                            ...$context,
                            'type' => $activityType,
                            'audit_event_id' => $auditEventId > 0 ? $auditEventId : ($context['audit_event_id'] ?? null),
                        ],
                    );
                }

                return;
            }
        } else {
            $this->notificationContext->discard();
        }

        $this->recordAndSurface(
            recipientId: $recipientId,
            sourceId: $sourceId,
            title: $title,
            body: $body,
            status: $status,
            context: $context,
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function pullPendingNotifications(): array
    {
        if (! request()->hasSession()) {
            return [];
        }

        $pending = request()->session()->pull(self::HEADER_NOTIFICATION_SESSION_KEY, []);
        if (! is_array($pending)) {
            return [];
        }

        return array_values(array_filter(
            $pending,
            static fn (mixed $message): bool => is_array($message),
        ));
    }

    /**
     * Persist one canonical Notification and project a newly created record into
     * the current admin shell when the recipient is the authenticated actor.
     *
     * @param  array<string, mixed>  $context
     */
    private function recordAndSurface(
        ?int $recipientId,
        ?string $sourceId,
        string $title,
        ?string $body,
        string $status,
        array $context = [],
    ): void {
        [$title, $body, $status] = $this->normalizeMessage($title, $body, $status);

        $messageUuid = (string) Str::orderedUuid();
        $sourceId = trim((string) $sourceId);
        if ($sourceId === '') {
            $sourceId = 'notification:'.$messageUuid;
        }

        $notification = null;

        if ($recipientId !== null) {
            $notification = AdminNotification::query()->firstOrCreate(
                [
                    'user_id' => $recipientId,
                    'source_id' => Str::limit($sourceId, 96, ''),
                ],
                [
                    'status' => $status,
                    'title' => $title,
                    'body' => $body,
                    ...$this->normalizeContext($context),
                ],
            );

            if (! $notification->wasRecentlyCreated) {
                return;
            }
        }

        $message = [
            'id' => $notification instanceof AdminNotification
                ? 'notification:'.$notification->getKey()
                : $messageUuid,
            'title' => $title,
            'body' => $body,
            'status' => $status,
        ];

        $this->surfaceNotification($recipientId, $message);
    }

    /**
     * @param  array{id:string,title:string,body:?string,status:string}  $message
     */
    private function surfaceNotification(?int $recipientId, array $message): void
    {
        if ($recipientId !== null && (int) (auth()->id() ?? 0) !== $recipientId) {
            return;
        }

        if (Livewire::isLivewireRequest()) {
            $component = app(LivewireManager::class)->current();

            if ($component instanceof Component) {
                $component->dispatch('admin-header-notification', notification: $message);

                return;
            }
        }

        $this->storePendingNotification($message);
    }

    private function recipientId(User|int|null $user): ?int
    {
        if ($user instanceof User) {
            $userId = (int) $user->getKey();

            return $userId > 0 ? $userId : null;
        }

        if (is_int($user)) {
            return $user > 0 ? $user : null;
        }

        $authenticated = auth()->user();
        if (! $authenticated instanceof User) {
            return null;
        }

        $userId = (int) $authenticated->getKey();

        return $userId > 0 ? $userId : null;
    }

    /**
     * @param  array{id:string,title:string,body:?string,status:string}  $message
     */
    private function storePendingNotification(array $message): void
    {
        if (! request()->hasSession()) {
            return;
        }

        $pending = request()->session()->get(self::HEADER_NOTIFICATION_SESSION_KEY, []);
        $pending = is_array($pending) ? array_values(array_filter($pending, 'is_array')) : [];

        if (count($pending) >= self::MAX_PENDING_NOTIFICATIONS) {
            $pending = array_slice($pending, -(self::MAX_PENDING_NOTIFICATIONS - 1));
        }

        $pending[] = $message;
        request()->session()->put(self::HEADER_NOTIFICATION_SESSION_KEY, $pending);
    }

    /** @return array{0:string,1:?string,2:string} */
    private function normalizeMessage(string $title, ?string $body, string $status): array
    {
        $title = trim(strip_tags($title));
        if ($title === '') {
            throw new InvalidArgumentException('An admin notification title is required.');
        }

        $status = strtolower(trim($status));
        if (! in_array($status, self::STATUSES, true)) {
            throw new InvalidArgumentException('Unsupported admin notification status.');
        }

        $body = $body === null ? null : trim(strip_tags($body));
        if ($body === '') {
            $body = null;
        }

        return [
            Str::limit($title, 255, ''),
            $body === null ? null : Str::limit($body, 2000, ''),
            $status,
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $type = is_string($context['type'] ?? null) ? trim($context['type']) : 'system';
        $type = preg_match('/^[a-z0-9._-]{1,64}$/', $type) === 1 ? $type : 'system';

        $actionUrl = is_string($context['action_url'] ?? null) ? trim($context['action_url']) : null;
        $actionLabel = is_string($context['action_label'] ?? null) ? trim(strip_tags($context['action_label'])) : null;
        $entityType = is_string($context['entity_type'] ?? null) ? trim($context['entity_type']) : null;
        $entityId = is_numeric($context['entity_id'] ?? null) ? (int) $context['entity_id'] : null;
        $auditEventId = is_numeric($context['audit_event_id'] ?? null) ? (int) $context['audit_event_id'] : null;
        $publicationCheckpointId = is_numeric($context['publication_checkpoint_id'] ?? null)
            ? (int) $context['publication_checkpoint_id']
            : null;
        $metadata = is_array($context['metadata'] ?? null) ? $context['metadata'] : null;

        return [
            'type' => $type,
            'action_url' => $actionUrl === null || $actionUrl === '' ? null : Str::limit($actionUrl, 2048, ''),
            'action_label' => $actionLabel === null || $actionLabel === '' ? null : Str::limit($actionLabel, 120, ''),
            'entity_type' => $entityType === null || $entityType === '' ? null : Str::limit($entityType, 64, ''),
            'entity_id' => $entityId !== null && $entityId > 0 ? $entityId : null,
            'audit_event_id' => $auditEventId !== null && $auditEventId > 0 ? $auditEventId : null,
            'publication_checkpoint_id' => $publicationCheckpointId !== null && $publicationCheckpointId > 0
                ? $publicationCheckpointId
                : null,
            'metadata' => $metadata,
        ];
    }
}
