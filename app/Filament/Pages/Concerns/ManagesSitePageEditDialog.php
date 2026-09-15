<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionIdentityService;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\InteractsWithAdminEditDialogAutosave;
use App\Filament\Support\HomeSettingsDialog;
use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ManagesSitePageEditDialog
{
    use InteractsWithAdminEditDialogAutosave;

    public function editPageAction(): Action
    {
        $action = Action::make('editPage')
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
            ->extraModalFooterActions(fn (array $arguments): array => $this->pageDialogHeaderActions($arguments));

        return AdminDialog::edit($action);
    }

    public function editHomeAction(): Action
    {
        $dialog = app(HomeSettingsDialog::class);
        $action = Action::make('editHome')
            ->label('Edit')
            ->modalHeading('Home settings')
            ->fillForm(fn (): array => $dialog->fill())
            ->schema($dialog->schema())
            ->action(function (array $data) use ($dialog): void {
                $dialog->save($data);
                $this->loadSections();
                Notification::make()->title('Home settings saved')->success()->send();
            });

        return AdminDialog::edit($action);
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
     * @return list<Action>
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
