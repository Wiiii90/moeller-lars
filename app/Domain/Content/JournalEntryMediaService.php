<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use LogicException;

final class JournalEntryMediaService
{
    public function __construct(
        private readonly SafeRichTextRenderer $richText,
        private readonly PublicMedia $publicMedia,
        private readonly AdminAuditService $audit,
    ) {}

    /** @return array<string,mixed> */
    public function structuredEditorState(BlogPost|Exhibition $entry): array
    {
        $entry->loadMissing('mediaUsages.mediaAsset.variants');
        /** @var EloquentCollection<int, JournalEntryMedia> $usages */
        $usages = $entry->mediaUsages;
        $cover = $usages->firstWhere('role', JournalEntryMedia::ROLE_COVER);
        $gallery = $usages->where('role', JournalEntryMedia::ROLE_GALLERY)
            ->sortBy(['position', 'id'])
            ->values()
            ->map(fn (JournalEntryMedia $usage): array => [
                'media_asset_id' => (int) $usage->getAttribute('media_asset_id'),
                'video_controls_enabled' => $usage->videoControlsEnabled(),
            ])
            ->all();

        return [
            'cover_media_asset_id' => $cover instanceof JournalEntryMedia ? (int) $cover->getAttribute('media_asset_id') : null,
            'cover_video_controls_enabled' => $cover instanceof JournalEntryMedia ? $cover->videoControlsEnabled() : true,
            'gallery_images' => $gallery,
        ];
    }

    /** @param array<string,mixed> $data */
    public function syncStructuredMedia(BlogPost|Exhibition $entry, array $data): bool
    {
        $changed = false;

        if ((array_key_exists('cover_media_asset_id', $data) || array_key_exists('cover_video_controls_enabled', $data))
            && $this->syncCover($entry, $data)) {
            $changed = true;
        }
        if (array_key_exists('gallery_images', $data) && $this->syncGallery($entry, $data['gallery_images'])) {
            $changed = true;
        }

        if ($changed) {
            $entry->unsetRelation('mediaUsages');
        }

        return $changed;
    }

    public function assertPublicReady(BlogPost|Exhibition $entry): void
    {
        $entry->loadMissing('mediaUsages.mediaAsset.variants');
        /** @var EloquentCollection<int, JournalEntryMedia> $usages */
        $usages = $entry->mediaUsages;
        foreach ($usages as $usage) {
            if ($entry instanceof Exhibition
                && ! (bool) $entry->getAttribute('gallery_enabled')
                && $usage->getAttribute('role') === JournalEntryMedia::ROLE_GALLERY) {
                continue;
            }
            $this->assertMediaReady(
                (int) $usage->getAttribute('media_asset_id'),
                'media',
            );
        }

        $source = $this->source($entry);
        if ($source !== '') {
            $this->richText->assertValid($source, allowEmbeddedMedia: true, requirePublicMedia: true);
        }
    }

