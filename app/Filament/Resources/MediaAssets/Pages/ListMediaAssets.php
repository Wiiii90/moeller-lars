<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaIngestService;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class ListMediaAssets extends Page
{
    private const PER_PAGE = 48;

    protected static string $resource = MediaAssetResource::class;

    protected string $view = 'filament.resources.media-assets.pages.list-media-assets';

    /** @var list<array<string, mixed>> */
    public array $assets = [];

    public string $filter = 'all';

    public string $search = '';

    public int $page = 1;

    public int $total = 0;

    public int $pages = 1;

    public int $inUse = 0;

    public int $unused = 0;

    public function mount(): void
    {
        $this->loadLibrary();
    }

    public function updatedSearch(): void
    {
        $this->page = 1;
        $this->loadLibrary();
    }

    public function showFilter(string $filter): void
    {
        if (! in_array($filter, ['all', 'used', 'unused'], true)) {
            return;
        }

        $this->filter = $filter;
        $this->page = 1;
        $this->loadLibrary();
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->loadLibrary();
        }
    }

    public function nextPage(): void
    {
        if ($this->page < $this->pages) {
            $this->page++;
            $this->loadLibrary();
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('upload')
                ->label('Upload media')
                ->schema([
                    FileUpload::make('media')
                        ->required()
                        ->storeFiles(false)
                        ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                        ->maxSize((int) ceil(MediaIngestService::MAX_BYTES / 1024)),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['media' => 'A valid uploaded image is required.']);
                    }

                    app(MediaIngestService::class)->ingest($data['media']);
                    $this->filter = 'all';
                    $this->search = '';
                    $this->page = 1;
                    $this->loadLibrary();
                    Notification::make()->title('Media uploaded')->success()->send();
                }),
        ];
    }

    private function loadLibrary(): void
    {
        $this->inUse = $this->usageQuery(true)->count();
        $this->unused = $this->usageQuery(false)->count();

        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query()
            ->with('variants')
            ->withCount(['artworks', 'exhibitions', 'cvEntries', 'blogPosts']);

        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function (Builder $search) use ($term): void {
                $search->where('original_filename', 'ilike', '%'.$term.'%')
                    ->orWhere('alt_text', 'ilike', '%'.$term.'%')
                    ->orWhere('credit', 'ilike', '%'.$term.'%');
            });
        }

        if ($this->filter === 'used') {
            $this->applyUsageFilter($query, true);
        } elseif ($this->filter === 'unused') {
            $this->applyUsageFilter($query, false);
        }

        $this->total = (clone $query)->count();
        $this->pages = max(1, (int) ceil($this->total / self::PER_PAGE));
        $this->page = min($this->page, $this->pages);

        /** @var EloquentCollection<int, MediaAsset> $records */
        $records = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->forPage($this->page, self::PER_PAGE)
            ->get();

        $this->assets = $records->map(static function (MediaAsset $asset): array {
            /** @var MediaVariant|null $thumbnail */
            $thumbnail = $asset->getRelationValue('variants')->first(static function (MediaVariant $variant): bool {
                return $variant->getAttribute('variant_kind') === 'thumbnail'
                    && $variant->getAttribute('transform_profile') === 'public-v1'
                    && $variant->getAttribute('state') === 'available';
            });
            $usage = (int) $asset->getAttribute('artworks_count')
                + (int) $asset->getAttribute('exhibitions_count')
                + (int) $asset->getAttribute('cv_entries_count')
                + (int) $asset->getAttribute('blog_posts_count');
            $usageParts = [];
            foreach ([
                'artworks' => (int) $asset->getAttribute('artworks_count'),
                'exhibitions' => (int) $asset->getAttribute('exhibitions_count'),
                'Vita' => (int) $asset->getAttribute('cv_entries_count'),
                'blog' => (int) $asset->getAttribute('blog_posts_count'),
            ] as $label => $count) {
                if ($count > 0) {
                    $usageParts[] = $count.' '.$label;
                }
            }

            return [
                'id' => (int) $asset->getKey(),
                'filename' => (string) $asset->getAttribute('original_filename'),
                'state' => (string) $asset->getAttribute('state'),
                'alt_missing' => blank($asset->getAttribute('alt_text')),
                'size' => self::formatBytes((int) $asset->getAttribute('byte_size')),
                'dimensions' => $asset->getAttribute('width') && $asset->getAttribute('height')
                    ? $asset->getAttribute('width').'×'.$asset->getAttribute('height')
                    : '—',
                'usage' => $usage,
                'usage_label' => $usageParts === [] ? 'Unused' : implode(' · ', $usageParts),
                'thumbnail_url' => $thumbnail === null ? null : route('admin.media.variant', $thumbnail),
                'thumbnail_width' => $thumbnail?->getAttribute('width'),
                'thumbnail_height' => $thumbnail?->getAttribute('height'),
                'edit_url' => MediaAssetResource::getUrl('edit', ['record' => $asset->getKey()]),
                'preview_url' => MediaAssetResource::getUrl('view', ['record' => $asset->getKey()]),
            ];
        })->all();
    }

    /** @return Builder<MediaAsset> */
    private function usageQuery(bool $used): Builder
    {
        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query()->where('state', 'available');
        $this->applyUsageFilter($query, $used);

        return $query;
    }

    /** @param Builder<MediaAsset> $query */
    private function applyUsageFilter(Builder $query, bool $used): void
    {
        if (! $used) {
            $query->whereDoesntHave('artworks')
                ->whereDoesntHave('exhibitions')
                ->whereDoesntHave('cvEntries')
                ->whereDoesntHave('blogPosts');

            return;
        }

        $query->where(function (Builder $usage): void {
            $usage->whereHas('artworks')
                ->orWhereHas('exhibitions')
                ->orWhereHas('cvEntries')
                ->orWhereHas('blogPosts');
        });
    }

    private static function formatBytes(int $bytes): string
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
