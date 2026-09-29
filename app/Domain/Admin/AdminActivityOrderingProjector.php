<?php

namespace App\Domain\Admin;

use App\Models\AdminActivityOrderingEvent;
use App\Models\AdminActivityOrderingProjection;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpointEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class AdminActivityOrderingProjector
{
    /**
     * @param array{scope:string,target_label:?string,before_state:list<string>,after_state:list<string>,item_count:int} $ordering
     */
    public function record(AuditEvent $event, array $ordering): AdminActivityOrderingProjection
    {
        return DB::transaction(function () use ($event, $ordering): AdminActivityOrderingProjection {
            $actorId = $event->getAttribute('admin_user_id');
            $occurredAt = $event->getAttribute('occurred_at');
            if (! $occurredAt instanceof CarbonInterface) {
                throw new RuntimeException('Ordering Activity requires an event timestamp.');
            }

            /** @var AdminActivityOrderingProjection|null $candidate */
            $candidate = AdminActivityOrderingProjection::query()
                ->where('scope', $ordering['scope'])
                ->where('action', (string) $event->getAttribute('action'))
                ->orderByDesc('id')
                ->lockForUpdate()
                ->first();

            $projection = null;
            if (
                $candidate instanceof AdminActivityOrderingProjection
                && (int) $candidate->getAttribute('admin_user_id') === (int) $actorId
                && $candidate->afterState() === $ordering['before_state']
                && ! PublicationCheckpointEvent::query()
                    ->where('audit_event_id', (int) $candidate->getAttribute('last_audit_event_id'))
                    ->exists()
            ) {
                $projection = $candidate;
            }

            if ($projection instanceof AdminActivityOrderingProjection) {
                $sequence = ((int) $projection->getAttribute('event_count')) + 1;
                $projection->forceFill([
                    'last_audit_event_id' => (int) $event->getKey(),
                    'event_count' => $sequence,
                    'item_count' => max((int) $projection->getAttribute('item_count'), $ordering['item_count']),
                    'after_state' => $ordering['after_state'],
                    'ended_at' => $occurredAt,
                ])->save();
            } else {
                $sequence = 1;
                $projection = AdminActivityOrderingProjection::query()->create([
                    'admin_user_id' => $actorId,
                    'scope' => $ordering['scope'],
                    'action' => (string) $event->getAttribute('action'),
                    'target_label' => $ordering['target_label'],
                    'first_audit_event_id' => (int) $event->getKey(),
                    'last_audit_event_id' => (int) $event->getKey(),
                    'event_count' => 1,
                    'item_count' => $ordering['item_count'],
                    'before_state' => $ordering['before_state'],
                    'after_state' => $ordering['after_state'],
                    'started_at' => $occurredAt,
                    'ended_at' => $occurredAt,
                ]);
            }

            AdminActivityOrderingEvent::query()->create([
                'audit_event_id' => (int) $event->getKey(),
                'projection_id' => (int) $projection->getKey(),
                'sequence' => $sequence,
            ]);

            return $projection;
        });
    }
}
