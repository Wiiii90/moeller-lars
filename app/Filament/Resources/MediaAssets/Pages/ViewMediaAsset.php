<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\Artwork;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use LogicException;

final class ViewMediaAsset extends ViewRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected string $view = 'filament.resources.media-assets.pages.view-media-asset';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('library')
                ->label('Back to Media')
                ->url(MediaAssetResource::getUrl('index')),
            Action::make('edit')
                ->label('Edit metadata')
                ->url(fn (): string => MediaAssetResource::getUrl('edit', ['record' => $this->mediaAssetRecord()])),
        ];
    }

    protected function getViewData(): array
    {
        $asset = $this->mediaAssetRecord();
        $asset->loadMissing(['artworks', 'exhibitions', 'cvEntries', 'blogPosts']);
        $available = $asset->getAttribute('state') === 'available';

        return [
            'media' => [
                'filename' => (string) $asset->getAttribute('original_filename'),
                'state' => (string) $asset->getAttribute('state'),
                'original_url' => $available ? route('admin.media.original', $asset) : null,
                'alt' => (string) ($asset->getAttribute('alt_text') ?? ''),
                'alt_label' => filled($asset->getAttribute('alt_text')) ? (string) $asset->getAttribute('alt_text') : 'ALT text missing',
                'credit' => filled($asset->getAttribute('credit')) ? (string) $asset->getAttribute('credit') : '—',
                'copyright' => filled($asset->getAttribute('copyright_notice')) ? (string) $asset->getAttribute('copyright_notice') : '—',
                'dimensions' => $asset->getAttribute('width') && $asset->getAttribute('height')
                    ? $asset->getAttribute('width').'×'.$asset->getAttribute('height')
                    : '—',
                'size' => $this->formatBytes((int) $asset->getAttribute('byte_size')),
                'type' => (string) $asset->getAttribute('mime_type'),
            ],
            'sequence' => $this->artworkSequence($asset),
            'usages' => $this->usageRows($asset),
        ];
    }

    private function mediaAssetRecord(): MediaAsset
    {
        $record = $this->getRecord();
        if (! $record instanceof MediaAsset) {
            throw new LogicException('Media resource resolved an invalid record.');
        }

        return $record;
    }

    /** @return array<string, mixed>|null */
    private function artworkSequence(MediaAsset $asset): ?array
    {
        $artworkId = request()->integer('artwork');
        if ($artworkId <= 0) {
            return null;
        }

        /** @var Artwork|null $artwork */
        $artwork = Artwork::query()->with('category')->find($artworkId);
        if ($artwork === null) {
            return null;
        }

        /** @var EloquentCollection<int, ArtworkMedia> $usages */
        $usages = ArtworkMedia::query()
            ->where('artwork_id', $artwork->getKey())
            ->with('mediaAsset')
            ->orderByRaw("CASE WHEN role = 'primary' THEN 0 ELSE 1 END")
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $sequence = [];
        foreach ($usages as $usage) {
            /** @var MediaAsset|null $candidate */
            $candidate = $usage->getRelationValue('mediaAsset');
            if ($candidate === null || $candidate->getAttribute('state') !== 'available') {
                continue;
            }

            $sequence[] = [
                'asset_id' => (int) $candidate->getKey(),
                'role' => (string) $usage->getAttribute('role'),
            ];
        }

        $currentIndex = null;
        foreach ($sequence as $index => $item) {
            if ($item['asset_id'] === (int) $asset->getKey()) {
                $currentIndex = $index;
                break;
            }
        }
        if ($currentIndex === null) {
            return null;
        }

        $previous = $currentIndex > 0 ? $sequence[$currentIndex - 1] : null;
        $next = $currentIndex < count($sequence) - 1 ? $sequence[$currentIndex + 1] : null;
        $categoryId = $artwork->getAttribute('artwork_category_id');

        return [
            'artwork_title' => (string) $artwork->getAttribute('title'),
            'role_label' => $sequence[$currentIndex]['role'] === 'primary' ? 'Primary image' : 'Gallery image',
            'position_label' => ($currentIndex + 1).' / '.count($sequence),
            'previous_url' => $previous === null ? null : MediaAssetResource::getUrl('view', [
                'record' => $previous['asset_id'],
                'artwork' => $artwork->getKey(),
            ]),
            'next_url' => $next === null ? null : MediaAssetResource::getUrl('view', [
                'record' => $next['asset_id'],
                'artwork' => $artwork->getKey(),
            ]),
            'artwork_url' => ArtworkResource::getUrl('edit', ['record' => $artwork->getKey()]),
            'gallery_url' => is_int($categoryId) ? ArtworkResource::getUrl('gallery', ['gallery' => $categoryId]) : null,
        ];
    }

    /** @return list<array{type:string,label:string,url:string}> */
    private function usageRows(MediaAsset $asset): array
    {
        $rows = [];

        /** @var EloquentCollection<int, Artwork> $artworks */
        $artworks = $asset->getRelation('artworks');
        foreach ($artworks as $artwork) {
            $rows[] = [
                'type' => 'Artwork',
                'label' => (string) $artwork->getAttribute('title'),
                'url' => ArtworkResource::getUrl('edit', ['record' => $artwork->getKey()]),
            ];
        }

        /** @var EloquentCollection<int, Exhibition> $exhibitions */
        $exhibitions = $asset->getRelation('exhibitions');
        foreach ($exhibitions as $exhibition) {
            $rows[] = [
                'type' => 'Exhibition',
                'label' => (string) $exhibition->getAttribute('title'),
                'url' => ExhibitionResource::getUrl('edit', ['record' => $exhibition->getKey()]),
            ];
        }

        /** @var EloquentCollection<int, CvEntry> $cvEntries */
        $cvEntries = $asset->getRelation('cvEntries');
        foreach ($cvEntries as $entry) {
            $rows[] = [
                'type' => 'Vita / CV',
                'label' => (string) $entry->getAttribute('title'),
                'url' => CvEntryResource::getUrl('edit', ['record' => $entry->getKey()]),
            ];
        }

        /** @var EloquentCollection<int, BlogPost> $blogPosts */
        $blogPosts = $asset->getRelation('blogPosts');
        foreach ($blogPosts as $post) {
            $rows[] = [
                'type' => 'Blog',
                'label' => (string) $post->getAttribute('title'),
                'url' => BlogPostResource::getUrl('edit', ['record' => $post->getKey()]),
            ];
        }

        return $rows;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }

        return number_format($bytes / (1024 * 1024), 1).' MB';
    }
}
