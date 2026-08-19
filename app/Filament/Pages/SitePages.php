<?php

namespace App\Filament\Pages;

use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogSettings\BlogSettingResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\SiteSection;
use BackedEnum;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use UnitEnum;

final class SitePages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $title = 'Pages';

    protected static ?string $slug = 'pages';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.site-pages';

    /** @var list<array<string, mixed>> */
    public array $sections = [];

    public function mount(): void
    {
        $this->loadSections();
    }

    public function moveSection(int $sectionId, string $direction): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        $moved = app(SiteSectionOrderService::class)->move($section, $direction);

        if ($moved) {
            Notification::make()
                ->title('Site order updated')
                ->success()
                ->send();
            $this->loadSections();
        }
    }

    private function loadSections(): void
    {
        /** @var EloquentCollection<int, SiteSection> $topLevel */
        $topLevel = SiteSection::query()
            ->whereNull('parent_id')
            ->with(['children' => static function (Relation $relation): void {
                $query = $relation->getQuery();
                $query->orderBy('position');
                $query->orderBy('id');
            }])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $counts = [
            SiteSection::TYPE_HOME => Artwork::query()->where('featured_on_home', true)->count(),
            SiteSection::TYPE_VITA => CvEntry::query()->count(),
            SiteSection::TYPE_BLOG => BlogPost::query()->count(),
            SiteSection::TYPE_EXHIBITIONS => Exhibition::query()->count(),
        ];

        $galleryCounts = [];
        /** @var EloquentCollection<int, ArtworkCategory> $categories */
        $categories = ArtworkCategory::query()->withCount('artworks')->get(['id']);
        foreach ($categories as $category) {
            $galleryCounts[(int) $category->getKey()] = (int) $category->getAttribute('artworks_count');
        }

        $rows = [];
        foreach ($topLevel as $section) {
            $rows[] = $this->row($section, 0, $counts, $galleryCounts);
            /** @var EloquentCollection<int, SiteSection> $children */
            $children = $section->getRelation('children');
            foreach ($children as $child) {
                $rows[] = $this->row($child, 1, $counts, $galleryCounts);
            }
        }

        $this->sections = $rows;
    }

    /**
     * @param array<string, int> $counts
     * @param array<int, int> $galleryCounts
     * @return array<string, mixed>
     */
    private function row(SiteSection $section, int $depth, array $counts, array $galleryCounts): array
    {
        $type = (string) $section->getAttribute('type');
        $categoryId = $section->getAttribute('artwork_category_id');
        $contentCount = $type === SiteSection::TYPE_GALLERY && is_int($categoryId)
            ? ($galleryCounts[$categoryId] ?? 0)
            : ($counts[$type] ?? 0);

        return [
            'id' => (int) $section->getKey(),
            'type' => $type,
            'type_label' => match ($type) {
                SiteSection::TYPE_HOME => 'Home',
                SiteSection::TYPE_GALLERY => 'Gallery',
                SiteSection::TYPE_VITA => 'Vita',
                SiteSection::TYPE_BLOG => 'Blog',
                SiteSection::TYPE_EXHIBITIONS => 'Exhibitions',
                default => ucfirst($type),
            },
            'title' => (string) $section->getAttribute('title'),
            'navigation_label' => $section->getAttribute('navigation_label'),
            'state' => (string) $section->getAttribute('state'),
            'visible' => (bool) $section->getAttribute('show_in_navigation'),
            'position' => (int) $section->getAttribute('position'),
            'depth' => $depth,
            'count' => $contentCount,
            'count_label' => match ($type) {
                SiteSection::TYPE_GALLERY, SiteSection::TYPE_HOME => $contentCount === 1 ? 'artwork' : 'artworks',
                SiteSection::TYPE_VITA => $contentCount === 1 ? 'entry' : 'entries',
                SiteSection::TYPE_BLOG => $contentCount === 1 ? 'post' : 'posts',
                SiteSection::TYPE_EXHIBITIONS => $contentCount === 1 ? 'exhibition' : 'exhibitions',
                default => 'items',
            },
            'public_url' => $section->publicUrl(),
            'can_move_up' => app(SiteSectionOrderService::class)->canMove($section, 'up'),
            'can_move_down' => app(SiteSectionOrderService::class)->canMove($section, 'down'),
            'editor_url' => $this->editorUrl($section),
            'content_url' => $this->contentUrl($section),
        ];
    }

    private function editorUrl(SiteSection $section): ?string
    {
        return match ($section->getAttribute('type')) {
            SiteSection::TYPE_GALLERY => ArtworkCategoryResource::getUrl('edit', [
                'record' => $section->getAttribute('artwork_category_id'),
            ]),
            SiteSection::TYPE_VITA, SiteSection::TYPE_EXHIBITIONS => PublicContentSettingResource::getUrl('edit', ['record' => 1]),
            SiteSection::TYPE_BLOG => BlogSettingResource::getUrl('edit', ['record' => 1]),
            default => null,
        };
    }

    private function contentUrl(SiteSection $section): string
    {
        return match ($section->getAttribute('type')) {
            SiteSection::TYPE_HOME, SiteSection::TYPE_GALLERY => ArtworkResource::getUrl('index'),
            SiteSection::TYPE_VITA => CvEntryResource::getUrl('index'),
            SiteSection::TYPE_BLOG => BlogPostResource::getUrl('index'),
            SiteSection::TYPE_EXHIBITIONS => ExhibitionResource::getUrl('index'),
            default => route('home'),
        };
    }
}
