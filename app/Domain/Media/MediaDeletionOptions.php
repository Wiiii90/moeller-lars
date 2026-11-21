<?php

namespace App\Domain\Media;

/**
 * One explicit policy for the canonical Storage deletion transaction.
 * Reviewed primary Artwork identities are required only for a confirmation
 * which offers deletion of the owning Artwork records.
 */
final readonly class MediaDeletionOptions
{
    /**
     * @param list<int> $deleteArtworkIds
     * @param list<int>|null $reviewedPrimaryArtworkIds
     */
    public function __construct(
        public array $deleteArtworkIds = [],
        public ?array $reviewedPrimaryArtworkIds = null,
        public bool $requireUnreferenced = false,
    ) {}
}
