<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminActionCatalog;
use App\Domain\Publication\PublicationVersionService;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use Carbon\CarbonInterface;
use Illuminate\Pagination\LengthAwarePaginator;

final class AdminPublicationHistory
{
    public function __construct(private readonly PublicationVersionService $versions) {}

    /**
     * @return array{commits:list<array<string,mixed>>,paginator:LengthAwarePaginator<int,PublicationCheckpoint>}
     */
    public function page(int $perPage = 20): array
    {
        $perPage = max(10, min(50, $perPage));
        $currentSchemaHash = $this->versions->schemaHash();
        $liveId = $this->versions->currentLiveCheckpoint()?->getKey();

        /** @var LengthAwarePaginator<int, PublicationCheckpoint> $paginator */
        $paginator = PublicationCheckpoint::query()
            ->with([
                'adminUser:id,name',
                'parent:id,hash,snapshot_available,schema_hash',
                'source:id,hash,snapshot_available,schema_hash',
            ])
            ->withCount('auditEvents')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'commits_page')
            ->withQueryString();

        return [
            'commits' => $paginator->getCollection()
                ->map(fn (PublicationCheckpoint $checkpoint): array => $this->project(
                    $checkpoint,
                    $liveId === $checkpoint->getKey(),
                    $currentSchemaHash,
                ))
                ->values()
                ->all(),
            'paginator' => $paginator,
        ];
    }

    /** @return array<string,mixed>|null */
    public function checkpoint(int $checkpointId): ?array
    {
        /** @var PublicationCheckpoint|null $checkpoint */
        $checkpoint = PublicationCheckpoint::query()
            ->with([
                'adminUser:id,name',
                'parent:id,hash,snapshot_available,schema_hash',
                'source:id,hash,snapshot_available,schema_hash',
            ])
            ->withCount('auditEvents')
            ->find($checkpointId);
        if (! $checkpoint instanceof PublicationCheckpoint) {
            return null;
        }

        $currentSchemaHash = $this->versions->schemaHash();
        $liveId = $this->versions->currentLiveCheckpoint()?->getKey();
        $projected = $this->project($checkpoint, $liveId === $checkpoint->getKey(), $currentSchemaHash);

        $events = AuditEvent::query()
            ->whereHas('publicationCheckpointEvent', static function ($query) use ($checkpointId): void {
                $query->where('publication_checkpoint_id', $checkpointId);
            })
            ->with('adminUser:id,name')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->map(static function (AuditEvent $event): array {
                $definition = AdminActionCatalog::definition((string) $event->getAttribute('action'));
                $occurredAt = $event->getAttribute('occurred_at');
                $actor = $event->getRelationValue('adminUser');

                return [
                    'id' => (int) $event->getKey(),
                    'action' => $definition['label'],
                    'area' => $definition['area'],
                    'entity_type' => (string) $event->getAttribute('entity_type'),
                    'entity_id' => (int) $event->getAttribute('entity_id'),
                    'actor' => $actor?->getAttribute('name') ?? 'Admin',
                    'timestamp' => $occurredAt instanceof CarbonInterface
                        ? $occurredAt->format('Y-m-d H:i')
                        : (string) $occurredAt,
                ];
            })
            ->values()
            ->all();

        return [...$projected, 'events' => $events];
    }

    /** @return array<string,mixed> */
    private function project(PublicationCheckpoint $checkpoint, bool $live, string $currentSchemaHash): array
    {
        /** @var CarbonInterface $publishedAt */
        $publishedAt = $checkpoint->getAttribute('published_at');
        $adminUser = $checkpoint->getRelationValue('adminUser');
        $parent = $checkpoint->getRelationValue('parent');
        $source = $checkpoint->getRelationValue('source');
        $hash = (string) ($checkpoint->getAttribute('hash') ?? '');
        $schemaHash = $checkpoint->getAttribute('schema_hash');
        $snapshotAvailable = (bool) $checkpoint->getAttribute('snapshot_available');
        $restorable = $snapshotAvailable
            && is_string($schemaHash)
            && hash_equals($schemaHash, $currentSchemaHash);
        $parentRestorable = $parent instanceof PublicationCheckpoint
            && (bool) $parent->getAttribute('snapshot_available')
            && is_string($parent->getAttribute('schema_hash'))
            && hash_equals((string) $parent->getAttribute('schema_hash'), $currentSchemaHash);
        $message = $checkpoint->getAttribute('message');
        $operation = (string) ($checkpoint->getAttribute('operation') ?? 'commit');

        return [
            'id' => (int) $checkpoint->getKey(),
            'hash' => $hash,
            'short_hash' => $hash !== '' ? substr($hash, 0, 10) : '#'.$checkpoint->getKey(),
            'snapshot_hash' => $checkpoint->getAttribute('snapshot_hash'),
            'schema_hash' => $schemaHash,
            'message' => is_string($message) && trim($message) !== '' ? trim($message) : null,
            'change_count' => (int) $checkpoint->getAttribute('change_count'),
            'event_count' => (int) ($checkpoint->getAttribute('audit_events_count') ?? 0),
            'actor' => $adminUser?->getAttribute('name') ?? 'Admin',
            'when' => $publishedAt->diffForHumans(),
            'timestamp' => $publishedAt->format('Y-m-d H:i'),
            'operation' => $operation,
            'operation_label' => match ($operation) {
                'initial' => 'Initial state',
                'restore' => 'Restore',
                'revert' => 'Revert',
                default => 'Commit',
            },
            'live' => $live,
            'snapshot_available' => $snapshotAvailable,
            'restorable' => $restorable,
            'legacy' => ! $snapshotAvailable,
            'can_restore' => ! $live && $restorable,
            'can_revert' => $live && $parentRestorable,
            'parent_id' => $parent?->getKey(),
            'parent_hash' => $parent instanceof PublicationCheckpoint ? (string) ($parent->getAttribute('hash') ?? '') : null,
            'parent_short_hash' => $parent instanceof PublicationCheckpoint && (string) ($parent->getAttribute('hash') ?? '') !== ''
                ? substr((string) $parent->getAttribute('hash'), 0, 10)
                : null,
            'source_id' => $source?->getKey(),
            'source_hash' => $source instanceof PublicationCheckpoint ? (string) ($source->getAttribute('hash') ?? '') : null,
        ];
    }
}
