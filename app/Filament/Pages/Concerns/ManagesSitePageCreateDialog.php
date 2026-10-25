<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SitePageHeading;
use App\Filament\Support\PageHeadingFields;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

trait ManagesSitePageCreateDialog
{
    public function addPageAction(): Action
    {
        $action = Action::make('addPage')
            ->label('Add page')
            ->modalHeading(__('Add page'))
            ->schema([
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema([
                        Select::make('type')
                            ->label('Page type')
                            ->options(array_map('__', SiteSectionType::compactOptions()))
                            ->default(SiteSectionType::CustomPage->value)
                            ->native()
                            ->required()
                            ->live(),
                        Select::make('template')
                            ->label('Template')
                            ->options(array_map('__', JournalTemplate::options()))
                            ->default(JournalTemplate::Blog->value)
                            ->native()
                            ->required(fn (callable $get): bool => $get('type') === SiteSectionType::Journal->value)
                            ->visible(fn (callable $get): bool => $get('type') === SiteSectionType::Journal->value),
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(160)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get): void {
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', rtrim(mb_substr(Str::slug($state), 0, 80), '-'));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Public slug')
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->required(fn (callable $get): bool => SiteSectionType::tryFrom((string) $get('type'))?->requiresSlug() ?? false)
                            ->visible(fn (callable $get): bool => SiteSectionType::tryFrom((string) $get('type'))?->requiresSlug() ?? false),
                        Select::make('parent_id')
                            ->label('Parent page')
                            ->options(fn (): array => $this->parentOptions)
                            ->placeholder(__('Top level'))
                            ->native()
                            ->nullable()
                            ->visible(fn (): bool => $this->nestedNavigationEnabled),
                        TextInput::make('position')
                            ->label('Position')
                            ->numeric()
                            ->integer()
                            ->minValue(1)
                            ->nullable()
                            ->placeholder(__('Last')),
                    ]),
                PageHeadingFields::schema()
                    ->visible(fn (Get $get): bool =>
                        SiteSectionType::tryFrom((string) $get('type'))?->hasPublicPage() ?? false),
            ])
            ->action(function (array $data): void {
                try {
                    $section = DB::transaction(fn (): SiteSection => $this->createPageFromDialog($data));
                } catch (ValidationException $exception) {
                    $this->throwPageDialogValidation($exception);
                }
                $this->pageNumber = 1;
                $this->loadSections(navigationChanged: true);

                app(AdminNotifier::class)->notification(
                    title: $section->nodeType()->label().' added',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Create page', AdminDialogSize::Large, AdminIcon::Pages);
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

        // Add Page writes heading settings through the same editorial owner
        // as Edit and Home, but only once the newly created page exists.
        if ($fresh->hasPublicPage()) {
            $fresh = $editorial->updatePageHeading($fresh, SitePageHeading::validated([
                'content_heading_enabled' => (bool) ($data['content_heading_enabled'] ?? false),
                'content_heading_override' => $data['content_heading_override'] ?? null,
                'content_heading_alignment' => $data['content_heading_alignment'] ?? 'left',
                'content_heading_divider' => $data['content_heading_divider'] ?? 'none',
            ]));
        }

        return $this->publishNewPageIfConfigured($fresh);
    }

    /**
     * Apply the Pages creation preference only after the record, its type
     * configuration and final hierarchy placement exist. Publication remains
     * subject to the canonical editorial validation and audit trail.
     */
    private function publishNewPageIfConfigured(SiteSection $section): SiteSection
    {
        if (! PublicContentSetting::pagesAutoPublish()) {
            return $section;
        }

        $parentId = $section->getAttribute('parent_id');

        return app(SiteSectionEditorialService::class)->updatePlacement(
            $section,
            'published',
            $parentId === null ? null : (int) $parentId,
        );
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
