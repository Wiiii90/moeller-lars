<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SitePageHeading;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionIdentityService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\HierarchyParentField;
use App\Filament\Support\PageHeadingFields;
use App\Filament\Support\PageMetaDescriptionField;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait ManagesSitePageEditDialog
{
    /** @var array<int,array<string,mixed>> Server-owned originals for mounted optimistic edits. */
    #[Locked]
    public array $pageDialogOriginals = [];

    /** @var array<int, SiteSection> */
    private array $dialogSectionCache = [];

    public function editPageAction(): Action
    {
        $action = Action::make('editPage')
            ->label('Edit')
            ->modalHeading(__('Edit page'))
            ->fillForm(function (array $arguments): array {
                $section = $this->dialogSection($arguments);
                $template = $section->nodeType() === SiteSectionType::Gallery
                    ? $section->galleryTemplate()
                    : $section->journalTemplate();
                $this->pageDialogOriginals[(int) $section->getKey()] = $this->pageDialogPersistedState($section);

                return [
                    'type' => $section->nodeType()->value,
                    'template' => $template?->value,
                    'show_in_navigation' => (bool) $section->getAttribute('show_in_navigation'),
                    'public_site_gate' => (bool) $section->getAttribute('public_site_gate'),
                    'name' => (string) $section->getAttribute('title'),
                    'slug' => $section->isHome() ? '/' : $section->getAttribute('slug'),
                    'parent_id' => $section->getAttribute('parent_id'),
                    'position' => $section->isHome() ? 1 : $this->dialogDisplayPosition($section),
                    'meta_description' => $section->getAttribute('meta_description'),
                    ...SitePageHeading::formState($section),
                ];
            })
            ->schema(fn (array $arguments): array => [
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema([
                        Select::make('type')
                            ->label('Page type')
                            ->options(fn (): array => array_diff_key(
                                array_map('__', SiteSectionType::compactOptions()),
                                $this->dialogSection($arguments)->isHome()
                                    ? [SiteSectionType::NavigationNode->value => true] : [],
                            ))
                            ->native()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set) use ($arguments): void {
                                $set('template', match ($state) {
                                    SiteSectionType::Gallery->value => $this->dialogSection($arguments)->isHome()
                                        ? GalleryTemplate::HeroArtworks->value : GalleryTemplate::Showcase->value,
                                    SiteSectionType::Journal->value => JournalTemplate::Blog->value,
                                    default => null,
                                });
                            }),
                        Select::make('template')
                            ->label('Template')
                            ->options(fn (Get $get): array => match ($get('type')) {
                                SiteSectionType::Gallery->value => array_map('__', GalleryTemplate::options()),
                                SiteSectionType::Journal->value => array_map('__', JournalTemplate::options()),
                                default => [],
                            })
                            ->native()
                            ->required(fn (Get $get): bool => in_array($get('type'), [SiteSectionType::Gallery->value, SiteSectionType::Journal->value], true))
                            ->visible(fn (Get $get): bool => in_array($get('type'), [SiteSectionType::Gallery->value, SiteSectionType::Journal->value], true)),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(160),
                        TextInput::make('slug')
                            ->label('Public slug')
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->required(fn (Get $get): bool => ! $this->dialogSection($arguments)->isHome()
                                && (SiteSectionType::tryFrom((string) $get('type'))?->requiresSlug() ?? false))
                            ->visible(fn (Get $get): bool => $this->dialogSection($arguments)->isHome()
                                || (SiteSectionType::tryFrom((string) $get('type'))?->requiresSlug() ?? false))
                            ->disabled(fn (): bool => $this->dialogSection($arguments)->isHome())
                            ->dehydrated(fn (): bool => ! $this->dialogSection($arguments)->isHome()),
                        HierarchyParentField::make(
                            'parent_id', fn (): array => $this->parentOptions, 'Parent page',
                        )->visible(fn (): bool => $this->nestedNavigationEnabled && ! $this->dialogSection($arguments)->isHome()),
                        TextInput::make('home_parent_page')
                            ->label('Parent page')
                            ->default('Top level')
                            ->disabled()
                            ->dehydrated(false)
                            ->visible(fn (): bool => $this->dialogSection($arguments)->isHome()),
                        TextInput::make('position')
                            ->label('Position')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->required()
                            ->disabled(fn (): bool => $this->dialogSection($arguments)->isHome())
                            ->dehydrated(fn (): bool => ! $this->dialogSection($arguments)->isHome()),
                        PageMetaDescriptionField::make()
                            ->visible(fn (callable $get): bool => $get('type') !== SiteSectionType::NavigationNode->value),
                    ]),
                Grid::make()->columns(['default' => 1, 'lg' => 2])
                    ->visible(fn (): bool => $this->dialogSection($arguments)->isHome())
                    ->schema([
                        AdminControl::toggle('show_in_navigation', 'Show in public navigation')
                            ->live()
                            ->afterStateUpdated(function (mixed $state) use ($arguments): void {
                                $section = $this->dialogSection($arguments);
                                app(SiteSectionEditorialService::class)->updateHomeNavigationVisibility($section, (bool) $state);
                                $this->pageDialogOriginals[(int) $section->getKey()]['show_in_navigation'] = (bool) $state;
                                $this->loadSections(navigationChanged: true);
                            }),
                        AdminControl::toggle('public_site_gate', 'Temporarily gate the public site')
                            ->visible(fn (): bool => $this->dialogSection($arguments)->nodeType() === SiteSectionType::CustomPage)
                            ->live()
                            ->afterStateUpdated(function (mixed $state) use ($arguments): void {
                                $section = $this->dialogSection($arguments);
                                app(SiteSectionEditorialService::class)->updateHomePublicSiteGate($section, (bool) $state);
                                $this->pageDialogOriginals[(int) $section->getKey()]['public_site_gate'] = (bool) $state;
                                $this->loadSections();
                            }),
                    ]),
                PageHeadingFields::schema(function (string $field, mixed $value) use ($arguments): bool {
                    $section = $this->dialogSection($arguments);
                    $updated = app(SiteSectionEditorialService::class)->updatePageHeading($section, [$field => $value]);
                    $this->dialogSectionCache = [];
                    $this->pageDialogOriginals[(int) $section->getKey()][$field] = $updated->getAttribute($field);
                    $this->loadSections();

                    return $updated->wasChanged($field);
                }, 'Page updated')
                    ->visible(fn (): bool => $this->dialogSection($arguments)->hasPublicPage()),
            ])
            ->action(function (array $data, array $arguments): void {
                try {
                    $changed = DB::transaction(function () use ($data, $arguments): bool {
                        return $this->savePageFromDialog($this->dialogSection($arguments), $data);
                    });
                } catch (ValidationException $exception) {
                    $this->throwPageDialogValidation($exception);
                }

                $this->loadSections(navigationChanged: $changed);
                if ($changed) {
                    // The editor stays mounted, so Filament otherwise renders only
                    // its modal. The changed page projection must render as well.
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(
                        title: 'Page updated',
                        status: 'success',
                    );
                }
            })
            ->extraModalFooterActions(fn (array $arguments): array => $this->pageDialogHeaderActions($arguments));

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /** @param array<string, mixed> $data */
    private function savePageFromDialog(SiteSection $section, array $data): bool
    {
        // A mounted Edit Action persists successive changes without remounting.
        // Always compare against the latest database row, not its cached initial
        // fill state, or later no-ops look like successful edits.
        $section = SiteSection::query()->whereKey($section->getKey())->lockForUpdate()->firstOrFail();
        $before = $this->pageDialogPersistedState($section);
        if (($this->pageDialogOriginals[(int) $section->getKey()] ?? null) !== $before) {
            throw ValidationException::withMessages(['name' => 'This page changed since the editor was opened. Reopen the editor.']);
        }

        $targetType = SiteSectionType::tryFrom((string) ($data['type'] ?? ''));
        if ($targetType === null || ($section->isHome() && $targetType === SiteSectionType::NavigationNode)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported page type. Home must display content.']);
        }

        $editorial = app(SiteSectionEditorialService::class);
        if ($section->nodeType() !== $targetType) {
            $section = $editorial->convertType($section, $targetType->value);
        }

        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($section->getKey());
        $name = trim((string) ($data['name'] ?? ''));
        $slug = isset($data['slug']) ? trim((string) $data['slug']) : null;
        $section = $this->updateDialogIdentity($section, $name, $slug);

        if ($section->nodeType() === SiteSectionType::Gallery) {
            $section = $editorial->updateGalleryTemplate(
                $section,
                (string) ($data['template'] ?? GalleryTemplate::Showcase->value),
            );
        }

        if ($section->nodeType() === SiteSectionType::Journal) {
            $section = $editorial->updateJournalTemplate(
                $section,
                (string) ($data['template'] ?? JournalTemplate::Blog->value),
            );
        }

        if (! $section->isHome()) {
            $parentId = $this->dialogParentId($data['parent_id'] ?? null);
            $section = $editorial->updatePlacement(
                $section,
                (string) $section->getAttribute('state'),
                $parentId,
            );

            $position = $this->dialogPosition($data['position'] ?? null);
            if ($position !== null) {
                if ($parentId === null) {
                    $position = max(2, $position);
                }
                $this->orderService()->moveTo($section, $parentId, $position - 1);
            }
        }

        // Group Nodes have no public document or Meta Description. The field is
        // hidden in their Edit form and must not trigger a page-metadata write.
        if ($section->hasPublicPage()) {
            $section = $editorial->updateMetaDescription($section, $data['meta_description'] ?? null);
        }

        /** @var SiteSection $fresh */
        $fresh = SiteSection::query()->findOrFail($section->getKey());
        $this->dialogSectionCache[(int) $fresh->getKey()] = $fresh;
        $this->pageDialogOriginals[(int) $fresh->getKey()] = $this->pageDialogPersistedState($fresh);

        return $before !== $this->pageDialogPersistedState($fresh);
    }

    /**
     * @return array<string, mixed>
     */
    private function pageDialogPersistedState(SiteSection $section): array
    {
        $template = $section->getAttribute('template');
        $navigationLabel = $section->getAttribute('navigation_label');
        $slug = $section->getAttribute('slug');
        $parentId = $section->getAttribute('parent_id');

        return [
            'type' => (string) $section->getAttribute('type'),
            'template' => $template === null ? null : (string) $template,
            'title' => (string) $section->getAttribute('title'),
            'navigation_label' => $navigationLabel === null ? null : (string) $navigationLabel,
            'slug' => $slug === null ? null : (string) $slug,
            'parent_id' => $parentId === null ? null : (int) $parentId,
            'position' => (int) $section->getAttribute('position'),
            'meta_description' => $section->getAttribute('meta_description'),
            'show_in_navigation' => $section->isHome() ? (bool) $section->getAttribute('show_in_navigation') : null,
            'public_site_gate' => $section->isHome() ? (bool) $section->getAttribute('public_site_gate') : null,
            ...SitePageHeading::formState($section),
        ];
    }

    private function updateDialogIdentity(SiteSection $section, string $name, ?string $slug): SiteSection
    {
        if ($section->nodeType() !== SiteSectionType::Gallery
            || $section->isHome()
            || $section->getAttribute('artwork_category_id') === null) {
            // Keep independently authored navigation labels intact. Labels
            // following the old title continue following a renamed page.
            $oldTitle = (string) $section->getAttribute('title');
            $label = $section->getAttribute('navigation_label');
            $customLabel = is_string($label) && trim($label) !== ''
                && trim($label) !== trim($oldTitle) ? $label : null;

            return app(SiteSectionIdentityService::class)->update($section, $name, $slug, $customLabel);
        }

        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $section->getAttribute('artwork_category_id'));
        $galleryService = app(GalleryEditorialService::class);
        $gallery = $galleryService->update($gallery, [
            'name' => $name,
        ]);

        if (trim((string) $gallery->getAttribute('slug')) !== trim((string) $slug)) {
            $galleryService->changeSlug($gallery, (string) $slug);
        }

        /** @var SiteSection $fresh */
        $fresh = SiteSection::query()->findOrFail($section->getKey());

        return $fresh;
    }

    /** @param array<string, mixed> $arguments
     * @return list<Action>
     */
    private function pageDialogHeaderActions(array $arguments): array
    {
        $section = $this->dialogSection($arguments);
        $id = (int) $section->getKey();
        $row = collect($this->filteredRows)->firstWhere('id', $id);
        $actions = [];

        // Use the same capabilities/order as the visible Pages row. Movement
        // reopens the editor for the stable section ID to refresh Position.
        if (($row['can_reorder'] ?? false) === true) {
            foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
                $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($id, $direction): void {
                    $this->moveSection($id, $direction);
                    $this->retargetPageEditor($id);
                })->disabled(! $this->reorderEnabled || ! ($row['can_move_'.$direction] ?? false));
            }
        }

        if (! $section->isHome() && $section->nodeType()->canChangePublication()) {
            $published = (string) $section->getAttribute('state') === 'published';
            $actions[] = AdminDialog::headerAction(
                'toggleDialogPublication',
                $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
                function () use ($id): void {
                    $this->toggleSectionState($id);
                    $this->retargetPageEditor($id);
                },
            );
        }

        if (($row['can_delete'] ?? false) === true) {
            $actions[] = AdminDialog::headerAction('dialogDeletePage', AdminRowAction::Delete, function () use ($id): void {
                $this->replaceMountedAction('deletePage', ['section' => $id]);
            });
        }

        return $actions;
    }

    /** @param array<string, mixed> $arguments */
    private function dialogSection(array $arguments): SiteSection
    {
        $sectionId = (int) ($arguments['section'] ?? 0);
        if (isset($this->dialogSectionCache[$sectionId])) {
            return $this->dialogSectionCache[$sectionId];
        }

        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($sectionId);

        return $this->dialogSectionCache[$sectionId] = $section;
    }

    private function dialogParentId(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
            throw ValidationException::withMessages(['parent_id' => 'Choose a valid top-level parent page.']);
        }

        $parentId = (int) $value;
        if (! SiteSection::query()->whereKey($parentId)->whereNull('parent_id')->exists()) {
            throw ValidationException::withMessages(['parent_id' => 'The parent must be a top-level page.']);
        }

        return $parentId;
    }

    private function dialogPosition(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value < 1) {
            throw ValidationException::withMessages(['position' => 'Position must be a positive number.']);
        }

        return (int) $value;
    }

    private function retargetPageEditor(int $sectionId): void
    {
        $this->dialogSectionCache = [];
        $this->cacheAction($this->editPageAction());
        $this->replaceMountedAction('editPage', ['section' => $sectionId]);
    }

    private function dialogDisplayPosition(SiteSection $section): int
    {
        $parentId = $section->getAttribute('parent_id');
        $siblings = SiteSection::query()
            ->when(
                $parentId === null,
                static fn ($query) => $query->whereNull('parent_id'),
                static fn ($query) => $query->where('parent_id', $parentId),
            )
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->values();

        $index = $siblings->search((int) $section->getKey());

        return $index === false ? 1 : $index + 1;
    }
}
