<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogSettings\BlogSettingResource;
use App\Filament\Resources\ContactContentSettings\ContactContentSettingResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\VitaContentSettings\VitaContentSettingResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\SiteSection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class SitePages extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $title = 'Pages';

    protected static ?string $slug = 'pages';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.site-pages';

    /** @var list<array<string, mixed>> */
    public array $sections = [];

    /** @var list<array{id: int, label: string, type: string}> */
    public array $parentCandidates = [];

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
            Notification::make()->title('Site order updated')->success()->send();
            $this->loadSections();
        }
    }

    public function toggleSectionState(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if ((string) $section->getAttribute('type') === SiteSection::TYPE_HOME) {
            return;
        }

        $state = (string) $section->getAttribute('state') === 'published' ? 'hidden' : 'published';
        $this->updatePlacement($section, $state, (bool) $section->getAttribute('show_in_navigation'), $section->getAttribute('parent_id'));
    }

    public function toggleSectionNavigation(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if ((string) $section->getAttribute('type') === SiteSection::TYPE_HOME) {
            return;
        }

        $this->updatePlacement(
            $section,
            (string) $section->getAttribute('state'),
            ! (bool) $section->getAttribute('show_in_navigation'),
            $section->getAttribute('parent_id'),
        );
    }

    public function moveSectionParent(int $sectionId, int|string|null $parentSectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        $parentId = filled($parentSectionId) ? (int) $parentSectionId : null;
        $this->updatePlacement(
            $section,
            (string) $section->getAttribute('state'),
            (bool) $section->getAttribute('show_in_navigation'),
            $parentId,
        );
    }

    // Compatibility for the existing Gallery workspace calls while placement becomes generic.
    public function toggleGalleryState(int $sectionId): void
    {
        $this->toggleSectionState($sectionId);
    }

    public function toggleGalleryNavigation(int $sectionId): void
    {
        $this->toggleSectionNavigation($sectionId);
    }

    public function moveGallery(int $sectionId, int|string|null $parentSectionId): void
    {
        $this->moveSectionParent($sectionId, $parentSectionId);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewSite')
                ->label('Preview site')
                ->icon(Heroicon::OutlinedEye)
                ->url(fn (): string => app(SitePreviewContext::class)->previewSiteUrl())
                ->openUrlInNewTab(),
            Action::make('addSection')
                ->label('Add page/section')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    Select::make('type')
                        ->label('Section type')
                        ->options([
                            SiteSection::TYPE_GALLERY => 'Gallery',
                            SiteSection::TYPE_NAVIGATION_GROUP => 'Navigation group (no page)',
                        ])
                        ->required(),
                    TextInput::make('title')->label('Title')->required()->maxLength(160),
                    TextInput::make('slug')
                        ->label('Public URL slug (Gallery only)')
                        ->maxLength(80)
                        ->helperText('Navigation groups have no URL. Gallery slugs use lowercase letters, numbers and hyphens.'),
                ])
                ->action(function (array $data): void {
                    $type = (string) ($data['type'] ?? '');
                    $title = trim((string) ($data['title'] ?? ''));

                    if ($type === SiteSection::TYPE_NAVIGATION_GROUP) {
                        app(SiteSectionEditorialService::class)->createNavigationGroup($title);
                        $message = 'Navigation group created as hidden';
                    } elseif ($type === SiteSection::TYPE_GALLERY) {
                        $slug = trim((string) ($data['slug'] ?? ''));
                        if ($slug === '' || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug) !== 1) {
                            throw ValidationException::withMessages(['slug' => 'A valid Gallery URL slug is required.']);
                        }

                        app(ArtworkCategoryEditorialService::class)->create([
                            'name' => $title,
                            'slug' => $slug,
                            'parent_section_id' => null,
                            'description' => null,
                            'show_on_home' => false,
                        ]);
                        $message = 'Gallery created as hidden';
                    } else {
                        throw ValidationException::withMessages(['type' => 'Choose a supported typed section.']);
                    }

                    $this->loadSections();
                    Notification::make()
                        ->title($message)
                        ->body('Save content and use Preview before publishing it.')
                        ->success()
                        ->send();
                }),
        ];
    }

    private function loadSections(): void
    {
        /** @var Builder<SiteSection> $topLevelQuery */
        $topLevelQuery = SiteSection::query();
        $topLevelQuery->whereNull('parent_id');

        /** @var EloquentCollection<int, SiteSection> $topLevel */
        $topLevel = $topLevelQuery
            ->with(['children' => static function (Relation $relation): void {
                $query = $relation->getQuery();
                $query->orderBy('position');
                $query->orderBy('id');
            }])
            ->orderBy('position')
            ->orderBy('id')
            ->get();

        $this->parentCandidates = $topLevel
            ->filter(static fn (SiteSection $section): bool => $section->canContainChildren())
            ->map(static fn (SiteSection $section): array => [
                'id' => (int) $section->getKey(),
                'label' => (string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')),
                'type' => (string) $section->getAttribute('type'),
            ])
            ->values()
            ->all();

        $counts = [
            SiteSection::TYPE_HOME => Artwork::query()->where('featured_on_home', true)->count(),
            SiteSection::TYPE_VITA => CvEntry::query()->count(),
            SiteSection::TYPE_BLOG => BlogPost::query()->count(),
            SiteSection::TYPE_EXHIBITIONS => Exhibition::query()->count(),
            SiteSection::TYPE_CONTACT => 0,
            SiteSection::TYPE_NAVIGATION_GROUP => 0,
        ];

        $galleryCounts = [];
        /** @var EloquentCollection<int, ArtworkCategory> $categories */
        $categories = ArtworkCategory::query()->withCount('artworks')->get(['id']);
        foreach ($categories as $category) {
            $galleryCounts[(int) $category->getKey()] = (int) $category->getAttribute('artworks_count');
        }

        $rows = [];
        foreach ($topLevel as $section) {
            $row = $this->row($section, 0, $counts, $galleryCounts);
            /** @var EloquentCollection<int, SiteSection> $children */
            $children = $section->getRelation('children');
            $row['children'] = $children
                ->map(fn (SiteSection $child): array => $this->row($child, 1, $counts, $galleryCounts))
                ->values()
                ->all();
            $rows[] = $row;
        }

        $this->sections = $rows;
    }

    /**
     * @param  array<string, int>  $counts
     * @param  array<int, int>  $galleryCounts
     * @return array<string, mixed>
     */
    private function row(SiteSection $section, int $depth, array $counts, array $galleryCounts): array
    {
        $type = (string) $section->getAttribute('type');
        $categoryId = $section->getAttribute('artwork_category_id');
        $contentCount = $type === SiteSection::TYPE_GALLERY && is_int($categoryId)
            ? ($galleryCounts[$categoryId] ?? 0)
            : ($counts[$type] ?? 0);
        $publicUrl = $section->publicUrl();

        return [
            'id' => (int) $section->getKey(),
            'type' => $type,
            'type_label' => match ($type) {
                SiteSection::TYPE_HOME => 'Home',
                SiteSection::TYPE_GALLERY => 'Gallery',
                SiteSection::TYPE_NAVIGATION_GROUP => 'Navigation group',
                SiteSection::TYPE_VITA => 'Vita',
                SiteSection::TYPE_BLOG => 'Blog',
                SiteSection::TYPE_EXHIBITIONS => 'Exhibitions',
                SiteSection::TYPE_CONTACT => 'Contact',
                default => ucfirst($type),
            },
            'title' => (string) $section->getAttribute('title'),
            'navigation_label' => $section->getAttribute('navigation_label'),
            'state' => (string) $section->getAttribute('state'),
            'visible' => (bool) $section->getAttribute('show_in_navigation'),
            'position' => (int) $section->getAttribute('position'),
            'parent_id' => $section->getAttribute('parent_id'),
            'has_children' => $section->relationLoaded('children') && $section->getRelation('children')->isNotEmpty(),
            'depth' => $depth,
            'count' => $contentCount,
            'count_label' => match ($type) {
                SiteSection::TYPE_GALLERY, SiteSection::TYPE_HOME => $contentCount === 1 ? 'artwork' : 'artworks',
                SiteSection::TYPE_VITA => $contentCount === 1 ? 'entry' : 'entries',
                SiteSection::TYPE_BLOG => $contentCount === 1 ? 'post' : 'posts',
                SiteSection::TYPE_EXHIBITIONS => $contentCount === 1 ? 'exhibition' : 'exhibitions',
                SiteSection::TYPE_CONTACT => 'typed page',
                SiteSection::TYPE_NAVIGATION_GROUP => 'no page',
                default => 'items',
            },
            'public_url' => $publicUrl,
            'preview_url' => app(SitePreviewContext::class)->previewUrlFor($section),
            'can_move_up' => app(SiteSectionOrderService::class)->canMove($section, 'up'),
            'can_move_down' => app(SiteSectionOrderService::class)->canMove($section, 'down'),
            'editor_url' => $this->editorUrl($section),
            'content_url' => $this->contentUrl($section),
        ];
    }

    private function updatePlacement(SiteSection $section, string $state, bool $visible, mixed $parentId): void
    {
        try {
            app(SiteSectionEditorialService::class)->updatePlacement(
                $section,
                $state,
                $visible,
                $parentId === null ? null : (int) $parentId,
            );
            Notification::make()->title('Section placement updated')->success()->send();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            Notification::make()
                ->title('Section placement unchanged')
                ->body(is_string($message) ? $message : 'The requested section placement is invalid.')
                ->danger()
                ->send();
        }

        $this->loadSections();
    }

    private function editorUrl(SiteSection $section): ?string
    {
        return match ($section->getAttribute('type')) {
            SiteSection::TYPE_GALLERY => ArtworkCategoryResource::getUrl('edit', ['record' => $section->getAttribute('artwork_category_id')]),
            SiteSection::TYPE_VITA => VitaContentSettingResource::getSettingsUrl(),
            SiteSection::TYPE_CONTACT => ContactContentSettingResource::getSettingsUrl(),
            SiteSection::TYPE_BLOG => BlogSettingResource::getSettingsUrl(),
            default => null,
        };
    }

    private function contentUrl(SiteSection $section): ?string
    {
        return match ($section->getAttribute('type')) {
            SiteSection::TYPE_HOME => ArtworkResource::getUrl('index'),
            SiteSection::TYPE_GALLERY => ArtworkResource::getUrl('gallery', ['gallery' => $section->getAttribute('artwork_category_id')]),
            SiteSection::TYPE_VITA => CvEntryResource::getUrl('index'),
            SiteSection::TYPE_BLOG => BlogPostResource::getUrl('index'),
            SiteSection::TYPE_EXHIBITIONS => ExhibitionResource::getUrl('index'),
            SiteSection::TYPE_CONTACT => ContactContentSettingResource::getSettingsUrl(),
            SiteSection::TYPE_NAVIGATION_GROUP => null,
            default => null,
        };
    }
}
