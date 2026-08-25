<?php

namespace App\Domain\Content;

use App\Domain\Media\MediaTypePolicy;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final class JournalEntryMediaService
{
    public function __construct(
        private readonly JournalEntryContent $content,
        private readonly PublicMedia $publicMedia,
    ) {}

    /** @return array<string,mixed> */
    public function editorState(BlogPost|Exhibition $entry): array
    {
        $entry->loadMissing('mediaUsages.mediaAsset.variants');
        /** @var EloquentCollection<int, JournalEntryMedia> $usages */
        $usages = $entry->mediaUsages;
        $inline = $usages->where('role', JournalEntryMedia::ROLE_INLINE)
            ->keyBy(fn (JournalEntryMedia $usage): string => strtolower((string) $usage->getAttribute('embed_key')));
        $blocks = [];
        foreach ($this->content->blocks($this->source($entry)) as $block) {
            if (($block['type'] ?? null) === 'text') { $blocks[] = $block; continue; }
            $key = strtolower((string) ($block['data']['embed_key'] ?? ''));
            $usage = $inline->get($key);
            if (! $usage instanceof JournalEntryMedia) { continue; }
            $blocks[] = ['type' => 'image', 'data' => [
                'embed_key' => $key,
                'media_asset_id' => (int) $usage->getAttribute('media_asset_id'),
                'alt_text_override' => $usage->getAttribute('alt_text_override'),
            ]];
        }
        if ($blocks === []) { $blocks[] = ['type' => 'text', 'data' => ['markdown' => '']]; }
        $cover = $usages->firstWhere('role', JournalEntryMedia::ROLE_COVER);
        $gallery = $usages->where('role', JournalEntryMedia::ROLE_GALLERY)->sortBy(['position', 'id'])->values()
            ->map(fn (JournalEntryMedia $usage): array => ['media_asset_id' => (int) $usage->getAttribute('media_asset_id'), 'alt_text_override' => $usage->getAttribute('alt_text_override')])->all();
        return [
            'content_blocks' => $blocks,
            'cover_media_asset_id' => $cover instanceof JournalEntryMedia ? (int) $cover->getAttribute('media_asset_id') : null,
            'cover_alt_text_override' => $cover instanceof JournalEntryMedia ? $cover->getAttribute('alt_text_override') : null,
            'gallery_images' => $gallery,
        ];
    }

    /** @param array<string,mixed> $data */
    public function syncEditor(BlogPost|Exhibition $entry, array $data): string
    {
        $blocks = is_array($data['content_blocks'] ?? null) ? array_values($data['content_blocks']) : [];
        $preparedBlocks = []; $inline = []; $seenEmbedKeys = [];
        foreach ($blocks as $block) {
            if (! is_array($block)) { throw ValidationException::withMessages(['content_blocks' => 'Journal content is invalid.']); }
            $type = $block['type'] ?? null;
            $blockData = is_array($block['data'] ?? null) ? $block['data'] : [];
            if ($type === 'text') {
                $preparedBlocks[] = ['type' => 'text', 'data' => ['markdown' => (string) ($blockData['markdown'] ?? '')]];
                continue;
            }
            if ($type !== 'image') { throw ValidationException::withMessages(['content_blocks' => 'Journal content contains an unsupported block.']); }
            $mediaAssetId = $this->mediaId($blockData['media_asset_id'] ?? null, 'content_blocks');
            $embedKey = strtolower(trim((string) ($blockData['embed_key'] ?? '')));
            if ($embedKey === '') { $embedKey = (string) Str::uuid(); }
            if (isset($seenEmbedKeys[$embedKey])) { throw ValidationException::withMessages(['content_blocks' => 'Each inline image needs its own internal reference.']); }
            $seenEmbedKeys[$embedKey] = true;
            $override = $this->altOverride($blockData['alt_text_override'] ?? null);
            $this->assertMediaReady($mediaAssetId, $override, 'content_blocks');
            $preparedBlocks[] = ['type' => 'image', 'data' => ['embed_key' => $embedKey]];
            $inline[] = compact('mediaAssetId', 'embedKey', 'override');
        }

        $source = $this->content->serialize($preparedBlocks);
        $entry->mediaUsages()->delete();
        $coverId = $data['cover_media_asset_id'] ?? null;
        if ($coverId !== null && $coverId !== '') {
            $coverId = $this->mediaId($coverId, 'cover_media_asset_id');
            $override = $this->altOverride($data['cover_alt_text_override'] ?? null);
            $this->assertMediaReady($coverId, $override, 'cover_media_asset_id');
            $this->createUsage($entry, $coverId, JournalEntryMedia::ROLE_COVER, 0, $override, null);
        }
        foreach ($inline as $index => $row) {
            $this->createUsage($entry, $row['mediaAssetId'], JournalEntryMedia::ROLE_INLINE, $index + 1, $row['override'], $row['embedKey']);
        }
        $gallery = is_array($data['gallery_images'] ?? null) ? array_values($data['gallery_images']) : [];
        foreach ($gallery as $index => $row) {
            if (! is_array($row)) { throw ValidationException::withMessages(['gallery_images' => 'Gallery images are invalid.']); }
            $mediaAssetId = $this->mediaId($row['media_asset_id'] ?? null, 'gallery_images');
            $override = $this->altOverride($row['alt_text_override'] ?? null);
            $this->assertMediaReady($mediaAssetId, $override, 'gallery_images');
            $this->createUsage($entry, $mediaAssetId, JournalEntryMedia::ROLE_GALLERY, $index + 1, $override, null);
        }
        return $source;
    }

    public function assertPublicReady(BlogPost|Exhibition $entry): void
    {
        $entry->loadMissing('mediaUsages.mediaAsset.variants');
        /** @var EloquentCollection<int, JournalEntryMedia> $usages */
        $usages = $entry->mediaUsages;
        foreach ($usages as $usage) {
            $this->assertMediaReady((int) $usage->getAttribute('media_asset_id'), $usage->getAttribute('alt_text_override'), 'media');
        }
        $inlineKeys = $usages->where('role', JournalEntryMedia::ROLE_INLINE)->pluck('embed_key')
            ->filter(fn (mixed $key): bool => is_string($key) && $key !== '')->map(fn (string $key): string => strtolower($key))->values()->all();
        $this->content->assertValid($this->source($entry), $inlineKeys);
        $markerCount = collect($this->content->blocks($this->source($entry)))->where('type', 'image')->count();
        if ($markerCount !== count($inlineKeys)) { throw ValidationException::withMessages(['media' => 'Journal content contains stale inline image references.']); }
    }

    public function detachAsset(MediaAsset $asset): void
    {
        /** @var EloquentCollection<int, JournalEntryMedia> $usages */
        $usages = JournalEntryMedia::query()->where('media_asset_id', $asset->getKey())
            ->with(['blogPost', 'exhibition'])->orderBy('id')->lockForUpdate()->get();
        $owners = [];
        foreach ($usages as $usage) {
            $entry = $usage->entry();
            if ($usage->getAttribute('role') === JournalEntryMedia::ROLE_INLINE && ($entry instanceof BlogPost || $entry instanceof Exhibition)) {
                $key = (string) $usage->getAttribute('embed_key');
                $source = $this->content->removeEmbed($this->source($entry), $key);
                $entry->setAttribute($entry instanceof BlogPost ? 'body' : 'description', $source === '' ? null : $source);
                $entry->save();
            }
            if ($entry instanceof BlogPost) { $owners['blog:'.$entry->getKey()] = $entry; }
            elseif ($entry instanceof Exhibition) { $owners['exhibition:'.$entry->getKey()] = $entry; }
            $usage->delete();
        }
        foreach ($owners as $entry) { $this->normalizeGallery($entry); }
    }

    private function createUsage(BlogPost|Exhibition $entry, int $mediaAssetId, string $role, int $position, ?string $override, ?string $embedKey): void
    {
        $usage = new JournalEntryMedia;
        $usage->fill([...$this->ownership($entry), 'media_asset_id' => $mediaAssetId, 'role' => $role, 'position' => $position, 'alt_text_override' => $override, 'embed_key' => $embedKey]);
        $usage->save();
    }

    /** @return array{blog_post_id:?int,exhibition_id:?int} */
    private function ownership(BlogPost|Exhibition $entry): array
    {
        return ['blog_post_id' => $entry instanceof BlogPost ? (int) $entry->getKey() : null, 'exhibition_id' => $entry instanceof Exhibition ? (int) $entry->getKey() : null];
    }

    private function normalizeGallery(BlogPost|Exhibition $entry): void
    {
        /** @var EloquentCollection<int, JournalEntryMedia> $gallery */
        $gallery = $entry->mediaUsages()->where('role', JournalEntryMedia::ROLE_GALLERY)->orderBy('position')->orderBy('id')->lockForUpdate()->get();
        foreach ($gallery as $index => $usage) {
            if ((int) $usage->getAttribute('position') !== $index + 1) { $usage->setAttribute('position', $index + 1); $usage->save(); }
        }
    }

    private function assertMediaReady(int $mediaAssetId, mixed $override, string $field): void
    {
        $asset = MediaAsset::query()->with('variants')->find($mediaAssetId);
        if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available' || ! MediaTypePolicy::isImage((string) $asset->getAttribute('mime_type'))) {
            throw ValidationException::withMessages([$field => 'Journal images must reference an available image in Media Files.']);
        }
        try { $this->publicMedia->altTextForAsset($asset, $override); $this->publicMedia->thumbnailVariantForAsset($asset); }
        catch (LogicException) { throw ValidationException::withMessages([$field => 'Journal images require ALT text and an available public image variant.']); }
    }

    private function mediaId(mixed $value, string $field): int
    {
        $id = filter_var($value, FILTER_VALIDATE_INT);
        if ($id === false || $id <= 0) { throw ValidationException::withMessages([$field => 'Choose an image from Media Files.']); }
        return (int) $id;
    }

    private function altOverride(mixed $value): ?string
    {
        if ($value === null || $value === '') { return null; }
        if (! is_string($value) || trim($value) === '' || mb_strlen($value) > 500) { throw ValidationException::withMessages(['media' => 'ALT override must be non-empty text of at most 500 characters.']); }
        return trim($value);
    }

    private function source(BlogPost|Exhibition $entry): string
    {
        return (string) ($entry instanceof BlogPost ? ($entry->getAttribute('body') ?? '') : ($entry->getAttribute('description') ?? ''));
    }
}
