<?php

namespace App\Domain\Admin;

use App\Models\AuditEvent;

final class AdminActivityNotificationContext
{
    private const REQUEST_ATTRIBUTE = 'admin.activity.notification';

    private const MAX_MESSAGES = 9;

    /**
     * @param array{change:string,details:?string} $presentation
     */
    public function push(int $auditEventId, array $presentation): void
    {
        if ($auditEventId < 1) {
            return;
        }

        $messages = array_values(array_filter(
            $this->messages(),
            static fn (mixed $message): bool => is_array($message)
                && (int) ($message['audit_event_id'] ?? 0) !== $auditEventId,
        ));

        $messages[] = [
            'audit_event_id' => $auditEventId,
            'change' => trim($presentation['change']),
            'details' => is_string($presentation['details'] ?? null)
                ? trim((string) $presentation['details'])
                : null,
        ];

        if (count($messages) > self::MAX_MESSAGES) {
            $messages = array_slice($messages, -self::MAX_MESSAGES);
        }

        request()->attributes->set(self::REQUEST_ATTRIBUTE, $messages);
    }

    public function discard(): void
    {
        request()->attributes->remove(self::REQUEST_ATTRIBUTE);
    }

    /**
     * Return only messages whose append-only Audit event is still present.
     *
     * @return list<array{audit_event_id:int,change:string,details:?string}>
     */
    public function consumeRecorded(): array
    {
        $messages = $this->messages();
        request()->attributes->remove(self::REQUEST_ATTRIBUTE);

        if ($messages === []) {
            return [];
        }

        $ids = array_values(array_unique(array_filter(
            array_map(
                static fn (mixed $message): int => is_array($message)
                    ? (int) ($message['audit_event_id'] ?? 0)
                    : 0,
                $messages,
            ),
            static fn (int $id): bool => $id > 0,
        )));

        if ($ids === []) {
            return [];
        }

        $existing = AuditEvent::query()
            ->whereKey($ids)
            ->pluck('id')
            ->mapWithKeys(static fn (mixed $id): array => [(int) $id => true])
            ->all();

        return array_values(array_filter(
            $messages,
            static fn (mixed $message): bool => is_array($message)
                && isset($existing[(int) ($message['audit_event_id'] ?? 0)])
                && is_string($message['change'] ?? null)
                && trim((string) $message['change']) !== '',
        ));
    }

    /** @return list<array<string,mixed>> */
    private function messages(): array
    {
        $messages = request()->attributes->get(self::REQUEST_ATTRIBUTE, []);

        return is_array($messages) ? array_values($messages) : [];
    }
}