    public function detachAsset(MediaAsset $asset): void
    {
        $assetId = (int) $asset->getKey();
        $actor = $this->audit->requireActor();

        /** @var EloquentCollection<int, JournalEntryMedia> $usages */
        $usages = JournalEntryMedia::query()
            ->where('media_asset_id', $assetId)
            ->with(['blogPost', 'exhibition'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        /** @var array<string, BlogPost|Exhibition> $structuredOwners */
        $structuredOwners = [];
        foreach ($usages as $usage) {
            $entry = $usage->entry();
            if ($entry instanceof BlogPost) {
                $structuredOwners['blog:'.$entry->getKey()] = $entry;
            } elseif ($entry instanceof Exhibition) {
                $structuredOwners['exhibition:'.$entry->getKey()] = $entry;
            }

            $usage->delete();
        }

        $processedBlogIds = [];
        $processedExhibitionIds = [];

        foreach ($structuredOwners as $entry) {
            if ($entry instanceof BlogPost) {
                $processedBlogIds[] = (int) $entry->getKey();
                $action = $this->removeBlogRichTextReference($entry, $assetId)
                    ?? 'blog_post.media_reference_removed';
                $this->normalizeGallery($entry);
                $this->recordBlogMediaRemoval($entry, $actor, $action);

                continue;
            }

            $processedExhibitionIds[] = (int) $entry->getKey();
            $this->removeExhibitionRichTextReference($entry, $assetId);
            $this->normalizeGallery($entry);
            $this->audit->record(
                $actor,
                'exhibition.media_reference_removed',
                'exhibition',
                (int) $entry->getKey(),
            );
        }

        /** @var EloquentCollection<int, BlogPost> $posts */
        $posts = BlogPost::query()
            ->whereNotNull('body')
            ->when(
                $processedBlogIds !== [],
                static fn ($query) => $query->whereNotIn('id', $processedBlogIds),
            )
            ->lockForUpdate()
            ->get();
        foreach ($posts as $post) {
            $action = $this->removeBlogRichTextReference($post, $assetId);
            if ($action === null) {
                continue;
            }

            $this->recordBlogMediaRemoval($post, $actor, $action);
        }

        /** @var EloquentCollection<int, Exhibition> $exhibitions */
        $exhibitions = Exhibition::query()
            ->whereNotNull('description')
            ->when(
                $processedExhibitionIds !== [],
                static fn ($query) => $query->whereNotIn('id', $processedExhibitionIds),
            )
            ->lockForUpdate()
            ->get();
        foreach ($exhibitions as $exhibition) {
            if (! $this->removeExhibitionRichTextReference($exhibition, $assetId)) {
                continue;
            }

            $this->audit->record(
                $actor,
                'exhibition.media_reference_removed',
                'exhibition',
                (int) $exhibition->getKey(),
            );
        }
    }

    private function removeBlogRichTextReference(BlogPost $post, int $assetId): ?string
    {
        $source = (string) ($post->getAttribute('body') ?? '');
        if (! in_array($assetId, RichTextMediaReference::ids($source), true)) {
            return null;
        }

        $clean = RichTextMediaReference::remove($source, $assetId);
        $state = (string) $post->getAttribute('state');

        $post->setAttribute('body', $clean === '' ? null : $clean);
        if ($clean === '' && in_array($state, ['published', 'scheduled'], true)) {
            $post->setAttribute('state', $state === 'published' ? 'unpublished' : 'draft');
            $post->setAttribute('scheduled_at', null);
        }
        $post->save();

        return match (true) {
            $state === 'published' && (string) $post->getAttribute('state') === 'unpublished' => 'blog_post.unpublished',
            $state === 'scheduled' && (string) $post->getAttribute('state') === 'draft' => 'blog_post.restored_to_draft',
            default => 'blog_post.media_reference_removed',
        };
    }

    private function recordBlogMediaRemoval(BlogPost $post, User $actor, string $action): void
    {
        $metadata = in_array($action, ['blog_post.unpublished', 'blog_post.restored_to_draft'], true)
            ? ['reason' => 'referenced_rich_text_media_deleted']
            : null;

        $this->audit->record(
            $actor,
            $action,
            'blog_post',
            (int) $post->getKey(),
            $metadata,
        );
    }

    private function removeExhibitionRichTextReference(Exhibition $exhibition, int $assetId): bool
    {
        $source = (string) ($exhibition->getAttribute('description') ?? '');
        if (! in_array($assetId, RichTextMediaReference::ids($source), true)) {
            return false;
        }

        $clean = RichTextMediaReference::remove($source, $assetId);
        $exhibition->setAttribute('description', $clean === '' ? null : $clean);
        $exhibition->save();

        return true;
    }

    /** @param array<string,mixed> $data */
    private function syncCover(BlogPost|Exhibition $entry, array $data): bool
    {
        /** @var EloquentCollection<int, JournalEntryMedia> $current */
        $current = $entry->mediaUsages()
            ->where('role', JournalEntryMedia::ROLE_COVER)
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $existing = $current->first();
        $value = array_key_exists('cover_media_asset_id', $data)
            ? $data['cover_media_asset_id']
            : ($existing?->getAttribute('media_asset_id'));

        if ($value === null || $value === '') {
            if ($current->isEmpty()) {
                return false;
            }

            foreach ($current as $usage) {
                $usage->delete();
            }

            return true;
        }

        $mediaAssetId = $this->mediaId($value, 'cover_media_asset_id');
        $this->assertMediaReady($mediaAssetId, 'cover_media_asset_id');
        $controls = array_key_exists('cover_video_controls_enabled', $data)
            ? $this->validatedVideoControls($data['cover_video_controls_enabled'], 'cover_video_controls_enabled')
            : ($existing?->videoControlsEnabled() ?? true);

        if ($current->count() === 1
            && (int) $existing->getAttribute('media_asset_id') === $mediaAssetId
            && (int) $existing->getAttribute('position') === 0) {
            if ($existing->videoControlsEnabled() === $controls) {
                return false;
            }

            $existing->setAttribute('video_controls_enabled', $controls);
            $existing->save();

            return true;
        }

        foreach ($current as $usage) {
            $usage->delete();
        }
        $this->createUsage($entry, $mediaAssetId, JournalEntryMedia::ROLE_COVER, 0, $controls);

        return true;
    }

    private function syncGallery(BlogPost|Exhibition $entry, mixed $value): bool
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['gallery_images' => 'Gallery images are invalid.']);
        }

        /** @var EloquentCollection<int, JournalEntryMedia> $current */
        $current = $entry->mediaUsages()
            ->where('role', JournalEntryMedia::ROLE_GALLERY)
            ->orderBy('position')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $availableByMedia = [];
        foreach ($current as $usage) {
            $availableByMedia[(int) $usage->getAttribute('media_asset_id')][] = $usage;
        }

