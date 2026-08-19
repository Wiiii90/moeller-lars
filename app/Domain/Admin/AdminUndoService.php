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
        $target = match ((string) $receipt->getAttribute('entity_type')) {
            'artwork' => Artwork::query()->whereKey($entityId)->lockForUpdate()->first(),
            'cv_entry' => CvEntry::query()->whereKey($entityId)->lockForUpdate()->first(),
            'exhibition' => Exhibition::query()->whereKey($entityId)->lockForUpdate()->first(),
            default => null,
        };

        if (! $target instanceof Artwork && ! $target instanceof CvEntry && ! $target instanceof Exhibition) {
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
                'artwork.published' => $this->artworkEditorial->publish($target, recordReceipt: false),
                'artwork.unpublished' => $this->artworkEditorial->unpublish($target, recordReceipt: false),
                default => throw ValidationException::withMessages(['undo' => 'This artwork change has no reversible contract.']),
            };
        }

        return match ($inverseActionKey) {
            'cv_entry.published', 'exhibition.published' => $this->editorialRecords->publish($target, recordReceipt: false),
            'cv_entry.unpublished', 'exhibition.unpublished' => $this->editorialRecords->unpublish($target, recordReceipt: false),
            'cv_entry.archived', 'exhibition.archived' => $this->editorialRecords->archive($target, recordReceipt: false),
            'cv_entry.restored_to_draft', 'exhibition.restored_to_draft' => $this->editorialRecords->restoreDraft($target, recordReceipt: false),
            default => throw ValidationException::withMessages(['undo' => 'This editorial change has no reversible contract.']),
        };
    }
}
