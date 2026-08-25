<?php

namespace App\Filament\Pages;

use App\Domain\Admin\CvEntryEditorialService;
use App\Domain\Admin\EditorialRecordService;
use App\Domain\Content\CustomPageEditorialService;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SocialLinks;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CustomPageWorkspace extends Page
{
    /** @var array<string, string> */
    private const COMPONENT_LABELS = [
        'image' => 'Image',
        'cv_list' => 'CV List',
        'text' => 'Text',
        'list' => 'List',
        'divider' => 'Divider',
        'contact' => 'Contact',
    ];

    /** @var list<int> */
    private const CV_PAGE_SIZES = [25, 50, 100];

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pages/custom/{section}';

    protected static ?string $title = 'Custom Page';

    protected string $view = 'filament.pages.custom-page-workspace';

    public int $sectionId;

    public int $settingsId;

    public string $pageTitle = '';

    public ?string $publicUrl = null;

    /** @var list<array{label:string,value:string,description:string}> */
    public array $metrics = [];

    /** @var list<array<string, mixed>> */
    public array $components = [];

    public int $unfilteredComponentCount = 0;

    public string $componentSearch = '';

    public string $componentType = 'any';

    /** @var list<string> */
    public array $selectedComponentTargets = [];

    public bool $hasCvList = false;

    public int $cvEntryCount = 0;

    /** @var list<array<string, mixed>> */
    public array $cvEntries = [];

    /** @var list<string> */
    public array $cvSections = [];

    public bool $hasLegacyHiddenCvEntries = false;

    public string $cvSearch = '';

    public string $cvSection = 'any';

    public string $cvStatus = 'any';

    /** @var list<int|string> */
    public array $selectedCvEntryIds = [];

    public int $cvPage = 1;

    public int $cvPageSize = 25;

    public int $cvTotal = 0;

    public int $cvPages = 1;

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
        $this->selectedComponentTargets = [];
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function updatedComponentType(): void
    {
        if ($this->componentType !== 'any' && ! array_key_exists($this->componentType, self::COMPONENT_LABELS)) {
            $this->componentType = 'any';
        }

        $this->selectedComponentTargets = [];
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function resetComponentFilters(): void
    {
        $this->componentSearch = '';
        $this->componentType = 'any';
        $this->selectedComponentTargets = [];
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function moveComponent(int $index, string $type, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $changed = app(CustomPageEditorialService::class)->moveBlock(
            $this->settings(),
            $index,
            $type,
            $direction,
        );
        $this->selectedComponentTargets = [];
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()->title('Component order updated')->success()->send();
        }
    }

    public function moveSelectedComponents(string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $targets = $this->selectedComponentTargetData();
        if ($targets === []) {
            return;
        }

        $changed = app(CustomPageEditorialService::class)->moveSelectedBlocks(
            $this->settings(),
            $targets,
            $direction,
        );
        $count = count($targets);
        $this->selectedComponentTargets = [];
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()
                ->title('Selected components moved')
                ->body($count.' component'.($count === 1 ? '' : 's').' updated.')
                ->success()
                ->send();
        }
    }

    public function addComponentAction(): Action
    {
        return Action::make('addComponent')
            ->label('Add component')
            ->fillForm(fn (): array => [
                'type' => 'text',
                'image_decorative' => false,
                'form_state' => 'enabled',
                'show_email' => true,
                'show_form' => true,
                'social_platforms' => array_keys(SocialLinks::options()),
                'items' => [],
            ])
            ->schema($this->componentEditorSchema(includeTypeSelect: true))
            ->modalHeading('Add component')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Add component')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                app(CustomPageEditorialService::class)->addBlock(
                    $this->settings(),
                    $this->componentPayload($data),
                );
                $this->selectedComponentTargets = [];
                $this->reloadWorkspace();
                Notification::make()->title('Component added')->success()->send();
            });
    }

    public function editComponentAction(): Action
    {
        return Action::make('editComponent')
            ->label('Edit')
            ->fillForm(fn (array $arguments): array => $this->actionComponent($arguments))
            ->schema($this->componentEditorSchema(includeTypeSelect: false))
            ->modalHeading(function (array $arguments): string {
                $block = $this->actionComponent($arguments);

                return 'Edit '.(self::COMPONENT_LABELS[(string) $block['type']] ?? 'component');
            })
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $changed = app(CustomPageEditorialService::class)->updateBlock(
                    $this->settings(),
                    $index,
                    $type,
                    $this->componentPayload($data),
                );
                $this->selectedComponentTargets = [];
                $this->reloadWorkspace();

                Notification::make()
                    ->title($changed ? 'Component saved' : 'No component changes')
                    ->success()
                    ->send();
            });
    }

    public function deleteComponentAction(): Action
    {
        return Action::make('deleteComponent')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete component?')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->deleteBlock(
                    $this->settings(),
                    $index,
                    $type,
                );
                $this->selectedComponentTargets = [];
                $this->reloadWorkspace();
                Notification::make()->title('Component deleted')->success()->send();
            });
    }

    public function deleteSelectedComponentsAction(): Action
    {
        return Action::make('deleteSelectedComponents')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected components?')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (): void {
                $targets = $this->selectedComponentTargetData();
                if ($targets === []) {
                    return;
                }

                app(CustomPageEditorialService::class)->deleteBlocks($this->settings(), $targets);
                $count = count($targets);
                $this->selectedComponentTargets = [];
                $this->reloadWorkspace();
                Notification::make()
                    ->title('Selected components deleted')
                    ->body($count.' component'.($count === 1 ? '' : 's').' deleted.')
                    ->success()
                    ->send();
            });
    }

    public function updatedCvSearch(): void
    {
        $this->refreshCvFromFirstPage();
    }

    public function updatedCvSection(): void
    {
        if ($this->cvSection !== 'any' && ! in_array($this->cvSection, $this->cvSections, true)) {
            $this->cvSection = 'any';
        }

        $this->refreshCvFromFirstPage();
    }

    public function updatedCvStatus(): void
    {
        $allowed = ['any', 'draft', 'published', 'archived'];
        if ($this->hasLegacyHiddenCvEntries) {
            $allowed[] = 'hidden';
        }
        if (! in_array($this->cvStatus, $allowed, true)) {
            $this->cvStatus = 'any';
        }

        $this->refreshCvFromFirstPage();
    }

    public function updatedCvPageSize(mixed $value): void
    {
        $size = (int) $value;
        $this->cvPageSize = in_array($size, self::CV_PAGE_SIZES, true) ? $size : 25;
        $this->refreshCvFromFirstPage();
    }

    public function resetCvFilters(): void
    {
        $this->cvSearch = '';
        $this->cvSection = 'any';
        $this->cvStatus = 'any';
        $this->refreshCvFromFirstPage();
    }

    public function previousCvPage(): void
    {
        if ($this->cvPage <= 1) {
            return;
        }

        $this->cvPage--;
        $this->selectedCvEntryIds = [];
        $this->loadCvProjection();
    }

    public function nextCvPage(): void
    {
        if ($this->cvPage >= $this->cvPages) {
            return;
        }

        $this->cvPage++;
        $this->selectedCvEntryIds = [];
        $this->loadCvProjection();
    }

    public function toggleVisibleCvSelection(): void
    {
        $visibleIds = collect($this->cvEntries)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
        if ($visibleIds === []) {
            return;
        }

        $selected = $this->normalizedCvSelection();
        $allVisibleSelected = count(array_intersect($visibleIds, $selected)) === count($visibleIds);
        $this->selectedCvEntryIds = $allVisibleSelected
            ? array_values(array_diff($selected, $visibleIds))
            : array_values(array_unique(array_merge($selected, $visibleIds)));
    }

    public function addCvEntryAction(): Action
    {
        return Action::make('addCvEntry')
            ->label('Add CV entry')
            ->fillForm(fn (): array => ['date_precision' => 'unknown'])
            ->schema($this->cvEntrySchema())
            ->modalHeading('Add CV entry')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Create draft')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                app(CvEntryEditorialService::class)->createDraft($data);
                $this->selectedCvEntryIds = [];
                $this->reloadWorkspace();
                Notification::make()->title('CV draft created')->success()->send();
            });
    }

    public function editCvEntryAction(): Action
    {
        return Action::make('editCvEntry')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $entry = $this->actionCvEntry($arguments);

                return [
                    'section' => $entry->getAttribute('section'),
                    'title' => $entry->getAttribute('title'),
                    'year_text' => $entry->getAttribute('year_text'),
                    'date_precision' => $entry->getAttribute('date_precision'),
                    'starts_on' => $entry->getAttribute('starts_on'),
                    'ends_on' => $entry->getAttribute('ends_on'),
                    'organisation' => $entry->getAttribute('organisation'),
                    'location' => $entry->getAttribute('location'),
                    'body' => $entry->getAttribute('body'),
                    'external_url' => $entry->getAttribute('external_url'),
                ];
            })
            ->schema($this->cvEntrySchema())
            ->modalHeading(fn (array $arguments): string => 'Edit '.(string) $this->actionCvEntry($arguments)->getAttribute('title'))
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                app(CvEntryEditorialService::class)->update($this->actionCvEntry($arguments), $data);
                $this->selectedCvEntryIds = [];
                $this->reloadCvMetadata();
                $this->loadCvProjection();
                Notification::make()->title('CV entry saved')->success()->send();
            });
    }

    public function deleteCvEntryAction(): Action
    {
        return Action::make('deleteCvEntry')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete CV entry?')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                app(EditorialRecordService::class)->deleteCv($this->actionCvEntry($arguments));
                $this->selectedCvEntryIds = [];
                $this->reloadWorkspace();
                Notification::make()->title('CV entry deleted')->success()->send();
            });
    }

    public function deleteSelectedCvEntriesAction(): Action
    {
        return Action::make('deleteSelectedCvEntries')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected CV entries?')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (): void {
                $ids = $this->normalizedCvSelection();
                if ($ids === []) {
                    return;
                }

                /** @var list<CvEntry> $entries */
                $entries = CvEntry::query()
                    ->whereIn('id', $ids)
                    ->orderBy('position')
                    ->orderBy('id')
                    ->get()
                    ->values()
                    ->all();
                $service = app(EditorialRecordService::class);
                $success = 0;
                $failed = 0;

                foreach ($entries as $entry) {
                    try {
                        $service->deleteCv($entry);
                        $success++;
                    } catch (Throwable $exception) {
                        if (! $exception instanceof ValidationException) {
                            report($exception);
                        }
                        $failed++;
                    }
                }

                $this->selectedCvEntryIds = [];
                $this->reloadWorkspace();
                $this->sendBatchNotification('Delete selected CV entries', $success, 0, $failed);
            });
    }

    public function moveCvEntry(int $entryId, string $direction): void
    {
        if (! $this->cvReorderEnabled()) {
            return;
        }

        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        $changed = app(EditorialRecordService::class)->move($entry, $direction);
        $this->selectedCvEntryIds = [];
        $this->loadCvProjection();

        if ($changed) {
            Notification::make()->title('CV order updated')->success()->send();
        }
    }

    public function transitionCvEntry(int $entryId, string $action): void
    {
        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        $service = app(EditorialRecordService::class);

        match ($action) {
            'publish' => $service->publish($entry),
            'unpublish' => $service->unpublish($entry),
            'archive' => $service->archive($entry),
            'restore' => $service->restoreDraft($entry),
            default => throw ValidationException::withMessages(['state' => 'Unsupported CV state action.']),
        };

        $this->selectedCvEntryIds = [];
        $this->loadCvProjection();
        Notification::make()->title('CV entry updated')->success()->send();
    }

    public function moveSelectedCvEntries(string $direction): void
    {
        if (! $this->cvReorderEnabled()) {
            return;
        }

        $ids = $this->normalizedCvSelection();
        if ($ids === []) {
            return;
        }

        $entries = CvEntry::query()
            ->whereIn('id', $ids)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values()
            ->all();
        if ($direction === 'down') {
            $entries = array_reverse($entries);
        }

        $success = 0;
        $failed = 0;
        $service = app(EditorialRecordService::class);
        foreach ($entries as $entry) {
            try {
                if ($entry instanceof CvEntry && $service->move($entry, $direction)) {
                    $success++;
                }
            } catch (Throwable $exception) {
                if (! $exception instanceof ValidationException) {
                    report($exception);
                }
                $failed++;
            }
        }

        $this->selectedCvEntryIds = [];
        $this->loadCvProjection();
        $this->sendBatchNotification('Move selected CV entries', $success, 0, $failed);
    }

    public function transitionSelectedCvEntries(string $action): void
    {
        $ids = $this->normalizedCvSelection();
        if ($ids === []) {
            return;
        }

        /** @var list<CvEntry> $entries */
        $entries = CvEntry::query()
            ->whereIn('id', $ids)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->values()
            ->all();
        $service = app(EditorialRecordService::class);
        $success = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($entries as $entry) {
            $state = (string) $entry->getAttribute('state');
            if (! $this->cvTransitionApplies($state, $action)) {
                $skipped++;
                continue;
            }

            try {
                match ($action) {
                    'publish' => $service->publish($entry),
                    'unpublish' => $service->unpublish($entry),
                    'archive' => $service->archive($entry),
                    'restore' => $service->restoreDraft($entry),
                    default => throw ValidationException::withMessages(['state' => 'Unsupported CV state action.']),
                };
                $success++;
            } catch (Throwable $exception) {
                if (! $exception instanceof ValidationException) {
                    report($exception);
                }
                $failed++;
            }
        }

        $this->selectedCvEntryIds = [];
        $this->loadCvProjection();
        $this->sendBatchNotification('Update selected CV entries', $success, $skipped, $failed);
    }

    private function reloadWorkspace(): void
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($this->sectionId);
        $this->pageTitle = (string) ($section->getAttribute('title') ?: $section->getAttribute('navigation_label') ?: 'Custom Page');
        $this->publicUrl = (string) $section->getAttribute('state') === 'published'
            ? app(SiteNodeRoute::class)->url($section)
            : null;

        $this->loadComponentProjection(refreshCvCount: true);
        if ($this->hasCvList) {
            $this->reloadCvMetadata();
            $this->loadCvProjection();
        } else {
            $this->resetCvState();
        }
    }

    private function loadComponentProjection(bool $refreshCvCount): void
    {
        $blocks = $this->settings()->components();
        $this->unfilteredComponentCount = count($blocks);
        $this->hasCvList = collect($blocks)->contains(
            static fn (array $block): bool => ($block['type'] ?? null) === 'cv_list',
        );

        if ($refreshCvCount) {
            $this->cvEntryCount = $this->hasCvList ? CvEntry::query()->count() : 0;
        } elseif (! $this->hasCvList) {
            $this->cvEntryCount = 0;
        }

        $imageIds = collect($blocks)
            ->filter(static fn (array $block): bool => ($block['type'] ?? null) === 'image')
            ->pluck('media_asset_id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
        $imageNames = $imageIds === []
            ? []
            : MediaAsset::query()
                ->whereIn('id', $imageIds)
                ->pluck('original_filename', 'id')
                ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
                ->all();

        $counts = array_fill_keys(array_keys(self::COMPONENT_LABELS), 0);
        $projected = [];
        foreach ($blocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }

            $mediaId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
            $imageName = $mediaId !== null ? ($imageNames[$mediaId] ?? null) : null;
            $summary = $this->componentSummary($block, $imageName);
            $searchText = $this->componentSearchText($block, $imageName);

            if ($this->componentType !== 'any' && $type !== $this->componentType) {
                continue;
            }
            $needle = mb_strtolower(trim($this->componentSearch));
            if ($needle !== '' && ! str_contains(mb_strtolower($searchText), $needle)) {
                continue;
            }

            $projected[] = [
                'index' => $index,
                'type' => $type,
                'type_label' => self::COMPONENT_LABELS[$type] ?? 'Component',
                'summary' => $summary,
                'target' => $index.':'.$type,
                'editable' => ! in_array($type, ['cv_list', 'divider'], true),
                'can_move_up' => $index > 0,
                'can_move_down' => $index < count($blocks) - 1,
                'is_cv_list' => $type === 'cv_list',
            ];
        }

        $this->components = $projected;
        $this->metrics = [
            ['label' => 'Components', 'value' => number_format(count($blocks)), 'description' => 'Page sequence'],
            ['label' => 'Images', 'value' => number_format($counts['image']), 'description' => 'Image components'],
            ['label' => 'Text', 'value' => number_format($counts['text']), 'description' => 'Text components'],
            ['label' => 'Lists', 'value' => number_format($counts['list']), 'description' => 'List components'],
            ['label' => 'CV entries', 'value' => number_format($this->hasCvList ? $this->cvEntryCount : 0), 'description' => 'Canonical records'],
            ['label' => 'Contact', 'value' => number_format($counts['contact']), 'description' => 'Contact components'],
        ];
    }

    private function reloadCvMetadata(): void
    {
        $this->cvSections = CvEntry::query()
            ->whereNotNull('section')
            ->where('section', '<>', '')
            ->distinct()
            ->orderBy('section')
            ->pluck('section')
            ->map(static fn (mixed $section): string => (string) $section)
            ->values()
            ->all();
        $this->hasLegacyHiddenCvEntries = CvEntry::query()->where('state', 'hidden')->exists();

        if ($this->cvSection !== 'any' && ! in_array($this->cvSection, $this->cvSections, true)) {
            $this->cvSection = 'any';
        }
        if ($this->cvStatus === 'hidden' && ! $this->hasLegacyHiddenCvEntries) {
            $this->cvStatus = 'any';
        }
    }

    private function loadCvProjection(): void
    {
        if (! $this->hasCvList) {
            $this->resetCvState();
            return;
        }

        $query = CvEntry::query();
        $needle = mb_strtolower(trim($this->cvSearch));
        if ($needle !== '') {
            $pattern = '%'.$needle.'%';
            $query->where(function (Builder $query) use ($pattern): void {
                $query
                    ->whereRaw('LOWER(year_text) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(title) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(organisation) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(location) LIKE ?', [$pattern])
                    ->orWhereRaw('LOWER(section) LIKE ?', [$pattern]);
            });
        }
        if ($this->cvSection !== 'any') {
            $query->where('section', $this->cvSection);
        }
        if ($this->cvStatus !== 'any') {
            $query->where('state', $this->cvStatus);
        }

        $this->cvTotal = (clone $query)->count();
        $this->cvPages = max(1, (int) ceil($this->cvTotal / $this->cvPageSize));
        $this->cvPage = min(max(1, $this->cvPage), $this->cvPages);

        $records = $query
            ->orderBy('position')
            ->orderBy('id')
            ->forPage($this->cvPage, $this->cvPageSize)
            ->get();
        $globalOffset = ($this->cvPage - 1) * $this->cvPageSize;
        $reorderEnabled = $this->cvReorderEnabled();
        $canonicalTotal = $this->cvEntryCount;

        $this->cvEntries = $records->values()->map(static function (CvEntry $entry, int $index) use ($globalOffset, $reorderEnabled, $canonicalTotal): array {
            $meta = array_values(array_filter([
                $entry->getAttribute('organisation'),
                $entry->getAttribute('location'),
            ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
            $globalIndex = $globalOffset + $index;

            return [
                'id' => (int) $entry->getKey(),
                'date' => (string) ($entry->getAttribute('year_text') ?? ''),
                'title' => (string) $entry->getAttribute('title'),
                'meta' => implode(' · ', $meta),
                'section' => (string) $entry->getAttribute('section'),
                'state' => (string) $entry->getAttribute('state'),
                'state_label' => ucfirst((string) $entry->getAttribute('state')),
                'can_move_up' => $reorderEnabled && $globalIndex > 0,
                'can_move_down' => $reorderEnabled && $globalIndex < $canonicalTotal - 1,
            ];
        })->all();
    }

    private function refreshCvFromFirstPage(): void
    {
        $this->cvPage = 1;
        $this->selectedCvEntryIds = [];
        $this->loadCvProjection();
    }

    private function resetCvState(): void
    {
        $this->cvEntries = [];
        $this->cvSections = [];
        $this->hasLegacyHiddenCvEntries = false;
        $this->selectedCvEntryIds = [];
        $this->cvTotal = 0;
        $this->cvPages = 1;
        $this->cvPage = 1;
    }

    private function componentReorderEnabled(): bool
    {
        return trim($this->componentSearch) === '' && $this->componentType === 'any';
    }

    private function cvReorderEnabled(): bool
    {
        return trim($this->cvSearch) === '' && $this->cvSection === 'any' && $this->cvStatus === 'any';
    }

    /** @return list<array{index:int,type:string}> */
    private function selectedComponentTargetData(): array
    {
        $targets = [];
        foreach ($this->selectedComponentTargets as $target) {
            if (! is_string($target) || ! str_contains($target, ':')) {
                continue;
            }
            [$index, $type] = explode(':', $target, 2);
            if (! ctype_digit($index) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
                continue;
            }
            $targets[] = ['index' => (int) $index, 'type' => $type];
        }

        return $targets;
    }

    /** @return list<int> */
    private function normalizedCvSelection(): array
    {
        return collect($this->selectedCvEntryIds)
            ->filter(static fn (mixed $id): bool => is_numeric($id) && (int) $id > 0)
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /** @return array{0:int,1:string} */
    private function actionComponentTarget(array $arguments): array
    {
        $index = $arguments['componentIndex'] ?? null;
        $type = $arguments['componentType'] ?? null;
        if (! is_numeric($index) || ! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['component' => 'The selected component is invalid.']);
        }

        return [(int) $index, $type];
    }

    /** @return array<string, mixed> */
    private function actionComponent(array $arguments): array
    {
        [$index, $type] = $this->actionComponentTarget($arguments);
        $block = $this->settings()->components()[$index] ?? null;
        if (! is_array($block) || ($block['type'] ?? null) !== $type) {
            throw ValidationException::withMessages(['component' => 'This component changed. Reload and try again.']);
        }

        return $block;
    }

    private function actionCvEntry(array $arguments): CvEntry
    {
        $id = $arguments['entry'] ?? null;
        if (! is_numeric($id)) {
            throw ValidationException::withMessages(['entry' => 'The selected CV entry is invalid.']);
        }

        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail((int) $id);

        return $entry;
    }

    /** @return list<mixed> */
    private function componentEditorSchema(bool $includeTypeSelect): array
    {
        $typeField = $includeTypeSelect
            ? Select::make('type')
                ->label('Component')
                ->options(self::COMPONENT_LABELS)
                ->required()
                ->live()
            : Hidden::make('type')->required();

        return [
            $typeField,
            Select::make('media_asset_id')
                ->label('Image from Media Files')
                ->options(fn (): array => MediaAsset::query()
                    ->where('state', 'available')
                    ->where('mime_type', 'like', 'image/%')
                    ->orderBy('original_filename')
                    ->pluck('original_filename', 'id')
                    ->all())
                ->searchable()
                ->required(fn (callable $get): bool => $get('type') === 'image')
                ->live()
                ->visible(fn (callable $get): bool => $get('type') === 'image'),
            Placeholder::make('image_preview')
                ->label('Preview')
                ->content(function (callable $get): HtmlString|string {
                    $mediaId = $get('media_asset_id');
                    if (! is_numeric($mediaId)) {
                        return 'Choose an image from Media Files.';
                    }

                    /** @var MediaAsset|null $asset */
                    $asset = MediaAsset::query()->find((int) $mediaId);
                    if (! $asset instanceof MediaAsset) {
                        return 'The selected image is unavailable.';
                    }

                    $url = e(route('admin.media.original', ['mediaAsset' => $asset]));
                    $filename = e((string) $asset->getAttribute('original_filename'));

                    return new HtmlString(
                        '<div class="custom-page-dialog__image-preview">'
                        .'<img src="'.$url.'" alt="" loading="lazy">'
                        .'<span>'.$filename.'</span>'
                        .'</div>',
                    );
                })
                ->visible(fn (callable $get): bool => $get('type') === 'image'),
            Toggle::make('image_decorative')
                ->label('Decorative image')
                ->default(false)
                ->visible(fn (callable $get): bool => $get('type') === 'image'),
            TextInput::make('title')
                ->label('Heading')
                ->maxLength(160)
                ->visible(fn (callable $get): bool => in_array($get('type'), ['text', 'list'], true)),
            MarkdownEditor::make('body')
                ->label('Text')
                ->toolbarButtons([
                    ['bold', 'italic', 'link'],
                    ['bulletList', 'orderedList'],
                    ['undo', 'redo'],
                ])
                ->maxLength(20000)
                ->visible(fn (callable $get): bool => $get('type') === 'text'),
            Repeater::make('items')
                ->label('List entries')
                ->extraAttributes(['class' => 'custom-page-dialog__list-entries'])
                ->schema([
                    Toggle::make('visible')->label('Visible on public page')->default(true),
                    TextInput::make('date')->label('Date / year')->maxLength(120),
                    TextInput::make('title')->required()->maxLength(240),
                    TextInput::make('meta')->label('Organisation / context')->maxLength(240),
                    TextInput::make('location')->maxLength(240),
                    TextInput::make('url')->label('Optional link')->url()->maxLength(2048),
                    MarkdownEditor::make('body')
                        ->label('Details')
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->maxLength(10000),
                ])
                ->defaultItems(0)
                ->addActionLabel('Add list entry')
                ->reorderableWithButtons()
                ->reorderableWithDragAndDrop(false)
                ->itemLabel(fn (array $state): ?string => isset($state['title']) && is_string($state['title']) ? $state['title'] : null)
                ->visible(fn (callable $get): bool => $get('type') === 'list'),
            Select::make('form_state')
                ->label('Form presentation')
                ->options([
                    'enabled' => 'Enabled',
                    'under_construction' => 'Under construction',
                    'hidden' => 'Hidden',
                ])
                ->default('enabled')
                ->required(fn (callable $get): bool => $get('type') === 'contact')
                ->live()
                ->visible(fn (callable $get): bool => $get('type') === 'contact'),
            TextInput::make('status_text')
                ->label('Status text')
                ->maxLength(500)
                ->required(fn (callable $get): bool => $get('type') === 'contact' && $get('form_state') === 'under_construction')
                ->visible(fn (callable $get): bool => $get('type') === 'contact' && $get('form_state') === 'under_construction'),
            Toggle::make('show_email')
                ->label('Show public email from General')
                ->default(true)
                ->visible(fn (callable $get): bool => $get('type') === 'contact'),
            Toggle::make('show_form')
                ->label('Show contact form')
                ->default(true)
                ->visible(fn (callable $get): bool => $get('type') === 'contact'),
            Select::make('social_platforms')
                ->label('Social links from General')
                ->options(SocialLinks::options())
                ->multiple()
                ->default(array_keys(SocialLinks::options()))
                ->visible(fn (callable $get): bool => $get('type') === 'contact'),
        ];
    }

    /** @return list<mixed> */
    private function cvEntrySchema(): array
    {
        return [
            TextInput::make('section')->required()->maxLength(120),
            TextInput::make('title')->required()->maxLength(240),
            TextInput::make('year_text')->label('Displayed date/year')->required()->maxLength(80),
            Select::make('date_precision')
                ->options([
                    'unknown' => 'Unknown',
                    'year' => 'Year',
                    'month' => 'Month',
                    'day' => 'Day',
                ])
                ->required()
                ->default('unknown'),
            DatePicker::make('starts_on')->nullable(),
            DatePicker::make('ends_on')->nullable(),
            TextInput::make('organisation')->maxLength(240)->nullable(),
            TextInput::make('location')->maxLength(240)->nullable(),
            MarkdownEditor::make('body')
                ->label('Details')
                ->toolbarButtons([
                    ['bold', 'italic', 'link'],
                    ['bulletList', 'orderedList'],
                    ['undo', 'redo'],
                ])
                ->maxLength(10000)
                ->nullable(),
            TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
        ];
    }

    /** @param array<string, mixed> $data */
    private function componentPayload(array $data): array
    {
        $type = $data['type'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported component type.']);
        }

        return match ($type) {
            'image' => [
                'type' => 'image',
                'media_asset_id' => is_numeric($data['media_asset_id'] ?? null) ? (int) $data['media_asset_id'] : null,
                'image_decorative' => (bool) ($data['image_decorative'] ?? false),
            ],
            'cv_list' => ['type' => 'cv_list'],
            'text' => [
                'type' => 'text',
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
            ],
            'list' => [
                'type' => 'list',
                'title' => $data['title'] ?? null,
                'items' => is_array($data['items'] ?? null) ? array_values($data['items']) : [],
            ],
            'divider' => ['type' => 'divider'],
            'contact' => [
                'type' => 'contact',
                'form_state' => $data['form_state'] ?? 'enabled',
                'status_text' => $data['status_text'] ?? null,
                'show_email' => (bool) ($data['show_email'] ?? true),
                'show_form' => (bool) ($data['show_form'] ?? true),
                'social_platforms' => is_array($data['social_platforms'] ?? null) ? array_values($data['social_platforms']) : [],
            ],
        };
    }

    /** @param array<string, mixed> $block */
    private function componentSummary(array $block, ?string $imageName): string
    {
        $type = $block['type'] ?? null;

        return match ($type) {
            'image' => ($imageName ?: 'Image unavailable').' · '.((bool) ($block['image_decorative'] ?? false) ? 'Decorative image' : 'Content image'),
            'cv_list' => $this->cvEntryCount.' CV '.($this->cvEntryCount === 1 ? 'entry' : 'entries'),
            'text' => (is_string($block['title'] ?? null) && trim($block['title']) !== '' ? trim($block['title']) : 'Text')
                .' · '.mb_strlen(trim((string) ($block['body'] ?? ''))).' characters',
            'list' => (is_string($block['title'] ?? null) && trim($block['title']) !== '' ? trim($block['title']) : 'List')
                .' · '.count(is_array($block['items'] ?? null) ? $block['items'] : []).' entries',
            'divider' => 'Divider',
            'contact' => ucfirst(str_replace('_', ' ', (string) ($block['form_state'] ?? 'enabled')))
                .' · '.((bool) ($block['show_email'] ?? true) ? 'Email shown' : 'Email hidden'),
            default => 'Component',
        };
    }

    /** @param array<string, mixed> $block */
    private function componentSearchText(array $block, ?string $imageName): string
    {
        $type = is_string($block['type'] ?? null) ? $block['type'] : '';
        $parts = [self::COMPONENT_LABELS[$type] ?? $type, $imageName, $block['title'] ?? null];
        if ($type === 'list' && is_array($block['items'] ?? null)) {
            foreach ($block['items'] as $item) {
                if (is_array($item)) {
                    $parts[] = $item['title'] ?? null;
                }
            }
        }

        return implode(' ', array_filter($parts, static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));
    }

    private function cvTransitionApplies(string $state, string $action): bool
    {
        return match ($action) {
            'publish' => $state === 'draft',
            'unpublish' => $state === 'published',
            'archive' => $state !== 'archived',
            'restore' => in_array($state, ['archived', 'hidden'], true),
            default => false,
        };
    }

    private function sendBatchNotification(string $title, int $success, int $skipped, int $failed): void
    {
        $notification = Notification::make()
            ->title($title)
            ->body($success.' updated · '.$skipped.' skipped · '.$failed.' failed');

        if ($failed > 0) {
            $notification->warning();
        } else {
            $notification->success();
        }

        $notification->send();
    }

    private function settings(): CustomPageSetting
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->findOrFail($this->settingsId);

        return $settings;
    }
}
