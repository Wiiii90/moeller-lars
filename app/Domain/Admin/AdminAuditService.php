<?php

namespace App\Domain\Admin;

use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class AdminAuditService
{
    private const ENTITY_TYPES = [
        'artwork',
        'media_asset',
        'artwork_category',
        'site_section',
        'cv_entry',
        'exhibition',
        'blog_post',
        'blog_setting',
        'public_content_setting',
    ];

    private const REASONS = [
        'referenced_inline_media_deleted',
    ];

    public function __construct(
        private readonly AdminActionReceiptService $receipts,
    ) {}

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
        if (! AdminActionCatalog::has($action)) {
            throw new InvalidArgumentException('Invalid audit action.');
        }

        if (! in_array($entityType, self::ENTITY_TYPES, true)) {
            throw new InvalidArgumentException('Invalid audit entity type.');
        }

        $metadata ??= [];
        foreach ($metadata as $key => $value) {
            $validReference = in_array($key, [
                'artwork_id',
                'media_asset_id',
                'artwork_media_id',
                'neighbor_artwork_media_id',
                'previous_artwork_media_id',
                'next_artwork_media_id',
                'site_section_id',
            ], true)
                && is_int($value)
                && $value > 0;
            $validPosition = in_array($key, ['position', 'from_position', 'to_position'], true)
                && is_int($value)
                && $value >= 0;
            $validDirection = $key === 'direction'
                && in_array($value, ['up', 'down'], true);
            $validReason = $key === 'reason'
                && is_string($value)
                && in_array($value, self::REASONS, true);

            if (! $validReference && ! $validPosition && ! $validDirection && ! $validReason) {
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

        $this->receipts->recordForAuditEvent($event, $actor);

        return $event;
    }
}
