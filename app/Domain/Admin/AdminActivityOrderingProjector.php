<?php

namespace App\Domain\Admin;

use App\Models\AdminActivityOrderingEvent;
use App\Models\AdminActivityOrderingGroup;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpointEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AdminActivityOrderingProjector
{
    /**
     * @param array{scope:string,target_label:?string,before_hash:string,after_hash:string,item_count:int} $ordering
     */
    public function record(AuditEvent $event, array $ordering): void
    {
        DB::transaction(function () use ($event, $ordering): void {
            $actorId = $event->getAttribute('admin_user_id');
            $occurredAt = $event->getAttribute('occurred_at');
            if (! $occurredAt instanceof CarbonInterface) {
                throw new RuntimeException('Ordering Activity requires an event timestamp.');
            }

            /** @var AdminActivityOrderingGroup|null $candidate */
            $candidate = AdminActivityOrderingGroup::query()
                ->where('admin_user_id', $actorId)
                ->where('scope', $ordering['scope'])
                ->where('action', (string) $event->getAttribute('action'))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $group = null;
            if (
                $candidate instanceof AdminActivityOrderingGroup
                && ! $candidate->returnedToIdentity()
                && hash_equals((string) $candidate->getAttribute('after_hash'), $ordering['before_hash'])
                && ! PublicationCheckpointEvent::query()
                    ->where('audit_event_id', (int) $candidate->getAttribute('last_audit_event_id'))
                    ->exists()
            ) {
                $group = $candidate;
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
                    'target_label' => $ordering['target_label'],
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
