<?php

namespace App\Filament\Resources\MediaAssets\Pages;

use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Filament\Resources\MediaAssets\MediaAssetResource;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use DateTimeInterface;
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
    private const PER_PAGE = 50;

    protected static string $resource = MediaAssetResource::class;

    protected string $view = 'filament.resources.media-assets.pages.list-media-assets';

    /** @var list<array<string, mixed>> */
    public array $assets = [];

    public string $usage = 'all';

    public string $type = 'all';

    public string $context = 'all';

    public string $state = 'available';

    public string $viewMode = 'list';

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
        $this->refreshFromFirstPage();
    }

    public function updatedUsage(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedType(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedContext(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedState(): void
    {
        $this->refreshFromFirstPage();
    }

    public function setViewMode(string $mode): void
    {
        if (! in_array($mode, ['list', 'grid', 'dense'], true)) {
            return;
        }

        $this->viewMode = $mode;
    }

    public function resetFilters(): void
    {
        $this->usage = 'all';
        $this->type = 'all';
        $this->context = 'all';
        $this->state = 'available';
        $this->search = '';
        $this->refreshFromFirstPage();
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
                        ->acceptedFileTypes(MediaTypePolicy::acceptedMimeTypes())
                        ->maxSize((int) ceil(MediaTypePolicy::maxUploadBytes() / 1024))
                        ->helperText('Images: JPEG, PNG, WebP. Video: browser-native H.264 MP4 or VP8/VP9/AV1 WebM. Type-specific byte limits are operator configured.'),
                ])
                ->action(function (array $data): void {
                    if (! array_key_exists('media', $data) || ! $data['media'] instanceof TemporaryUploadedFile) {
                        throw ValidationException::withMessages(['media' => 'A valid media upload is required.']);
                    }

                    app(MediaIngestService::class)->ingest($data['media']);
                    $this->page = 1;
                    $this->loadLibrary();
                    Notification::make()->title('Media uploaded')->success()->send();
                }),
        ];
    }

    private function refreshFromFirstPage(): void
    {
        $this->page = 1;
        $this->loadLibrary();
    }

    private function loadLibrary(): void
    {
        $this->inUse = $this->usageQuery(true)->count();
        $this->unused = $this->usageQuery(false)->count();

        /** @var Builder<MediaAsset> $query */
        $query = MediaAsset::query()
            ->with('variants')
            ->withCount(['artworks', 'exhibitions', 'cvEntries', 'blogPosts', 'siteIdentitySettings']);

        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function (Builder $search) use ($term): void {
                $search->where('original_filename', 'ilike', '%'.$term.'%')
                    ->orWhere('alt_text', 'ilike', '%'.$term.'%')
                    ->orWhere('credit', 'ilike', '%'.$term.'%')
                    ->orWhere('copyright_notice', 'ilike', '%'.$term.'%')
                    ->orWhere('mime_type', 'ilike', '%'.$term.'%');
            });
        }

        if ($this->state !== 'all' && in_array($this->state, ['available', 'quarantined', 'deleted'], true)) {
            $query->where('state', $this->state);
        }

        $this->applyTypeFilter($query);
        $this->applyContextFilter($query);

        if ($this->usage === 'used') {
            $this->applyUsageFilter($query, true);
        } elseif ($this->usage === 'unused') {
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
                return $variant->getAttribute('variant_kind') === MediaIngestService::THUMBNAIL_KIND
                    && $variant->getAttribute('transform_profile') === MediaIngestService::TRANSFORM_PROFILE
                    && $variant->getAttribute('state') === 'available';
            });
            $usageCounts = [
                'Artwork' => (int) $asset->getAttribute('artworks_count'),
                'Exhibition' => (int) $asset->getAttribute('exhibitions_count'),
                'Vita / CV' => (int) $asset->getAttribute('cv_entries_count'),
                'Blog' => (int) $asset->getAttribute('blog_posts_count'),
                'Site identity' => (int) $asset->getAttribute('site_identity_settings_count'),
            ];
            $usage = array_sum($usageCounts);
            $usageParts = [];
            foreach ($usageCounts as $label => $count) {
                if ($count > 0) {
                    $usageParts[] = $count.' '.$label;
                }
            }
            $mime = (string) $asset->getAttribute('mime_type');
            $createdAt = $asset->getAttribute('created_at');

            return [
                'id' => (int) $asset->getKey(),
                'filename' => (string) $asset->getAttribute('original_filename'),
                'state' => (string) $asset->getAttribute('state'),
                'alt_missing' => MediaTypePolicy::isImage($mime) && blank($asset->getAttribute('alt_text')),
                'credit' => (string) ($asset->getAttribute('credit') ?? ''),
                'size' => self::formatBytes((int) $asset->getAttribute('byte_size')),
                'dimensions' => $asset->getAttribute('width') && $asset->getAttribute('height')
                    ? $asset->getAttribute('width').'×'.$asset->getAttribute('height')
                    : '—',
                'usage' => $usage,
                'shared' => $usage > 1,
                'usage_label' => $usageParts === [] ? 'Unreferenced' : implode(' · ', $usageParts),
                'mime' => $mime,
                'type_label' => MediaTypePolicy::label($mime),
                'kind' => MediaTypePolicy::kind($mime),
                'thumbnail_url' => $thumbnail === null ? null : route('admin.media.variant', $thumbnail),
                'thumbnail_width' => $thumbnail?->getAttribute('width'),
                'thumbnail_height' => $thumbnail?->getAttribute('height'),
                'created' => $createdAt instanceof DateTimeInterface ? $createdAt->format('Y-m-d') : '—',
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
        $relations = ['artworks', 'exhibitions', 'cvEntries', 'blogPosts', 'siteIdentitySettings'];
        if (! $used) {
            foreach ($relations as $relation) {
                $query->whereDoesntHave($relation);
            }

            return;
        }

        $query->where(function (Builder $usage) use ($relations): void {
            foreach ($relations as $index => $relation) {
                $index === 0 ? $usage->whereHas($relation) : $usage->orWhereHas($relation);
            }
        });
    }

    /** @param Builder<MediaAsset> $query */
    private function applyTypeFilter(Builder $query): void
    {
        if ($this->type === 'image') {
            $query->whereIn('mime_type', MediaTypePolicy::IMAGE_MIME_TYPES);

            return;
        }
        if ($this->type === 'video') {
            $query->whereIn('mime_type', MediaTypePolicy::VIDEO_MIME_TYPES);

            return;
        }
        if (in_array($this->type, MediaTypePolicy::acceptedMimeTypes(), true)) {
            $query->where('mime_type', $this->type);
        }
    }

    /** @param Builder<MediaAsset> $query */
    private function applyContextFilter(Builder $query): void
    {
        if ($this->context === 'artwork') {
            $query->whereHas('artworks');

            return;
        }
        if ($this->context === 'exhibition') {
            $query->whereHas('exhibitions');

            return;
        }
        if ($this->context === 'vita') {
            $query->whereHas('cvEntries');

            return;
        }
        if ($this->context === 'blog') {
            $query->whereHas('blogPosts');

            return;
        }
        if ($this->context === 'identity') {
            $query->whereHas('siteIdentitySettings');

            return;
        }
        if ($this->context === 'unassigned') {
            $this->applyUsageFilter($query, false);
        }
    }

    private static function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        if ($bytes < 1024 * 1024) {
            return number_format($bytes / 1024, 1).' KB';
        }
        if ($bytes < 1024 * 1024 * 1024) {
            return number_format($bytes / (1024 * 1024), 1).' MB';
        }

        return number_format($bytes / (1024 * 1024 * 1024), 2).' GB';
    }
}
