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
        'artwork.primary_media_replaced',
        'artwork.primary_media_alt_updated',
        'artwork.additional_media_attached',
        'artwork.additional_media_detached',
        'artwork.additional_media_reordered',
        'artwork_category.created',
        'artwork_category.updated',
        'artwork_category.published',
        'artwork_category.hidden',
        'artwork_category.slug_changed',
        'artwork_category.deleted',
        'artwork_category.gallery_reordered',
        'site_section.updated',
        'site_section.reordered',
        'media.ingested',
        'media.metadata_updated',
        'media.deleted',
        'cv_entry.created',
        'cv_entry.updated',
        'cv_entry.published',
        'cv_entry.unpublished',
        'cv_entry.archived',
        'cv_entry.restored_to_draft',
        'cv_entry.reordered',
        'exhibition.created',
        'exhibition.updated',
        'exhibition.published',
        'exhibition.unpublished',
        'exhibition.archived',
        'exhibition.restored_to_draft',
        'exhibition.reordered',
        'blog_post.created',
        'blog_post.updated',
        'blog_post.published',
        'blog_post.scheduled',
        'blog_post.unpublished',
        'blog_post.archived',
        'blog_post.restored_to_draft',
        'blog_post.reordered',
        'blog_setting.updated',
        'public_content_setting.updated',
    ];

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
            $validReference = in_array($key, ['artwork_id', 'media_asset_id', 'artwork_media_id'], true)
                && is_int($value)
                && $value > 0;
            $validPosition = $key === 'position'
                && is_int($value)
                && $value >= 0;
            $validDirection = $key === 'direction'
                && in_array($value, ['up', 'down'], true);

            if (! $validReference && ! $validPosition && ! $validDirection) {
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
