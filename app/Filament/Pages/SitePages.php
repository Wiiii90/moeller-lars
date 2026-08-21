<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Resources\JournalSettings\JournalSettingResource;
use App\Models\ArtworkCategory;
use App\Models\CustomPageSetting;
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
        if (app(SiteSectionOrderService::class)->move($section, $direction)) {
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

    public function deleteSection(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if ((string) $section->getAttribute('type') === SiteSection::TYPE_HOME) {
            return;
        }

        try {
            if ((string) $section->getAttribute('type') === SiteSection::TYPE_GALLERY) {
                /** @var ArtworkCategory $category */
                $category = ArtworkCategory::query()->findOrFail((int) $section->getAttribute('artwork_category_id'));
                app(ArtworkCategoryEditorialService::class)->delete($category);
            } else {
                app(SiteSectionEditorialService::class)->deleteConfigurableSection($section);
            }

            Notification::make()->title('Page removed')->success()->send();
        } catch (ValidationException $exception) {
            $message = collect($exception->errors())->flatten()->first();
            Notification::make()
                ->title('Page was not removed')
                ->body(is_string($message) ? $message : 'This page cannot be removed yet.')
                ->danger()
                ->send();
        }

        $this->loadSections();
    }

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
                        ->label('Page type')
                        ->options([
                            SiteSection::TYPE_GALLERY => 'Gallery',
                            SiteSection::TYPE_JOURNAL => 'Journal',
                            SiteSection::TYPE_CUSTOM => 'Custom Page',
                            SiteSection::TYPE_NAVIGATION_GROUP => 'Navigation Node',
                        ])
                        ->required()
                        ->live(),
                    Select::make('template')
                        ->label('Journal template')
                        ->options([
                            SiteSection::JOURNAL_TEMPLATE_BLOG => 'Blog',
                            SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS => 'Exhibitions',
                        ])
                        ->required(fn (callable $get): bool => $get('type') === SiteSection::TYPE_JOURNAL)
                        ->visible(fn (callable $get): bool => $get('type') === SiteSection::TYPE_JOURNAL),
                    TextInput::make('title')->label('Title')->required()->maxLength(160),
                    TextInput::make('slug')
                        ->label('Public URL slug')
                        ->maxLength(80)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->required(fn (callable $get): bool => $get('type') !== SiteSection::TYPE_NAVIGATION_GROUP)
                        ->visible(fn (callable $get): bool => $get('type') !== SiteSection::TYPE_NAVIGATION_GROUP)
                        ->helperText('Use lowercase letters, numbers and hyphens. Navigation Nodes have no public URL.'),
                ])
                ->action(function (array $data): void {
                    $type = (string) ($data['type'] ?? '');
                    $title = trim((string) ($data['title'] ?? ''));
                    $slug = trim((string) ($data['slug'] ?? ''));

                    $message = match ($type) {
                        SiteSection::TYPE_NAVIGATION_GROUP => $this->createNavigationNode($title),
                        SiteSection::TYPE_CUSTOM => $this->createCustomPage($title, $slug),
                        SiteSection::TYPE_JOURNAL => $this->createJournal($title, $slug, (string) ($data['template'] ?? '')),
                        SiteSection::TYPE_GALLERY => $this->createGallery($title, $slug),
                        default => throw ValidationException::withMessages(['type' => 'Choose Gallery, Journal, Custom Page or Navigation Node.']),
                    };

                    $this->loadSections();
                    Notification::make()->title($message)->success()->send();
                }),
        ];
    }

    private function createNavigationNode(string $title): string
    {
        app(SiteSectionEditorialService::class)->createNavigationGroup($title);

        return 'Navigation Node created';
    }

    private function createCustomPage(string $title, string $slug): string
    {
        app(SiteSectionEditorialService::class)->createCustomPage($title, $slug);

        return 'Custom Page created as hidden';
    }

    private function createJournal(string $title, string $slug, string $template): string
    {
        app(SiteSectionEditorialService::class)->createJournal($title, $slug, $template);

        return 'Journal created as hidden';
    }

    private function createGallery(string $title, string $slug): string
    {
        app(ArtworkCategoryEditorialService::class)->create([
            'name' => $title,
            'slug' => $slug,
            'parent_section_id' => null,
            'description' => null,
            'show_on_home' => false,
        ]);

        return 'Gallery created as hidden';
    }

    private function loadSections(): void
    {
        /** @var Builder<SiteSection> $topLevelQuery */
        $topLevelQuery = SiteSection::query()->whereNull('parent_id');

        /** @var EloquentCollection<int, SiteSection> $topLevel */
        $topLevel = $topLevelQuery
            ->with([
                'customPageSetting',
                'children' => static function (Relation $relation): void {
                    $query = $relation->getQuery();
                    $query->with('customPageSetting');
                    $query->orderBy('position');
                    $query->orderBy('id');
                },
            ])
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

        $rows = [];
        foreach ($topLevel as $section) {
            $row = $this->row($section, 0);
            /** @var EloquentCollection<int, SiteSection> $children */
            $children = $section->getRelation('children');
            $row['children'] = $children
                ->map(fn (SiteSection $child): array => $this->row($child, 1))
                ->values()
                ->all();
            $rows[] = $row;
        }

        $this->sections = $rows;
    }

    /** @return array<string, mixed> */
    private function row(SiteSection $section, int $depth): array
    {
        $type = (string) $section->getAttribute('type');
        $template = $section->getAttribute('template');

        return [
            'id' => (int) $section->getKey(),
            'type' => $type,
            'template' => $template,
            'type_label' => match ($type) {
                SiteSection::TYPE_HOME => 'Home',
                SiteSection::TYPE_GALLERY => 'Gallery',
                SiteSection::TYPE_NAVIGATION_GROUP => 'Navigation Node',
                SiteSection::TYPE_CUSTOM => 'Custom Page',
                SiteSection::TYPE_JOURNAL => $template === SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS ? 'Journal · Exhibitions' : 'Journal · Blog',
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
            'public_url' => $section->publicUrl(),
            'can_move_up' => app(SiteSectionOrderService::class)->canMove($section, 'up'),
            'can_move_down' => app(SiteSectionOrderService::class)->canMove($section, 'down'),
            'can_delete' => $type !== SiteSection::TYPE_HOME,
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
        return match ((string) $section->getAttribute('type')) {
            SiteSection::TYPE_GALLERY => is_numeric($section->getAttribute('artwork_category_id'))
                ? ArtworkCategoryResource::getUrl('edit', ['record' => (int) $section->getAttribute('artwork_category_id')])
                : null,
            SiteSection::TYPE_JOURNAL => JournalSettingResource::getSettingsUrl($section),
            default => null,
        };
    }

    private function contentUrl(SiteSection $section): ?string
    {
        $type = (string) $section->getAttribute('type');

        if ($type === SiteSection::TYPE_HOME) {
            return ArtworkResource::getUrl('index');
        }

        if ($type === SiteSection::TYPE_GALLERY) {
            $galleryId = $section->getAttribute('artwork_category_id');

            return is_numeric($galleryId)
                ? ArtworkResource::getUrl('gallery', ['gallery' => (int) $galleryId])
                : null;
        }

        if ($type === SiteSection::TYPE_CUSTOM) {
            $settings = $section->relationLoaded('customPageSetting')
                ? $section->getRelation('customPageSetting')
                : null;

            return $settings instanceof CustomPageSetting
                ? CustomPageSettingResource::getUrl('edit', ['record' => $settings])
                : null;
        }

        if ($type === SiteSection::TYPE_JOURNAL) {
            return $section->getAttribute('template') === SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS
                ? ExhibitionResource::getUrl('index', ['section' => $section->getKey()])
                : BlogPostResource::getUrl('index', ['section' => $section->getKey()]);
        }

        return null;
    }
}
