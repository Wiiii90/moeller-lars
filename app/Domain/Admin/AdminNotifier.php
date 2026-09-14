<?php

namespace App\Domain\Admin;

use App\Models\AdminNotification;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Support\Str;
use InvalidArgumentException;

final class AdminNotifier
{
    /** @var list<string> */
    private const STATUSES = ['success', 'warning', 'danger', 'info'];

    /**
     * Immediate feedback only. This deliberately does not persist an
     * AdminNotification row.
     */
    public function toast(
        string $title,
        ?string $body = null,
        string $status = 'info',
    ): void {
        [$title, $body, $status] = $this->normalizeMessage($title, $body, $status);

        $notification = Notification::make()->title($title);
        if ($body !== null) {
            $notification->body($body);
        }

        match ($status) {
            'success' => $notification->success(),
            'warning' => $notification->warning(),
            'danger' => $notification->danger(),
            default => $notification->info(),
        };

        $notification->send();
    }

    /**
     * Persist information that deserves later attention. This method does not
     * require a browser or a rendered Filament notification.
     *
     * Supported context keys:
     * type, action_url, action_label, entity_type, entity_id,
     * audit_event_id, publication_checkpoint_id, metadata.
     *
     * @param array<string, mixed> $context
     */
    public function inbox(
        User|int $user,
        string $sourceId,
        string $title,
        ?string $body = null,
        string $status = 'info',
        array $context = [],
    ): AdminNotification {
        $userId = $user instanceof User ? (int) $user->getKey() : $user;
        if ($userId <= 0) {
            throw new InvalidArgumentException('A valid admin notification recipient is required.');
        }

        $sourceId = trim($sourceId);
        if ($sourceId === '') {
            throw new InvalidArgumentException('A persistent admin notification requires a source id.');
        }

        [$title, $body, $status] = $this->normalizeMessage($title, $body, $status);
        $attributes = $this->normalizeContext($context);

        return AdminNotification::query()->firstOrCreate(
            [
                'user_id' => $userId,
                'source_id' => Str::limit($sourceId, 96, ''),
            ],
            [
                'status' => $status,
                'title' => $title,
                'body' => $body,
                ...$attributes,
            ],
        );
    }

    /**
     * Persist the notification and also show immediate feedback in the current
     * request. Background jobs should normally use inbox() instead.
     *
     * @param array<string, mixed> $context
     */
    public function both(
        User|int $user,
        string $sourceId,
        string $title,
        ?string $body = null,
        string $status = 'info',
        array $context = [],
    ): AdminNotification {
        $notification = $this->inbox($user, $sourceId, $title, $body, $status, $context);
        $this->toast($title, $body, $status);

        return $notification;
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
     * @param array<string, mixed> $context
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
