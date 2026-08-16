<?php

namespace App\Domain\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AdminAuditService
{
    private const ACTIONS = [
        'artwork.created',
        'artwork.updated',
        'artwork.published',
        'artwork.unpublished',
        'artwork.primary_media_attached',
        'media.ingested',
    ];

    private const ENTITY_TYPES = ['artwork', 'media_asset'];

    public function requireActor(): User
    {
        $actor = Auth::guard('web')->user();

        if (! $actor instanceof User || ! (bool) $actor->getAttribute('is_admin')) {
            throw new AuthorizationException('An admin actor is required.');
        }

        return $actor;
    }

    public function record(
        User $actor,
        string $action,
        string $entityType,
        int $entityId,
        ?array $metadata = null,
    ): AuditEvent {
        if (! in_array($action, self::ACTIONS, true)) {
            throw new InvalidArgumentException('Invalid audit action.');
        }

        if (! in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid audit entity type.');
        }

        $metadata ??= [];
        foreach ($metadata as $key => $value) {
            if (! in_array($key, ['artwork_id', 'media_asset_id'], true) || ! is_int($value) || $value <= 0) {
                throw new InvalidArgumentException('Invalid audit metadata.');
            }
        }

        $event = new AuditEvent;
        $event->fill([
            'admin_user_id' => $actor->getKey(),
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'occurred_at' => now(),
            'request_id' => null,
            'metadata' => $metadata === [] ? null : $metadata,
        ]);
        $event->save();

        return $event;
    }
}