        $desired = [];
        foreach (array_values($value) as $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['gallery_images' => 'Gallery images are invalid.']);
            }
            $mediaAssetId = $this->mediaId($row['media_asset_id'] ?? null, 'gallery_images');
            $this->assertMediaReady($mediaAssetId, 'gallery_images');
            $previous = array_shift($availableByMedia[$mediaAssetId]);
            $controls = array_key_exists('video_controls_enabled', $row)
                ? $this->validatedVideoControls($row['video_controls_enabled'], 'gallery_images')
                : ($previous?->videoControlsEnabled() ?? true);

            $desired[] = [
                'media_asset_id' => $mediaAssetId,
                'video_controls_enabled' => $controls,
            ];
        }

        $currentIds = $current
            ->map(static fn (JournalEntryMedia $usage): int => (int) $usage->getAttribute('media_asset_id'))
            ->values()
            ->all();
        $desiredIds = array_column($desired, 'media_asset_id');
        $positionsAreCanonical = $current->values()->every(
            static fn (JournalEntryMedia $usage, int $index): bool => (int) $usage->getAttribute('position') === $index + 1,
        );

        if ($currentIds === $desiredIds && $positionsAreCanonical) {
            $changed = false;
            foreach ($current->values() as $index => $usage) {
                $controls = $desired[$index]['video_controls_enabled'];
                if ($usage->videoControlsEnabled() === $controls) {
                    continue;
                }

                $usage->setAttribute('video_controls_enabled', $controls);
                $usage->save();
                $changed = true;
            }

            return $changed;
        }

        foreach ($current as $usage) {
            $usage->delete();
        }
        foreach ($desired as $index => $row) {
            $this->createUsage(
                $entry,
                $row['media_asset_id'],
                JournalEntryMedia::ROLE_GALLERY,
                $index + 1,
                $row['video_controls_enabled'],
            );
        }

        return true;
    }

    private function validatedVideoControls(mixed $value, string $field): bool
    {
        if (! is_bool($value)) {
            throw ValidationException::withMessages([$field => 'Choose whether video playback controls are shown.']);
        }

        return $value;
    }

    private function createUsage(BlogPost|Exhibition $entry, int $mediaAssetId, string $role, int $position, bool $controls = true): void
    {
        $usage = new JournalEntryMedia;
        $usage->fill([
            ...$this->ownership($entry),
            'media_asset_id' => $mediaAssetId,
            'role' => $role,
            'position' => $position,
            'alt_text_override' => null,
            'video_controls_enabled' => $controls,
        ]);
        $usage->save();
    }

    /** @return array{blog_post_id:?int,exhibition_id:?int} */
    private function ownership(BlogPost|Exhibition $entry): array
    {
        return [
            'blog_post_id' => $entry instanceof BlogPost ? (int) $entry->getKey() : null,
            'exhibition_id' => $entry instanceof Exhibition ? (int) $entry->getKey() : null,
        ];
    }

    private function normalizeGallery(BlogPost|Exhibition $entry): void
    {
        /** @var EloquentCollection<int, JournalEntryMedia> $gallery */
        $gallery = $entry->mediaUsages()->where('role', JournalEntryMedia::ROLE_GALLERY)
            ->orderBy('position')->orderBy('id')->lockForUpdate()->get();
        foreach ($gallery as $index => $usage) {
            if ((int) $usage->getAttribute('position') !== $index + 1) {
                $usage->setAttribute('position', $index + 1);
                $usage->save();
            }
        }
    }

    private function assertMediaReady(int $mediaAssetId, string $field): void
    {
        $asset = MediaAsset::query()->with('variants')->find($mediaAssetId);
        if (! $asset instanceof MediaAsset
            || $asset->getAttribute('state') !== 'available'
            || (! MediaTypePolicy::isImage((string) $asset->getAttribute('mime_type'))
                && ! MediaTypePolicy::isVideo((string) $asset->getAttribute('mime_type')))) {
            throw ValidationException::withMessages([$field => 'Journal media must reference an available image or video in Storage.']);
        }
        try {
            $this->publicMedia->altTextForAsset($asset);
            if (MediaTypePolicy::isImage((string) $asset->getAttribute('mime_type'))) {
                $this->publicMedia->thumbnailVariantForAsset($asset);
            }
        } catch (LogicException) {
            throw ValidationException::withMessages([$field => 'Journal media requires ALT text; images also need a public thumbnail.']);
        }
    }

    private function mediaId(mixed $value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw ValidationException::withMessages([$field => 'Choose an image or video from Storage.']);
        }

        return (int) $id;
    }

    private function source(BlogPost|Exhibition $entry): string
    {
        return trim((string) ($entry instanceof BlogPost
            ? ($entry->getAttribute('body') ?? '')
            : ($entry->getAttribute('description') ?? '')));
    }
}
