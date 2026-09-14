<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionIdentityService;
use App\Filament\Support\AdminIcon;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ManagesSitePageDialogs
{
    public function addPageAction(): Action
    {
        return Action::make('addPage')
            ->label('Add page')
            ->modalHeading('Add page')
            ->schema([
                Select::make('type')
                    ->label('Page type')
                    ->options(SiteNodeType::compactOptions())
                    ->default(SiteNodeType::CustomPage->value)
                    ->native()
                    ->required()
                    ->live(),
                Select::make('template')
                    ->label('Template')
                    ->options(JournalTemplate::options())
                    ->default(JournalTemplate::Blog->value)
                    ->native()
                    ->required(fn (callable $get): bool => $get('type') === SiteNodeType::Journal->value)
                    ->visible(fn (callable $get): bool => $get('type') === SiteNodeType::Journal->value),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(160),
                TextInput::make('slug')
                    ->label('Public slug')
                    ->maxLength(80)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->required(fn (callable $get): bool => SiteNodeType::tryFrom((string) $get('type'))?->requiresSlug() ?? false)
                    ->visible(fn (callable $get): bool => SiteNodeType::tryFrom((string) $get('type'))?->requiresSlug() ?? false),
                Select::make('parent_id')
                    ->label('Parent page')
                    ->options(fn (): array => $this->parentOptions)
                    ->placeholder('Top level')
                    ->native()
                    ->nullable(),
                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->nullable()
                    ->helperText('Leave empty to place the page last on the selected level.'),
            ])
            ->action(function (array $data): void {
                $section = DB::transaction(fn (): SiteSection => $this->createPageFromDialog($data));
                $this->pageNumber = 1;
                $this->loadSections();

                Notification::make()
                    ->title($section->nodeType()->label().' added')
                    ->success()
                    ->send();
            })
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Create page')
                ->icon(AdminIcon::Commit->value)
                ->iconButton()
                ->extraAttributes(['class' => 'admin-dialog__header-action is-primary']))
            ->modalCancelAction(false)
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--small admin-dialog--header-actions',
            ]);
    }

    public function editPageAction(): Action
    {
        return Action::make('editPage')
            ->label('Edit')
            ->modalHeading('Edit page')
            ->fillForm(function (array $arguments): array {
                $section = $this->dialogSection($arguments);

                return [
                    'type' => $section->nodeType()->value,
                    'template' => $section->journalTemplate()?->value ?? JournalTemplate::Blog->value,
                    'name' => $this->sectionLabel($section),
                    'slug' => $section->getAttribute('slug'),
                    'parent_id' => $section->getAttribute('parent_id'),
                    'position' => $this->dialogDisplayPosition($section),
                    'show_in_navigation' => (bool) $section->getAttribute('show_in_navigation'),
                ];
            })
            ->schema([
                Select::make('type')
                    ->label('Page type')
                    ->options(SiteNodeType::compactOptions())
                    ->native()
                    ->required()
                    ->live(),
                Select::make('template')
                    ->label('Template')
                    ->options(JournalTemplate::options())
                    ->native()
                    ->required(fn (callable $get): bool => $get('type') === SiteNodeType::Journal->value)
                    ->visible(fn (callable $get): bool => $get('type') === SiteNodeType::Journal->value),
                TextInput::make('name')
                    ->label('Name')
                    ->required()
                    ->maxLength(160),
                TextInput::make('slug')
                    ->label('Public slug')
                    ->maxLength(80)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->required(fn (callable $get): bool => SiteNodeType::tryFrom((string) $get('type'))?->requiresSlug() ?? false)
                    ->visible(fn (callable $get): bool => SiteNodeType::tryFrom((string) $get('type'))?->requiresSlug() ?? false),
                Select::make('parent_id')
                    ->label('Parent page')
                    ->options(fn (): array => $this->parentOptions)
                    ->placeholder('Top level')
                    ->native()
                    ->nullable(),
                TextInput::make('position')
                    ->label('Position')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required()
                    ->helperText('Position within the selected level. Home remains fixed at position 1.'),
                Checkbox::make('show_in_navigation')
                    ->label('Show in navigation'),
            ])
            ->action(function (array $data, array $arguments): void {
                DB::transaction(function () use ($data, $arguments): void {
                    $this->savePageFromDialog($this->dialogSection($arguments), $data);
                });

                $this->loadSections();
                Notification::make()->title('Page updated')->success()->send();
            })
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save page')
                ->icon(AdminIcon::Commit->value)
                ->iconButton()
                ->extraAttributes(['class' => 'admin-dialog__header-action is-primary']))
            ->modalCancelAction(false)
            ->extraModalFooterActions(fn (array $arguments): array => $this->pageDialogHeaderActions($arguments))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--small admin-dialog--header-actions',
            ]);
    }

    /** @param array<string, mixed> $data */
    private function createPageFromDialog(array $data): SiteSection
    {
        $type = SiteNodeType::tryFrom((string) ($data['type'] ?? ''));
        if ($type === null || ! $type->isCreatable()) {
            throw ValidationException::withMessages(['type' => 'Choose a supported page type.']);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $parentId = $this->dialogParentId($data['parent_id'] ?? null);
        $position = $this->dialogPosition($data['position'] ?? null);
        $editorial = app(SiteSectionEditorialService::class);

        $section = match ($type) {
            SiteNodeType::NavigationNode => $editorial->createNavigationGroup($name),
            SiteNodeType::CustomPage => $editorial->createCustomPage($name, $slug),
            SiteNodeType::Journal => $editorial->createJournal(
                $name,
                $slug,
                (string) ($data['template'] ?? JournalTemplate::Blog->value),
            ),
            SiteNodeType::Gallery => $this->createGallerySection($name, $slug),
            default => throw ValidationException::withMessages(['type' => 'Choose a supported page type.']),
        };

        if ($parentId !== null || $position !== null) {
            $displayPosition = $position;
            if ($displayPosition !== null && $parentId === null) {
                $displayPosition = max(2, $displayPosition);
            }

            $this->orderService()->moveTo(
                $section,
                $parentId,
                $displayPosition === null ? PHP_INT_MAX : $displayPosition - 1,
            );
        }

        /** @var SiteSection $fresh */
        $fresh = SiteSection::query()->findOrFail($section->getKey());

        return $fresh;
    }

    private function createGallerySection(string $name, string $slug): SiteSection
    {
        $gallery = app(GalleryEditorialService::class)->create([
            'name' => $name,
            'slug' => $slug,
            'parent_section_id' => null,
            'description' => null,
            'show_on_home' => false,
        ]);

        /** @var SiteSection $section */
        $section = SiteSection::query()
            ->where('type', SiteNodeType::Gallery->value)
            ->where('artwork_category_id', $gallery->getKey())
            ->firstOrFail();

        return $section;
    }

    /** @param array<string, mixed> $data */
    private function savePageFromDialog(SiteSection $section, array $data): void
    {
        $targetType = SiteNodeType::tryFrom((string) ($data['type'] ?? ''));
        if ($targetType === null || $targetType === SiteNodeType::Home) {
            throw ValidationException::withMessages(['type' => 'Choose a supported editable page type.']);
        }

        $editorial = app(SiteSectionEditorialService::class);
        if ($section->nodeType() !== $targetType) {
            $section = $editorial->convertType($section, $targetType);
        }

        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($section->getKey());
        $name = trim((string) ($data['name'] ?? ''));
        $slug = isset($data['slug']) ? trim((string) $data['slug']) : null;
        $section = $this->updateDialogIdentity($section, $name, $slug);

        if ($section->nodeType() === SiteNodeType::Journal) {
            $section = $editorial->updateJournalTemplate(
                $section,
                (string) ($data['template'] ?? JournalTemplate::Blog->value),
            );
        }

        $parentId = $this->dialogParentId($data['parent_id'] ?? null);
        $section = $editorial->updatePlacement(
            $section,
            (string) $section->getAttribute('state'),
            (bool) ($data['show_in_navigation'] ?? false),
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

    private function updateDialogIdentity(SiteSection $section, string $name, ?string $slug): SiteSection
    {
        if ($section->nodeType() !== SiteNodeType::Gallery) {
            return app(SiteSectionIdentityService::class)->update($section, $name, $slug);
        }

        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $section->getAttribute('artwork_category_id'));
        $galleryService = app(GalleryEditorialService::class);
        $gallery = $galleryService->update($gallery, [
            'name' => $name,
            'description' => $gallery->getAttribute('description'),
            'show_on_home' => (bool) $gallery->getAttribute('show_on_home'),
        ]);

        if (trim((string) $gallery->getAttribute('slug')) !== trim((string) $slug)) {
            $galleryService->changeSlug($gallery, (string) $slug);
        }

        /** @var SiteSection $fresh */
        $fresh = SiteSection::query()->findOrFail($section->getKey());

        return $fresh;
    }

    /** @param array<string, mixed> $arguments
     *  @return list<Action>
     */
    private function pageDialogHeaderActions(array $arguments): array
    {
        $section = $this->dialogSection($arguments);
        if (! $section->nodeType()->canChangePublication()) {
            return [];
        }

        $published = (string) $section->getAttribute('state') === 'published';
        $sectionId = (int) $section->getKey();

        return [
            Action::make('toggleDialogPublication')
                ->label($published ? 'Unpublish' : 'Publish')
                ->icon(($published ? AdminIcon::Unpublish : AdminIcon::Publish)->value)
                ->iconButton()
                ->color('gray')
                ->extraAttributes(['class' => 'admin-dialog__header-action'])
                ->action(fn (): mixed => $this->toggleSectionState($sectionId)),
        ];
    }

    /** @param array<string, mixed> $arguments */
    private function dialogSection(array $arguments): SiteSection
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail((int) ($arguments['section'] ?? 0));

        return $section;
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
