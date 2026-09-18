<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait ManagesSitePageCreateDialog
{
    public function addPageAction(): Action
    {
        $action = Action::make('addPage')
            ->label('Add page')
            ->modalHeading('Add page')
            ->schema([
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema([
                        Select::make('type')
                            ->label('Page type')
                            ->options(SiteSectionType::compactOptions())
                            ->default(SiteSectionType::CustomPage->value)
                            ->native()
                            ->required()
                            ->live(),
                        Select::make('template')
                            ->label('Template')
                            ->options(JournalTemplate::options())
                            ->default(JournalTemplate::Blog->value)
                            ->native()
                            ->required(fn (callable $get): bool => $get('type') === SiteSectionType::Journal->value)
                            ->visible(fn (callable $get): bool => $get('type') === SiteSectionType::Journal->value),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(160),
                        TextInput::make('slug')
                            ->label('Public slug')
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->required(fn (callable $get): bool => SiteSectionType::tryFrom((string) $get('type'))?->requiresSlug() ?? false)
                            ->visible(fn (callable $get): bool => SiteSectionType::tryFrom((string) $get('type'))?->requiresSlug() ?? false),
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
                    ]),
            ])
            ->action(function (array $data): void {
                $section = DB::transaction(fn (): SiteSection => $this->createPageFromDialog($data));
                $this->pageNumber = 1;
                $this->loadSections();

                Notification::make()
                    ->title($section->nodeType()->label().' added')
                    ->success()
                    ->send();
            });

        return AdminDialog::create($action, 'Create page', AdminDialogSize::Large);
    }

    /** @param array<string, mixed> $data */
    private function createPageFromDialog(array $data): SiteSection
    {
        $type = SiteSectionType::tryFrom((string) ($data['type'] ?? ''));
        if ($type === null || ! $type->isCreatable()) {
            throw ValidationException::withMessages(['type' => 'Choose a supported page type.']);
        }

        $name = trim((string) ($data['name'] ?? ''));
        $slug = trim((string) ($data['slug'] ?? ''));
        $parentId = $this->dialogParentId($data['parent_id'] ?? null);
        $position = $this->dialogPosition($data['position'] ?? null);
        $editorial = app(SiteSectionEditorialService::class);

        $section = match ($type) {
            SiteSectionType::NavigationNode => $editorial->createNavigationGroup($name),
            SiteSectionType::CustomPage => $editorial->createCustomPage($name, $slug),
            SiteSectionType::Journal => $editorial->createJournal(
                $name,
                $slug,
                (string) ($data['template'] ?? JournalTemplate::Blog->value),
            ),
            SiteSectionType::Gallery => $this->createGallerySection($name, $slug),
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
            ->where('type', SiteSectionType::Gallery->value)
            ->where('artwork_category_id', $gallery->getKey())
            ->firstOrFail();

        return $section;
    }
}
