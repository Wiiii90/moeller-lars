<?php

namespace App\Domain\Admin;

use App\Domain\Publication\PublicationSnapshot;
use App\Models\AdminActionReceipt;
use App\Models\AdminActivityOrderingEvent;
use App\Models\AdminActivityOrderingProjection;
use App\Models\Artwork;
use App\Models\Exhibition;
use App\Models\PublicationCheckpointEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AdminUndoService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly AdminLifecycleRegistry $lifecycle,
        private readonly AdminSnapshotReceiptService $snapshots,
        private readonly AdminUndoContext $undoContext,
        private readonly AdminOrderingState $orderingState,
    ) {}

    /** @return array{action:string,inverse:string} */
    public function undo(int $receiptId): array
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($receiptId, $actor): array {
            // Commit/reclaim acquire this lock before touching receipts and
            // publication tables. Undo must follow the same lock order.
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);

            /** @var AdminActionReceipt|null $receipt */
            $receipt = AdminActionReceipt::query()->whereKey($receiptId)->lockForUpdate()->first();
            if (! $receipt) {
                throw ValidationException::withMessages(['undo' => 'This Undo receipt no longer exists.']);
            }

            $this->assertAvailableReceipt($receipt, $actor);
            $actionKey = (string) $receipt->getAttribute('action_key');
            $inverseActionKey = (string) $receipt->getAttribute('inverse_action_key');

            $this->undoContext->withoutReceipts(function () use ($receipt, $actor, $actionKey, $inverseActionKey): void {
                if (AdminActionCatalog::definition($actionKey)['family'] === 'ordering') {
                    /** @var AdminActivityOrderingEvent|null $orderingEvent */
                    $orderingEvent = AdminActivityOrderingEvent::query()
                        ->with('projection')
                        ->whereKey((int) $receipt->getAttribute('audit_event_id'))
                        ->first();
                    $projection = $orderingEvent?->getRelationValue('projection');

                    if (! ($projection instanceof AdminActivityOrderingProjection)
                        || $projection->isIdentity()
                        || (int) $projection->getAttribute('last_audit_event_id') !== (int) $receipt->getAttribute('audit_event_id')) {
                        throw ValidationException::withMessages(['undo' => 'This ordering Undo is no longer attached to the current net change.']);
                    }

                    $scope = (string) $projection->getAttribute('scope');
                    $before = $projection->afterState();
                    $after = $projection->beforeState();
                    $this->orderingState->restore($scope, $before, $after);

                    /** @var \App\Models\AuditEvent $sourceEvent */
                    $sourceEvent = $orderingEvent->auditEvent()->firstOrFail();
                    $this->audit->recordOrdering(
                        $actor,
                        $actionKey,
                        (string) $sourceEvent->getAttribute('entity_type'),
                        (int) $sourceEvent->getAttribute('entity_id'),
                        $scope,
                        AdminOrderingState::denormalize($before),
                        AdminOrderingState::denormalize($after),
                    );
                    $this->audit->record(
                        $actor,
                        'admin.undo_applied',
                        (string) $sourceEvent->getAttribute('entity_type'),
                        (int) $sourceEvent->getAttribute('entity_id'),
                        ['source_audit_event_id' => (int) $sourceEvent->getKey()],
                    );

                    return;
                }

                if ($this->snapshots->isSnapshotReceipt($receipt)) {
                    $this->snapshots->restore($receipt);
                    $this->audit->record(
                        $actor,
                        'admin.undo_applied',
                        (string) $receipt->getAttribute('entity_type'),
                        (int) $receipt->getAttribute('entity_id'),
                        ['source_audit_event_id' => (int) $receipt->getAttribute('audit_event_id')],
                    );

                    return;
                }

                $expectedState = (string) $receipt->getAttribute('after_state');
                if ((string) $target->getAttribute('state') !== $expectedState) {
                    throw ValidationException::withMessages(['undo' => 'Undo is no longer available because this item changed afterwards.']);
                }

                $result = $this->lifecycle->applyInverse($target, $inverseActionKey);
                $expectedRestoredState = (string) $receipt->getAttribute('before_state');
                if ((string) $result->getAttribute('state') !== $expectedRestoredState) {
                    throw new RuntimeException('The domain inverse did not restore the receipt state.');
                }
            });

            $receipt->setAttribute('undone_at', now());
            $receipt->save();

            return [
                'action' => AdminActionCatalog::definition($actionKey)['label'],
                'inverse' => AdminActionCatalog::definition($inverseActionKey)['label'],
            ];
        });
    }

    private function assertAvailableReceipt(AdminActionReceipt $receipt, User $actor): void
    {
        // Historical receipts for the retired additional-image feature remain
        // auditable but must not restore unsupported media references.
        if (str_starts_with((string) $receipt->getAttribute('action_key'), 'artwork.additional_media_')) {
            throw ValidationException::withMessages(['undo' => 'Undo is not available for the retired additional-image feature.']);
        }
        if ((int) $receipt->getAttribute('admin_user_id') !== (int) $actor->getKey()) {
            throw new AuthorizationException('This Undo receipt belongs to another admin.');
        }
        if (PublicationCheckpointEvent::query()->where('audit_event_id', (int) $receipt->getAttribute('audit_event_id'))->exists()) {
            throw ValidationException::withMessages(['undo' => 'This change is already part of a Commit and can no longer be undone individually.']);
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

    private function lockedTarget(AdminActionReceipt $receipt): Artwork|Exhibition
    {
        $target = $this->lifecycle->findTarget(
            (string) $receipt->getAttribute('entity_type'),
            (int) $receipt->getAttribute('entity_id'),
            lockForUpdate: true,
        );

        if ($target === null) {
            throw ValidationException::withMessages(['undo' => 'The target of this change no longer exists or is no longer part of the active editorial model.']);
        }

        return $target;
    }


}
