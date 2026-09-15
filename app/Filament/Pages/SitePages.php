<?php

namespace App\Filament\Pages;

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionOrderService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\Concerns\ManagesSitePageDialogs;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\SiteNodePresentation;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class SitePages extends Page
{
    use ManagesSitePageDialogs;

    protected static string|BackedEnum|null $navigationIcon = AdminIcon::Pages;

    protected static string|UnitEnum|null $navigationGroup = 'Website';

    protected static ?string $navigationLabel = 'Pages';

    protected static ?string $title = 'Pages';

    protected static ?string $slug = 'pages';

    protected static ?int $navigationSort = 5;

    protected string $view = 'filament.pages.site-pages';

    /** @var list<array<string, mixed>> */
    public array $sections = [];

    /** @var list<array<string, mixed>> */
    public array $filteredRows = [];

    /** @var array{total:int,published:int,unpublished:int,top_level:int,children:int,navigation:int} */
    public array $metrics = [
        'total' => 0,
        'published' => 0,
        'unpublished' => 0,
        'top_level' => 0,
        'children' => 0,
        'navigation' => 0,
    ];

    /** @var list<int|string> */
    public array $selectedSectionIds = [];

    /** @var array<int, string> */
    public array $parentOptions = [];

    public string $search = '';

    public string $typeFilter = '';

    public string $statusFilter = '';

    public bool $filtersActive = false;

    public bool $reorderEnabled = true;

    public bool $allVisibleSelected = false;

    public bool $selectionIndeterminate = false;

    public bool $addingPage = false;

    public string $newPageType = 'custom';

    public string $newPageTitle = '';

    public string $newPageSlug = '';

    public string $newJournalTemplate = 'blog';

    public string $newPageParent = '';

    public int $perPage = 25;

    public int $pageNumber = 1;

    public int $totalGroups = 0;

    public int $lastPage = 1;

    public int $rangeStart = 0;

    public int $rangeEnd = 0;

    private ?SiteSectionOrderService $orderService = null;

    public function mount(): void
    {
        $this->loadSections();
    }

    public function updatedSearch(): void
    {
        $this->pageNumber = 1;
        $this->loadSections();
    }

    public function updatedTypeFilter(): void
    {
        $this->pageNumber = 1;
        $this->loadSections();
    }

    public function updatedStatusFilter(): void
    {
        $this->pageNumber = 1;
        $this->loadSections();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->pageNumber = 1;
        $this->loadSections();
    }

    public function updatedSelectedSectionIds(): void
    {
        $this->syncSelectionState();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->typeFilter = '';
        $this->statusFilter = '';
        $this->pageNumber = 1;
        $this->loadSections();
    }

    public function previousPage(): void
    {
        if ($this->pageNumber <= 1) {
            return;
        }

        $this->pageNumber--;
        $this->loadSections();
    }

    public function nextPage(): void
    {
        if ($this->pageNumber >= $this->lastPage) {
            return;
        }

        $this->pageNumber++;
        $this->loadSections();
    }

    public function toggleSelectAll(): void
    {
        $visibleIds = array_map(static fn (array $row): int => (int) $row['id'], $this->filteredRows);
        $selected = $this->selectedIds();
        $allVisibleSelected = $visibleIds !== [] && array_diff($visibleIds, $selected) === [];

        if ($allVisibleSelected) {
            $selected = array_values(array_diff($selected, $visibleIds));
        } else {
            $selected = array_values(array_unique([...$selected, ...$visibleIds]));
        }

        $this->selectedSectionIds = $selected;
        $this->syncSelectionState();
    }

    public function sortSection(int $sectionId, int $position, int|string|null $groupId = null): void
    {
        if (! $this->reorderEnabled) {
            Notification::make()
                ->title('Reordering is unavailable for this view')
                ->body($this->filtersActive
                    ? 'Reset Search, Type and Status before changing page order.'
                    : 'Show the complete canonical root-page set before changing page order.')
                ->warning()
                ->send();
            $this->loadSections();

            return;
        }

        $parentId = null;
        if ($groupId !== null && $groupId !== '' && $groupId !== 'root') {
            if (! ctype_digit((string) $groupId)) {
                Notification::make()->title('Page order unchanged')->danger()->send();
                $this->loadSections();

                return;
            }
            $parentId = (int) $groupId;
        }

        try {
            /** @var SiteSection $section */
            $section = SiteSection::query()->findOrFail($sectionId);
            if ($this->orderService()->moveTo($section, $parentId, $position)) {
                Notification::make()->title('Page order updated')->success()->send();
            }
        } catch (ValidationException $exception) {
            $this->validationNotification('Page order unchanged', $exception);
        }

        $this->loadSections();
    }

    public function moveSection(int $sectionId, string $direction): void
    {
        if (! $this->reorderEnabled) {
            Notification::make()->title('Show the complete unfiltered page order before reordering')->warning()->send();

            return;
        }

        try {
            /** @var SiteSection $section */
            $section = SiteSection::query()->findOrFail($sectionId);
            if ($this->orderService()->move($section, $direction)) {
                Notification::make()->title('Page order updated')->success()->send();
                $this->loadSections();
            }
        } catch (ValidationException $exception) {
            $this->validationNotification('Page order unchanged', $exception);
            $this->loadSections();
        }
    }

    public function editPlacementAction(): Action
    {
        return AdminDialog::edit(
            Action::make('editPlacement')
                ->label('Edit')
                ->modalHeading('Edit page placement')
                ->modalDescription('Choose whether this page is top level or belongs under another top-level page.')
                ->fillForm(function (array $arguments): array {
                    /** @var SiteSection $section */
                    $section = SiteSection::query()->findOrFail((int) ($arguments['section'] ?? 0));

                    return [
                        'parent_id' => $section->getAttribute('parent_id'),
                    ];
                })
                ->schema([
                    Select::make('parent_id')
                        ->label('Parent page')
                        ->options(fn (): array => $this->parentOptions)
                        ->placeholder('Top level')
                        ->native()
                        ->nullable(),
                ])
                ->action(function (array $data, array $arguments): void {
                    /** @var SiteSection $section */
                    $section = SiteSection::query()->findOrFail((int) ($arguments['section'] ?? 0));
                    if ($section->nodeType() === SiteSectionType::Home) {
                        return;
                    }

                    $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null
                        ? (int) $data['parent_id']
                        : null;

                    try {
                        app(SiteSectionEditorialService::class)->updatePlacement(
                            $section,
                            (string) $section->getAttribute('state'),
                            (bool) $section->getAttribute('show_in_navigation'),
                            $parentId,
                        );
                        Notification::make()->title('Page placement updated')->success()->send();
                    } catch (ValidationException $exception) {
                        $this->validationNotification('Page placement unchanged', $exception);
                    }

                    $this->loadSections();
                }),
            AdminDialogSize::Default,
        );
    }

    public function toggleSectionState(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if (! $section->nodeType()->canChangePublication()) {
            return;
        }

        $state = (string) $section->getAttribute('state') === 'published' ? 'hidden' : 'published';
        $this->updatePlacement($section, $state, (bool) $section->getAttribute('show_in_navigation'));
    }

    public function toggleSectionNavigation(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        $this->updatePlacement(
            $section,
            (string) $section->getAttribute('state'),
            ! (bool) $section->getAttribute('show_in_navigation'),
        );
    }

    public function deleteSection(int $sectionId): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);
        if (! $section->nodeType()->canDelete()) {
            return;
        }

        try {
            $this->deleteSectionRecord($section);
            Notification::make()->title('Page removed')->success()->send();
        } catch (ValidationException $exception) {
            $this->validationNotification('Page was not removed', $exception);
        }

        $this->loadSections();
    }

    public function bulkPublish(): void
    {
        $this->bulkChangeState('published');
    }

    public function bulkUnpublish(): void
    {
        $this->bulkChangeState('hidden');
    }

    public function bulkDelete(): void
    {
        $sections = $this->selectedSections()
            ->sortByDesc(static fn (SiteSection $section): int => $section->getAttribute('parent_id') === null ? 0 : 1)
            ->values();

        $deleted = 0;
        $blocked = 0;
        foreach ($sections as $section) {
            if (! $section->nodeType()->canDelete()) {
                $blocked++;

                continue;
            }

            try {
                $this->deleteSectionRecord($section);
                $deleted++;
            } catch (ValidationException) {
                $blocked++;
            }
        }

        $this->loadSections();
        $this->bulkNotification('Delete', $deleted, $blocked);
    }

    public function convertSectionType(int $sectionId, string $targetType): void
    {
        try {
            /** @var SiteSection $section */
            $section = SiteSection::query()->findOrFail($sectionId);
            app(SiteSectionEditorialService::class)->convertType($section, $targetType);
            Notification::make()->title('Page type updated')->success()->send();
        } catch (ValidationException $exception) {
            $this->validationNotification('Page type unchanged', $exception);
        }

        $this->loadSections();
    }

    public function changeJournalTemplate(int $sectionId, string $template): void
    {
        try {
            /** @var SiteSection $section */
            $section = SiteSection::query()->findOrFail($sectionId);
            app(SiteSectionEditorialService::class)->updateJournalTemplate($section, $template);
            Notification::make()->title('Journal template updated')->success()->send();
        } catch (ValidationException $exception) {
            $this->validationNotification('Journal template unchanged', $exception);
        }

        $this->loadSections();
    }

    public function startAddingPage(): void
    {
        $this->addingPage = true;
        $this->newPageType = SiteSectionType::CustomPage->value;
        $this->newJournalTemplate = JournalTemplate::Blog->value;
        $this->newPageParent = '';
    }

    public function cancelAddingPage(): void
    {
        $this->addingPage = false;
        $this->newPageTitle = '';
        $this->newPageSlug = '';
        $this->newPageParent = '';
    }

    public function createPage(): void
    {
        $type = SiteSectionType::tryFrom($this->newPageType);

        try {
            $parentId = $this->newParentId();
            $message = match ($type) {
                SiteSectionType::NavigationNode => $this->createNavigationGroup($parentId),
                SiteSectionType::CustomPage => $this->createCustomPage($parentId),
                SiteSectionType::Journal => $this->createJournal($parentId),
                SiteSectionType::Gallery => $this->createGallery($parentId),
                default => throw ValidationException::withMessages(['type' => 'Choose Gallery, Journal, Custom Page or Group Node.']),
            };

            $this->addingPage = false;
            $this->newPageTitle = '';
            $this->newPageSlug = '';
            $this->newPageType = SiteSectionType::CustomPage->value;
            $this->newJournalTemplate = JournalTemplate::Blog->value;
            $this->newPageParent = '';
            $this->pageNumber = 1;
            $this->loadSections();
            Notification::make()->title($message)->success()->send();
        } catch (ValidationException $exception) {
            $this->validationNotification('Page was not added', $exception);
        }
    }

    private function createNavigationGroup(?int $parentId): string
    {
        $section = app(SiteSectionEditorialService::class)->createNavigationGroup($this->newPageTitle);
        $this->placeCreatedSection($section, $parentId);

        return 'Group Node added';
    }

    private function createCustomPage(?int $parentId): string
    {
        $section = app(SiteSectionEditorialService::class)->createCustomPage($this->newPageTitle, $this->newPageSlug);
        $this->placeCreatedSection($section, $parentId);

        return 'Custom Page added as unpublished';
    }

    private function createJournal(?int $parentId): string
    {
        $section = app(SiteSectionEditorialService::class)->createJournal(
            $this->newPageTitle,
            $this->newPageSlug,
            $this->newJournalTemplate,
        );
        $this->placeCreatedSection($section, $parentId);

        return 'Journal added as unpublished';
    }

    private function createGallery(?int $parentId): string
    {
        app(GalleryEditorialService::class)->create([
            'name' => $this->newPageTitle,
            'slug' => $this->newPageSlug,
            'parent_section_id' => $parentId,
            'description' => null,
            'show_on_home' => false,
        ]);

        return 'Gallery added as unpublished';
    }

    private function placeCreatedSection(SiteSection $section, ?int $parentId): void
    {
        if ($parentId !== null) {
            $this->orderService()->moveTo($section, $parentId, PHP_INT_MAX);
        }
    }

    private function newParentId(): ?int
    {
        if ($this->newPageParent === '') {
            return null;
        }
        if (! ctype_digit($this->newPageParent)) {
            throw ValidationException::withMessages(['parent_id' => 'Choose a valid top-level parent page.']);
        }

        $parentId = (int) $this->newPageParent;
        if (! SiteSection::query()->whereKey($parentId)->whereNull('parent_id')->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'The parent must be a top-level page.']);
        }

        return $parentId;
    }

    private function loadSections(): void
    {
        $this->orderService = app(SiteSectionOrderService::class);

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

        $allGroups = [];
        $allRows = [];
        $topCount = $topLevel->count();
        foreach ($topLevel->values() as $topIndex => $section) {
            /** @var EloquentCollection<int, SiteSection> $children */
            $children = $section->getRelation('children');
            $label = $this->sectionLabel($section);
            $isHome = $section->nodeType() === SiteSectionType::Home;
            $previous = $topIndex > 0 ? $topLevel->values()->get($topIndex - 1) : null;
            $previousIsHome = $previous instanceof SiteSection && $previous->nodeType() === SiteSectionType::Home;
            $row = $this->row(
                $section,
                0,
                (string) ($topIndex + 1),
                null,
                ! $isHome && $topIndex > 0 && ! $previousIsHome,
                ! $isHome && $topIndex < $topCount - 1,
                $children->isNotEmpty(),
            );

            $childRows = [];
            $childCount = $children->count();
            foreach ($children->values() as $childIndex => $child) {
                $childRows[] = $this->row(
                    $child,
                    1,
                    ($topIndex + 1).'.'.($childIndex + 1),
                    $label,
                    $childIndex > 0,
                    $childIndex < $childCount - 1,
                    false,
                );
            }

            $row['children'] = $childRows;
            $allGroups[] = $row;
            $allRows[] = $row;
            foreach ($childRows as $childRow) {
                $allRows[] = $childRow;
            }
        }

        $this->parentOptions = [];
        foreach ($allGroups as $group) {
            $this->parentOptions[(int) $group['id']] = (string) ($group['navigation_label'] ?: $group['title']);
        }

        $this->metrics = [
            'total' => count($allRows),
            'published' => count(array_filter($allRows, static fn (array $row): bool => $row['state'] === 'published')),
            'unpublished' => count(array_filter($allRows, static fn (array $row): bool => $row['state'] !== 'published')),
            'top_level' => count($allGroups),
            'children' => count(array_filter($allRows, static fn (array $row): bool => $row['parent_id'] !== null)),
            'navigation' => count(array_filter($allRows, static fn (array $row): bool => $row['visible'] === true)),
        ];

        $this->filtersActive = trim($this->search) !== '' || $this->typeFilter !== '' || $this->statusFilter !== '';
        $groups = $this->filtersActive ? $this->filteredGroups($allGroups) : $allGroups;

        $this->totalGroups = count($groups);
        $this->lastPage = max(1, (int) ceil($this->totalGroups / $this->perPage));
        $this->pageNumber = min(max(1, $this->pageNumber), $this->lastPage);

        $offset = ($this->pageNumber - 1) * $this->perPage;
        $this->sections = array_values(array_slice($groups, $offset, $this->perPage));
        $this->rangeStart = $this->totalGroups === 0 ? 0 : $offset + 1;
        $this->rangeEnd = $this->totalGroups === 0 ? 0 : min($offset + count($this->sections), $this->totalGroups);
        $this->filteredRows = $this->flattenGroups($this->sections);

        $visibleIds = array_map(static fn (array $row): int => (int) $row['id'], $this->filteredRows);
        $this->selectedSectionIds = array_values(array_intersect($this->selectedIds(), $visibleIds));
        $this->reorderEnabled = ! $this->filtersActive && $this->totalGroups <= $this->perPage;
        $this->syncSelectionState();
    }

    /** @param list<array<string, mixed>> $groups
     * @return list<array<string, mixed>>
     */
    private function filteredGroups(array $groups): array
    {
        $filtered = [];

        foreach ($groups as $group) {
            $children = $group['children'];
            $rootMatches = $this->matchesFilters($group);
            $matchingChildren = array_values(array_filter(
                $children,
                fn (array $child): bool => $this->matchesFilters($child),
            ));

            if (! $rootMatches && $matchingChildren === []) {
                continue;
            }

            $group['children'] = $matchingChildren;
            $group['filter_context'] = ! $rootMatches;
            $filtered[] = $group;
        }

        return $filtered;
    }

    /** @param list<array<string, mixed>> $groups
     * @return list<array<string, mixed>>
     */
    private function flattenGroups(array $groups): array
    {
        $rows = [];
        foreach ($groups as $group) {
            $children = $group['children'];
            $root = $group;
            unset($root['children']);
            $rows[] = $root;
            foreach ($children as $child) {
                $rows[] = $child;
            }
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function row(
        SiteSection $section,
        int $depth,
        string $positionLabel,
        ?string $parentLabel,
        bool $canMoveUp,
        bool $canMoveDown,
        bool $hasChildren,
    ): array {
        $type = $section->nodeType();
        $journalTemplate = $section->journalTemplate();
        $navigationLabel = $section->getAttribute('navigation_label');

        return [
            'id' => (int) $section->getKey(),
            'type' => $type->value,
            'template' => $journalTemplate?->value,
            'type_label' => $type->label(),
            'title' => (string) $section->getAttribute('title'),
            'navigation_label' => $navigationLabel,
            'slug' => $section->getAttribute('slug'),
            'state' => (string) $section->getAttribute('state'),
            'visible' => (bool) $section->getAttribute('show_in_navigation'),
            'position' => (int) $section->getAttribute('position'),
            'position_label' => $positionLabel,
            'parent_id' => $section->getAttribute('parent_id'),
            'parent_label' => $parentLabel,
            'has_children' => $hasChildren,
            'depth' => $depth,
            'can_reorder' => $type !== SiteSectionType::Home,
            'can_move_up' => $canMoveUp,
            'can_move_down' => $canMoveDown,
            'can_delete' => $type->canDelete(),
            'can_change_publication' => $type->canChangePublication(),
            'can_convert' => $type->canConvert(),
            'filter_context' => false,
            'workspace_url' => app(SiteNodePresentation::class)->workspaceUrl($section),
        ];
    }

    private function matchesFilters(array $row): bool
    {
        if ($this->typeFilter !== '' && $row['type'] !== $this->typeFilter) {
            return false;
        }
        if ($this->statusFilter !== '' && $row['state'] !== $this->statusFilter) {
            return false;
        }

        $needle = mb_strtolower(trim($this->search));
        if ($needle === '') {
            return true;
        }

        $templateLabel = JournalTemplate::tryFrom((string) ($row['template'] ?? ''))?->label() ?? '';
        $haystack = mb_strtolower(implode(' ', array_filter([
            $row['title'],
            $row['navigation_label'],
            $row['slug'],
            $row['type_label'],
            $templateLabel,
            $row['parent_label'],
        ], static fn (mixed $value): bool => is_string($value) && $value !== '')));

        return str_contains($haystack, $needle);
    }

    private function sectionLabel(SiteSection $section): string
    {
        $navigationLabel = $section->getAttribute('navigation_label');

        return is_string($navigationLabel) && trim($navigationLabel) !== ''
            ? trim($navigationLabel)
            : (string) $section->getAttribute('title');
    }

    private function orderService(): SiteSectionOrderService
    {
        return $this->orderService ??= app(SiteSectionOrderService::class);
    }

    private function updatePlacement(SiteSection $section, string $state, bool $visible): void
    {
        try {
            app(SiteSectionEditorialService::class)->updatePlacement(
                $section,
                $state,
                $visible,
                $section->getAttribute('parent_id') === null ? null : (int) $section->getAttribute('parent_id'),
            );
            Notification::make()->title('Page settings updated')->success()->send();
        } catch (ValidationException $exception) {
            $this->validationNotification('Page settings unchanged', $exception);
        }

        $this->loadSections();
    }

    private function bulkChangeState(string $state): void
    {
        $sections = $this->selectedSections();
        $sections = $state === 'published'
            ? $sections->sortBy(static fn (SiteSection $section): int => $section->getAttribute('parent_id') === null ? 0 : 1)->values()
            : $sections->sortByDesc(static fn (SiteSection $section): int => $section->getAttribute('parent_id') === null ? 0 : 1)->values();

        $changed = 0;
        $blocked = 0;
        foreach ($sections as $section) {
            if ($state === 'hidden' && ! $section->nodeType()->canChangePublication()) {
                $blocked++;

                continue;
            }
            if ((string) $section->getAttribute('state') === $state) {
                continue;
            }

            try {
                app(SiteSectionEditorialService::class)->updatePlacement(
                    $section,
                    $state,
                    (bool) $section->getAttribute('show_in_navigation'),
                    $section->getAttribute('parent_id') === null ? null : (int) $section->getAttribute('parent_id'),
                );
                $changed++;
            } catch (ValidationException) {
                $blocked++;
            }
        }

        $this->loadSections();
        $this->bulkNotification($state === 'published' ? 'Publish' : 'Unpublish', $changed, $blocked);
    }

    private function deleteSectionRecord(SiteSection $section): void
    {
        if ($section->nodeType() === SiteSectionType::Gallery) {
            /** @var ArtworkCategory $gallery */
            $gallery = ArtworkCategory::query()->findOrFail((int) $section->getAttribute('artwork_category_id'));
            app(GalleryEditorialService::class)->delete($gallery);

            return;
        }

        app(SiteSectionEditorialService::class)->deleteConfigurableSection($section);
    }

    /** @return EloquentCollection<int, SiteSection> */
    private function selectedSections(): EloquentCollection
    {
        $ids = $this->selectedIds();
        if ($ids === []) {
            return new EloquentCollection;
        }

        /** @var EloquentCollection<int, SiteSection> $sections */
        $sections = SiteSection::query()->whereKey($ids)->get();

        return $sections;
    }

    /** @return list<int> */
    private function selectedIds(): array
    {
        return array_values(array_unique(array_filter(
            array_map(static fn (mixed $id): int => (int) $id, $this->selectedSectionIds),
            static fn (int $id): bool => $id > 0,
        )));
    }

    private function syncSelectionState(): void
    {
        $selected = $this->selectedIds();
        $visible = array_map(static fn (array $row): int => (int) $row['id'], $this->filteredRows);
        $visibleSelected = array_intersect($visible, $selected);

        $this->allVisibleSelected = $visible !== [] && count($visibleSelected) === count($visible);
        $this->selectionIndeterminate = $visibleSelected !== [] && ! $this->allVisibleSelected;
    }

    private function validationNotification(string $title, ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first();
        Notification::make()
            ->title($title)
            ->body(is_string($message) ? $message : 'The requested change is not safe.')
            ->danger()
            ->send();
    }

    private function bulkNotification(string $action, int $changed, int $blocked): void
    {
        $notification = Notification::make()->title($action.' selection complete');
        if ($blocked > 0) {
            $notification->body($changed.' changed · '.$blocked.' blocked by page safety rules')->warning()->send();

            return;
        }

        $notification->body($changed.' changed')->success()->send();
    }
}
