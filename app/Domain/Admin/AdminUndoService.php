<?php

namespace App\Domain\Admin;

use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\AdminActionReceipt;
use App\Models\Artwork;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AdminUndoService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly ArtworkEditorialService $artworkEditorial,
        private readonly EditorialRecordService $editorialRecords,
    ) {}

    /** @return array{action:string,inverse:string} */
    public function undo(int $receiptId): array
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($receiptId, $actor): array {
            /** @var AdminActionReceipt|null $receipt */
            $receipt = AdminActionReceipt::query()
                ->whereKey($receiptId)
                ->lockForUpdate()
                ->first();

            if (! $receipt) {
                throw ValidationException::withMessages(['undo' => 'This Undo receipt no longer exists.']);
            }

            $this->assertAvailableReceipt($receipt, $actor);
            $target = $this->lockedTarget($receipt);
            $expectedState = (string) $receipt->getAttribute('after_state');

            if ((string) $target->getAttribute('state') !== $expectedState) {
                throw ValidationException::withMessages([
                    'undo' => 'Undo is no longer available because this item changed afterwards.',
                ]);
            }

            $inverseActionKey = (string) $receipt->getAttribute('inverse_action_key');
            $result = $this->executeInverse($target, $inverseActionKey);
            $expectedRestoredState = (string) $receipt->getAttribute('before_state');

            if ((string) $result->getAttribute('state') !== $expectedRestoredState) {
                throw new RuntimeException('The domain inverse did not restore the receipt state.');
            }

            $receipt->setAttribute('undone_at', now());
            $receipt->save();

            return [
                'action' => AdminActionCatalog::definition((string) $receipt->getAttribute('action_key'))['label'],
                'inverse' => AdminActionCatalog::definition($inverseActionKey)['label'],
            ];
        });
    }

    private function assertAvailableReceipt(AdminActionReceipt $receipt, User $actor): void
    {
        if ((int) $receipt->getAttribute('admin_user_id') !== (int) $actor->getKey()) {
            throw new AuthorizationException('This Undo receipt belongs to another admin.');
        }

        if ((int) $receipt->getAttribute('receipt_version') !== AdminActionReceiptService::RECEIPT_VERSION) {
            throw ValidationException::withMessages(['undo' => 'This Undo receipt uses an unsupported version.']);
        }

        if ($receipt->getAttribute('undone_at') !== null) {
            throw ValidationException::withMessages(['undo' => 'This change has already been undone.']);
        }

        $expiresAt = $receipt->getAttribute('expires_at');
        if ($expiresAt === null || $expiresAt->isPast()) {
            throw ValidationException::withMessages(['undo' => 'This Undo receipt has expired.']);
        }
    }

    private function lockedTarget(AdminActionReceipt $receipt): Artwork|CvEntry|Exhibition
    {
        $entityId = (int) $receipt->getAttribute('entity_id');

        return match ((string) $receipt->getAttribute('entity_type')) {
            'artwork' => $this->lockedArtwork($entityId),
            'cv_entry' => $this->lockedCvEntry($entityId),
            'exhibition' => $this->lockedExhibition($entityId),
            default => throw ValidationException::withMessages(['undo' => 'The target of this change no longer exists.']),
        };
    }

    private function lockedArtwork(int $entityId): Artwork
    {
        /** @var Artwork|null $target */
        $target = Artwork::query()->whereKey($entityId)->lockForUpdate()->first();

        if (! $target) {
            throw ValidationException::withMessages(['undo' => 'The target of this change no longer exists.']);
        }

        return $target;
    }

    private function lockedCvEntry(int $entityId): CvEntry
    {
        /** @var CvEntry|null $target */
        $target = CvEntry::query()->whereKey($entityId)->lockForUpdate()->first();

        if (! $target) {
            throw ValidationException::withMessages(['undo' => 'The target of this change no longer exists.']);
        }

        return $target;
    }

    private function lockedExhibition(int $entityId): Exhibition
    {
        /** @var Exhibition|null $target */
        $target = Exhibition::query()->whereKey($entityId)->lockForUpdate()->first();

        if (! $target) {
            throw ValidationException::withMessages(['undo' => 'The target of this change no longer exists.']);
        }

        return $target;
    }

    private function executeInverse(
        Artwork|CvEntry|Exhibition $target,
        string $inverseActionKey,
    ): Artwork|CvEntry|Exhibition {
        if ($target instanceof Artwork) {
            return match ($inverseActionKey) {
                'artwork.published' => $this->artworkEditorial->publish($target),
                'artwork.unpublished' => $this->artworkEditorial->unpublish($target),
                default => throw ValidationException::withMessages(['undo' => 'This artwork change has no reversible contract.']),
            };
        }

        return match ($inverseActionKey) {
            'cv_entry.published', 'exhibition.published' => $this->editorialRecords->publish($target),
            'cv_entry.unpublished', 'exhibition.unpublished' => $this->editorialRecords->unpublish($target),
            default => throw ValidationException::withMessages(['undo' => 'This editorial change has no reversible contract.']),
        };
    }
}
