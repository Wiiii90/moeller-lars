<?php

namespace App\Domain\Admin;

use App\Models\AdminActivityOrderingEvent;
use App\Models\AdminActivityOrderingGroup;
use App\Models\AuditEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AdminActivityOrderingProjector
{
    public const COALESCE_SECONDS = 600;

    /**
     * @param array{scope:string,before_hash:string,after_hash:string,item_count:int} $ordering
     */
    public function record(AuditEvent $event, array $ordering): void
    {
        DB::transaction(function () use ($event, $ordering): void {
            $actorId = $event->getAttribute('admin_user_id');
            $occurredAt = $event->getAttribute('occurred_at');
            if (! $occurredAt instanceof CarbonInterface) {
                throw new RuntimeException('Ordering Activity requires an event timestamp.');
            }

            $previous = AuditEvent::query()
                ->where('admin_user_id', $actorId)
                ->where('id', '<', $event->getKey())
                ->orderByDesc('id')
                ->first();

            $group = null;
            if ($previous instanceof AuditEvent) {
                /** @var AdminActivityOrderingEvent|null $previousProjection */
                $previousProjection = AdminActivityOrderingEvent::query()
                    ->with('group')
                    ->whereKey($previous->getKey())
                    ->first();
                $candidate = $previousProjection?->getRelationValue('group');

                if (
                    $candidate instanceof AdminActivityOrderingGroup
                    && ! $candidate->returnedToIdentity()
                    && (string) $candidate->getAttribute('scope') === $ordering['scope']
                    && (string) $candidate->getAttribute('action') === (string) $event->getAttribute('action')
                    && hash_equals((string) $candidate->getAttribute('after_hash'), $ordering['before_hash'])
                    && $candidate->getAttribute('ended_at') instanceof CarbonInterface
                    && $candidate->getAttribute('ended_at')->diffInSeconds($occurredAt) <= self::COALESCE_SECONDS
                ) {
                    /** @var AdminActivityOrderingGroup $group */
                    $group = AdminActivityOrderingGroup::query()
                        ->whereKey($candidate->getKey())
                        ->lockForUpdate()
                        ->firstOrFail();
                }
            }

            if ($group instanceof AdminActivityOrderingGroup) {
                $sequence = ((int) $group->getAttribute('event_count')) + 1;
                $group->forceFill([
                    'last_audit_event_id' => (int) $event->getKey(),
                    'event_count' => $sequence,
                    'item_count' => max((int) $group->getAttribute('item_count'), $ordering['item_count']),
                    'after_hash' => $ordering['after_hash'],
                    'ended_at' => $occurredAt,
                ])->save();
            } else {
                $sequence = 1;
                $group = AdminActivityOrderingGroup::query()->create([
                    'admin_user_id' => $actorId,
                    'scope' => $ordering['scope'],
                    'action' => (string) $event->getAttribute('action'),
                    'first_audit_event_id' => (int) $event->getKey(),
                    'last_audit_event_id' => (int) $event->getKey(),
                    'event_count' => 1,
                    'item_count' => $ordering['item_count'],
                    'before_hash' => $ordering['before_hash'],
                    'after_hash' => $ordering['after_hash'],
                    'started_at' => $occurredAt,
                    'ended_at' => $occurredAt,
                ]);
            }

            AdminActivityOrderingEvent::query()->create([
                'audit_event_id' => (int) $event->getKey(),
                'group_id' => (int) $group->getKey(),
                'sequence' => $sequence,
            ]);
        });
    }
}
