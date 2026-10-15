<?php

namespace App\Domain\Contact;

use App\Models\ContactMessage;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Database-only contact retention. Mailboxes and off-site backups have
 * independent lifecycles and are not touched by this operation.
 */
final class ContactMessageRetentionService
{
    public const RETENTION_DAYS = 180;

    private const DELETE_BATCH_SIZE = 500;

    public function cutoff(): CarbonImmutable
    {
        return CarbonImmutable::now()->subDays(self::RETENTION_DAYS);
    }

    public function eligibleCount(?CarbonInterface $cutoff = null): int
    {
        return ContactMessage::query()
            ->where('created_at', '<', $cutoff ?? $this->cutoff())
            ->count();
    }

    public function purge(?CarbonInterface $cutoff = null): int
    {
        $cutoff ??= $this->cutoff();
        $deleted = 0;

        do {
            // Query only IDs, not personal content. Bulk deletion intentionally
            // avoids creating new audit copies of information being erased.
            $ids = ContactMessage::query()
                ->where('created_at', '<', $cutoff)
                ->orderBy('id')
                ->limit(self::DELETE_BATCH_SIZE)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                break;
            }

            $deleted += ContactMessage::query()
                ->whereIn('id', $ids)
                ->where('created_at', '<', $cutoff)
                ->delete();
        } while (count($ids) === self::DELETE_BATCH_SIZE);

        return $deleted;
    }
}
