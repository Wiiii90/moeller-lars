<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\InteractsWithAdminEditDialogAutosave;
use App\Models\CustomPageSetting;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\DB;

trait CustomPageWorkspaceLifecycle
{
    use InteractsWithAdminEditDialogAutosave;

    public function mount(int|string $section): void
    {
        /** @var SiteSection $siteSection */
        $siteSection = SiteSection::query()
            ->whereKey((int) $section)
            ->where('type', SiteNodeType::CustomPage->value)
            ->firstOrFail();

        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()
            ->where('site_section_id', $siteSection->getKey())
            ->firstOrFail();

        $this->sectionId = (int) $siteSection->getKey();
        $this->settingsId = (int) $settings->getKey();
        $this->loadAvailableSocialPlatforms();
        $this->loadAnalyticsSnapshot($siteSection);
        $this->reloadWorkspace();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string
    {
        return $this->pageTitle;
    }

    public function updatedComponentSearch(): void
    {
        $this->refreshFromFirstPage(refreshCvCount: false);
    }

    public function updatedComponentType(): void
    {
        if ($this->componentType !== 'any' && ! array_key_exists($this->componentType, self::COMPONENT_LABELS)) {
            $this->componentType = 'any';
        }

        $this->refreshFromFirstPage(refreshCvCount: false);
    }

    public function updatedPageSize(mixed $value): void
    {
        $this->pageSize = $this->normalizePageSize($value);
        $this->refreshFromFirstPage(refreshCvCount: false);
    }

    public function resetComponentFilters(): void
    {
        $this->componentSearch = '';
        $this->componentType = 'any';
        $this->refreshFromFirstPage(refreshCvCount: false);
    }

    public function previousPage(): void
    {
        if ($this->page <= 1) {
            return;
        }

        $this->page--;
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function nextPage(): void
    {
        if ($this->page >= $this->pages) {
            return;
        }

        $this->page++;
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function selectAllVisible(bool $selected): void
    {
        if (! $selected) {
            $this->clearSelections();

            return;
        }

        $this->selectedComponentTargets = collect($this->components)
            ->pluck('target')
            ->filter(static fn (mixed $target): bool => is_string($target))
            ->values()
            ->all();
        $this->selectedChildTargets = collect($this->components)
            ->flatMap(static fn (array $component): array => is_array($component['children'] ?? null) ? $component['children'] : [])
            ->pluck('target')
            ->filter(static fn (mixed $target): bool => is_string($target))
            ->values()
            ->all();
    }

    public function pageSettingsAction(): Action
    {
        $action = Action::make('pageSettings')
            ->label('Settings')
            ->fillForm(function (): array {
                $section = $this->section();

                return [
                    'title' => (string) $section->getAttribute('title'),
                    'navigation_label' => (string) ($section->getAttribute('navigation_label') ?? ''),
                    'slug' => (string) $section->getAttribute('slug'),
                    'publication_state' => (string) $section->getAttribute('state') === 'published' ? 'published' : 'unpublished',
                    'show_in_navigation' => (bool) $section->getAttribute('show_in_navigation'),
                    'parent_id' => $section->getAttribute('parent_id'),
                ];
            })
            ->schema([
                TextInput::make('title')
                    ->label('Page title')
                    ->required()
                    ->maxLength(160),
                TextInput::make('navigation_label')
                    ->label('Navigation label')
                    ->maxLength(160)
                    ->helperText('Leave empty to use the page title.'),
                TextInput::make('slug')
                    ->label('Public URL slug')
                    ->required()
                    ->maxLength(80)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->helperText('Changing this keeps the previous public URL as a redirect.'),
                Select::make('publication_state')
                    ->label('Page status')
                    ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                    ->required(),
                Toggle::make('show_in_navigation')->label('Show in navigation'),
                Select::make('parent_id')
                    ->label('Navigation parent')
                    ->options(fn (): array => $this->parentOptions())
                    ->nullable()
                    ->placeholder('Top level'),
            ])
            ->modalHeading('Page settings')
            ->action(function (array $data): void {
                DB::transaction(function () use ($data): void {
                    $service = app(SiteSectionEditorialService::class);
                    $section = $service->updateCustomPageIdentity(
                        $this->section(),
                        (string) ($data['title'] ?? ''),
                        is_string($data['navigation_label'] ?? null) ? $data['navigation_label'] : null,
                        (string) ($data['slug'] ?? ''),
                    );

                    $service->updatePlacement(
                        $section,
                        ($data['publication_state'] ?? 'unpublished') === 'published' ? 'published' : 'hidden',
                        (bool) ($data['show_in_navigation'] ?? false),
                        is_numeric($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                    );
                });

                $section = $this->section();
                $this->loadAnalyticsSnapshot($section);
                $this->reloadWorkspace();
                Notification::make()->title('Page settings saved')->success()->send();
            });

        return AdminDialog::edit($action);
    }
}
