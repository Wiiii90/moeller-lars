<?php

namespace App\Domain\Admin;

use App\Domain\Artwork\ArtworkEditorialService;
use App\Models\AdminActionReceipt;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

final class AdminUndoService
{
    private const MEDIA_ACTIONS = [
        'artwork.additional_media_attached',
        'artwork.additional_media_detached',
        'artwork.additional_media_reordered',
    ];

    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly ArtworkEditorialService $artworkEditorial,
        private readonly AdminLifecycleRegistry $lifecycle,
        private readonly AdminSnapshotReceiptService $snapshots,
        private readonly AdminUndoContext $undoContext,
    ) {}

    /** @return array{action:string,inverse:string} */
    public function undo(int $receiptId): array
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($receiptId, $actor): array {
            /** @var AdminActionReceipt|null $receipt */
            $receipt = AdminActionReceipt::query()->whereKey($receiptId)->lockForUpdate()->first();
            if (! $receipt) {
                throw ValidationException::withMessages(['undo' => 'This Undo receipt no longer exists.']);
            }

            $this->assertAvailableReceipt($receipt, $actor);
            $actionKey = (string) $receipt->getAttribute('action_key');
            $inverseActionKey = (string) $receipt->getAttribute('inverse_action_key');

            $this->undoContext->withoutReceipts(function () use ($receipt, $actor, $actionKey, $inverseActionKey): void {
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

                $target = $this->lockedTarget($receipt);
                if (in_array($actionKey, self::MEDIA_ACTIONS, true)) {
                    if (! $target instanceof Artwork) {
                        throw ValidationException::withMessages(['undo' => 'This media change no longer has a valid artwork target.']);
                    }
                    $this->assertMediaPrecondition($receipt, $target);
                    $this->executeMediaInverse($receipt, $target);
                    $this->assertMediaRestored($receipt, $target);

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

    private function assertMediaPrecondition(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        match ((string) $receipt->getAttribute('action_key')) {
            'artwork.additional_media_attached' => $this->assertAttachedPrecondition($receipt, $artwork),
            'artwork.additional_media_detached' => $this->assertDetachedPrecondition($receipt, $artwork),
            'artwork.additional_media_reordered' => $this->assertReorderedPrecondition($receipt, $artwork),
            default => throw ValidationException::withMessages(['undo' => 'This media change has no reversible contract.']),
        };
    }

    private function assertAttachedPrecondition(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        /** @var ArtworkMedia|null $usage */
        $usage = ArtworkMedia::query()
            ->whereKey((int) $receipt->getAttribute('artwork_media_id'))
            ->where('artwork_id', $artwork->getKey())
            ->lockForUpdate()
            ->first();

        if (! $usage
            || $usage->getAttribute('role') !== 'additional'
            || (int) $usage->getAttribute('media_asset_id') !== (int) $receipt->getAttribute('media_asset_id')
            || (int) $usage->getAttribute('position') !== (int) $receipt->getAttribute('after_position')) {
            $this->conflict();
        }
    }

    private function assertDetachedPrecondition(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        /** @var MediaAsset|null $asset */
        $asset = MediaAsset::query()->whereKey((int) $receipt->getAttribute('media_asset_id'))->lockForUpdate()->first();
        if (! $asset || $asset->getAttribute('state') !== 'available') {
            $this->conflict();
        }
        if (ArtworkMedia::query()->whereKey((int) $receipt->getAttribute('artwork_media_id'))->exists()) {
            $this->conflict();
        }
        if (ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('media_asset_id', $asset->getKey())->exists()) {
            $this->conflict();
        }

        /** @var EloquentCollection<int, ArtworkMedia> $additional */
        $additional = ArtworkMedia::query()
            ->where('artwork_id', $artwork->getKey())
            ->where('role', 'additional')
            ->orderBy('position')
            ->lockForUpdate()
            ->get();
        if (! $this->neighborGapMatches($receipt, $additional->modelKeys())) {
            $this->conflict();
        }
    }

    private function assertReorderedPrecondition(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        /** @var EloquentCollection<int, ArtworkMedia> $usages */
        $usages = ArtworkMedia::query()
            ->whereIn('id', [(int) $receipt->getAttribute('artwork_media_id'), (int) $receipt->getAttribute('neighbor_artwork_media_id')])
            ->where('artwork_id', $artwork->getKey())
            ->where('role', 'additional')
            ->lockForUpdate()
            ->get();
        /** @var ArtworkMedia|null $moving */
        $moving = $usages->firstWhere('id', (int) $receipt->getAttribute('artwork_media_id'));
        /** @var ArtworkMedia|null $neighbor */
        $neighbor = $usages->firstWhere('id', (int) $receipt->getAttribute('neighbor_artwork_media_id'));

        if (! $moving || ! $neighbor
            || (int) $moving->getAttribute('position') !== (int) $receipt->getAttribute('after_position')
            || (int) $neighbor->getAttribute('position') !== (int) $receipt->getAttribute('before_position')
            || abs((int) $moving->getAttribute('position') - (int) $neighbor->getAttribute('position')) !== 1) {
            $this->conflict();
        }
    }

    private function executeMediaInverse(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        match ((string) $receipt->getAttribute('action_key')) {
            'artwork.additional_media_attached' => $this->undoMediaAttach($receipt, $artwork),
            'artwork.additional_media_detached' => $this->undoMediaDetach($receipt, $artwork),
            'artwork.additional_media_reordered' => $this->undoMediaReorder($receipt, $artwork),
            default => throw ValidationException::withMessages(['undo' => 'This media change has no reversible contract.']),
        };
    }

    private function undoMediaAttach(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        /** @var ArtworkMedia $usage */
        $usage = ArtworkMedia::query()->findOrFail((int) $receipt->getAttribute('artwork_media_id'));
        $this->artworkEditorial->detachAdditionalMedia($artwork, $usage);
    }

    private function undoMediaDetach(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        /** @var MediaAsset $asset */
        $asset = MediaAsset::query()->findOrFail((int) $receipt->getAttribute('media_asset_id'));
        $this->artworkEditorial->restoreAdditionalMedia($artwork, $asset, (int) $receipt->getAttribute('before_position'));
    }

    private function undoMediaReorder(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        /** @var ArtworkMedia $usage */
        $usage = ArtworkMedia::query()->findOrFail((int) $receipt->getAttribute('artwork_media_id'));
        $direction = (string) $receipt->getAttribute('inverse_direction');
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['undo' => 'This reorder receipt has no valid inverse direction.']);
        }
        $this->artworkEditorial->moveAdditionalMedia($artwork, $usage, $direction);
    }

    private function assertMediaRestored(AdminActionReceipt $receipt, Artwork $artwork): void
    {
        $action = (string) $receipt->getAttribute('action_key');
        if ($action === 'artwork.additional_media_attached') {
            if (ArtworkMedia::query()->where('artwork_id', $artwork->getKey())->where('media_asset_id', (int) $receipt->getAttribute('media_asset_id'))->exists()) {
                throw new RuntimeException('The media inverse did not detach the expected asset.');
            }

            return;
        }
        if ($action === 'artwork.additional_media_detached') {
            /** @var ArtworkMedia|null $restored */
            $restored = ArtworkMedia::query()
                ->where('artwork_id', $artwork->getKey())
                ->where('media_asset_id', (int) $receipt->getAttribute('media_asset_id'))
                ->where('role', 'additional')
                ->first();
            if (! $restored || (int) $restored->getAttribute('position') !== (int) $receipt->getAttribute('before_position')) {
                throw new RuntimeException('The media inverse did not restore the expected gallery position.');
            }

            return;
        }

        /** @var EloquentCollection<int, ArtworkMedia> $usages */
        $usages = ArtworkMedia::query()
            ->whereIn('id', [(int) $receipt->getAttribute('artwork_media_id'), (int) $receipt->getAttribute('neighbor_artwork_media_id')])
            ->get();
        /** @var ArtworkMedia|null $moving */
        $moving = $usages->firstWhere('id', (int) $receipt->getAttribute('artwork_media_id'));
        /** @var ArtworkMedia|null $neighbor */
        $neighbor = $usages->firstWhere('id', (int) $receipt->getAttribute('neighbor_artwork_media_id'));
        if (! $moving || ! $neighbor
            || (int) $moving->getAttribute('position') !== (int) $receipt->getAttribute('before_position')
            || (int) $neighbor->getAttribute('position') !== (int) $receipt->getAttribute('after_position')) {
            throw new RuntimeException('The media inverse did not restore the expected order.');
        }
    }

    /** @param array<int, int|string> $ordered */
    private function neighborGapMatches(AdminActionReceipt $receipt, array $ordered): bool
    {
        $ordered = array_map(static fn (int|string $id): int => (int) $id, $ordered);
        $previous = $receipt->getAttribute('previous_artwork_media_id');
        $next = $receipt->getAttribute('next_artwork_media_id');
        $previous = is_int($previous) ? $previous : null;
        $next = is_int($next) ? $next : null;

        if ($previous === null && $next === null) {
            return $ordered === [];
        }
        if ($previous === null) {
            return ($ordered[0] ?? null) === $next;
        }
        if ($next === null) {
            return $ordered !== [] && $ordered[array_key_last($ordered)] === $previous;
        }

        $previousIndex = array_search($previous, $ordered, true);
        $nextIndex = array_search($next, $ordered, true);

        return is_int($previousIndex) && is_int($nextIndex) && $nextIndex === $previousIndex + 1;
    }

    private function conflict(): never
    {
        throw ValidationException::withMessages([
            'undo' => 'Undo is no longer available because this artwork media changed afterwards.',
        ]);
    }
}
