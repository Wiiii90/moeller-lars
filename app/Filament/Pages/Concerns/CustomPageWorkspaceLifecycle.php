<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceLifecycle
{

    public function mount(int|string $section): void
    {
        /** @var SiteSection $siteSection */
        $siteSection = SiteSection::query()
            ->whereKey((int) $section)
            ->where('type', SiteSectionType::CustomPage->value)
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

    /**
     * Livewire public properties are restored from the browser snapshot on
     * each update. A tab opened before the independent Image-child row
     * refactor can therefore still carry the old four-field child summary.
     * The Blade table now requires full component projections. Rebuild only
     * when a hydrated snapshot has the obsolete shape, before any Action
     * or Sortable callback operates on its positional targets.
     */
    public function hydrate(): void
    {
        foreach ($this->components as $component) {
            if (! is_array($component)
                || ! isset(
                    $component['target'], $component['content'],
                    $component['index'], $component['type'],
                )
                || ! array_key_exists('accepts_children', $component)
                || ! array_key_exists('can_move_up', $component)
                || ! array_key_exists('can_move_down', $component)
                || ! is_array($component['children'] ?? null)) {
                $this->loadComponentProjection();
                return;
            }

            foreach ($component['children'] as $child) {
                if (! is_array($child)
                    || ! isset(
                        $child['target'],
                        $child['index'],
                        $child['image_index'],
                        $child['position'],
                        $child['content'],
                        $child['type'],
                    )
                    || ! array_key_exists('can_move_up', $child)
                    || ! array_key_exists('can_move_down', $child)) {
                    $this->loadComponentProjection();
                    return;
                }
            }
        }
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
            ->flatMap(static fn (array $row): array => [
                $row['target'], ...array_column($row['children'] ?? [], 'target'),
            ])
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
                    'parent_id' => $section->getAttribute('parent_id'),
                ];
            })
            ->schema([
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema([
                        TextInput::make('title')
                            ->label('Page title')
                            ->required()
                            ->maxLength(160),
                        TextInput::make('navigation_label')
                            ->label('Navigation label')
                            ->maxLength(160),
                        TextInput::make('slug')
                            ->label('Public URL slug')
                            ->required()
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                        Select::make('publication_state')
                            ->label('Page status')
                            ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                            ->required(),
                        Select::make('parent_id')
                            ->label('Navigation parent')
                            ->options(fn (): array => $this->parentOptions())
                            ->nullable()
                            ->placeholder(__('Top level'))
                            ->visible(fn (): bool => PublicContentSetting::navigationNestingEnabled()),
                    ]),
            ])
            ->modalHeading(__('Page settings'))
            ->action(function (array $data): void {
                $before = $this->section()->only([
                    'title',
                    'navigation_label',
                    'slug',
                    'state',
                    'parent_id',
                    'position',
                ]);

                try {
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
                            PublicContentSetting::navigationNestingEnabled() && is_numeric($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                        );
                    });
                } catch (ValidationException $exception) {
                    $path = $this->getMountedActionSchema()->getStatePath();
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $errors[$path.'.'.$field] = $messages;
                    }
                    throw ValidationException::withMessages($errors);
                }

                $section = $this->section()->fresh();
                $changed = $section instanceof SiteSection
                    && $before !== $section->only([
                        'title',
                        'navigation_label',
                        'slug',
                        'state',
                        'parent_id',
                        'position',
                    ]);
                $section ??= $this->section();
                $this->loadAnalyticsSnapshot($section);
                $this->reloadWorkspace();
                if ($changed) {
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(
                        title: 'Page settings saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Default);
    }
}
