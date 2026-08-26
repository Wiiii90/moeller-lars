<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
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
            ])
            ->all();

        return [
            'cover_media_asset_id' => $cover instanceof JournalEntryMedia ? (int) $cover->getAttribute('media_asset_id') : null,
            'gallery_images' => $gallery,
        ];
    }

    /** @param array<string,mixed> $data */
    public function syncStructuredMedia(BlogPost|Exhibition $entry, array $data): void
    {
        if (array_key_exists('cover_media_asset_id', $data)) {
            $this->syncCover($entry, $data['cover_media_asset_id']);
        }
        if (array_key_exists('gallery_images', $data)) {
            $this->syncGallery($entry, $data['gallery_images']);
        }
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
        /** @var EloquentCollection<int, JournalEntryMedia> $usages */
        $usages = JournalEntryMedia::query()
            ->where('media_asset_id', $assetId)
            ->with(['blogPost', 'exhibition'])
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $owners = [];
        foreach ($usages as $usage) {
            $entry = $usage->entry();
            if ($entry instanceof BlogPost) {
                $owners['blog:'.$entry->getKey()] = $entry;
            } elseif ($entry instanceof Exhibition) {
                $owners['exhibition:'.$entry->getKey()] = $entry;
            }
            $usage->delete();
        }

        $actor = $this->audit->requireActor();
        /** @var EloquentCollection<int, BlogPost> $posts */
        $posts = BlogPost::query()->whereNotNull('body')->lockForUpdate()->get();
        foreach ($posts as $post) {
            $source = (string) $post->getAttribute('body');
            if (! in_array($assetId, RichTextMediaReference::ids($source), true)) {
                continue;
            }
            $clean = RichTextMediaReference::remove($source, $assetId);
            $post->setAttribute('body', $clean === '' ? null : $clean);
            $state = (string) $post->getAttribute('state');
            if ($clean === '' && in_array($state, ['published', 'scheduled'], true)) {
                $post->setAttribute('state', $state === 'published' ? 'unpublished' : 'draft');
                $post->setAttribute('scheduled_at', null);
                $this->audit->record(
                    $actor,
                    $state === 'published' ? 'blog_post.unpublished' : 'blog_post.restored_to_draft',
                    'blog_post',
                    $post->getKey(),
                    ['reason' => 'referenced_rich_text_media_deleted'],
                );
            }
            $post->save();
        }

        /** @var EloquentCollection<int, Exhibition> $exhibitions */
        $exhibitions = Exhibition::query()->whereNotNull('description')->lockForUpdate()->get();
        foreach ($exhibitions as $exhibition) {
            $source = (string) $exhibition->getAttribute('description');
            if (! in_array($assetId, RichTextMediaReference::ids($source), true)) {
                continue;
            }
            $clean = RichTextMediaReference::remove($source, $assetId);
            $exhibition->setAttribute('description', $clean === '' ? null : $clean);
            $exhibition->save();
        }

        foreach ($owners as $entry) {
            $this->normalizeGallery($entry);
        }
    }

    private function syncCover(BlogPost|Exhibition $entry, mixed $value): void
    {
        $entry->mediaUsages()->where('role', JournalEntryMedia::ROLE_COVER)->delete();
        if ($value === null || $value === '') {
            return;
        }

        $mediaAssetId = $this->mediaId($value, 'cover_media_asset_id');
        $this->assertMediaReady($mediaAssetId, 'cover_media_asset_id');
        $this->createUsage($entry, $mediaAssetId, JournalEntryMedia::ROLE_COVER, 0);
    }

    private function syncGallery(BlogPost|Exhibition $entry, mixed $value): void
    {
        if (! is_array($value)) {
            throw ValidationException::withMessages(['gallery_images' => 'Gallery images are invalid.']);
        }

        $entry->mediaUsages()->where('role', JournalEntryMedia::ROLE_GALLERY)->delete();

        foreach (array_values($value) as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(['gallery_images' => 'Gallery images are invalid.']);
            }
            $mediaAssetId = $this->mediaId($row['media_asset_id'] ?? null, 'gallery_images');
            $this->assertMediaReady($mediaAssetId, 'gallery_images');
            $this->createUsage($entry, $mediaAssetId, JournalEntryMedia::ROLE_GALLERY, $index + 1);
        }
    }

    private function createUsage(BlogPost|Exhibition $entry, int $mediaAssetId, string $role, int $position): void
    {
        $usage = new JournalEntryMedia;
        $usage->fill([
            ...$this->ownership($entry),
            'media_asset_id' => $mediaAssetId,
            'role' => $role,
            'position' => $position,
            'alt_text_override' => null,
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
            || ! MediaTypePolicy::isImage((string) $asset->getAttribute('mime_type'))) {
            throw ValidationException::withMessages([$field => 'Journal images must reference an available image in Media Files.']);
        }
        try {
            $this->publicMedia->altTextForAsset($asset);
            $this->publicMedia->thumbnailVariantForAsset($asset);
        } catch (LogicException) {
            throw ValidationException::withMessages([$field => 'Journal images require ALT text and an available public image variant.']);
        }
    }

    private function mediaId(mixed $value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) {
            throw ValidationException::withMessages([$field => 'Choose an image from Media Files.']);
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
