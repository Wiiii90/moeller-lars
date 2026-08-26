<?php

namespace App\Filament\Pages;

use App\Domain\Admin\CvEntryEditorialService;
use App\Domain\Admin\EditorialRecordService;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Content\CustomPageEditorialService;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SocialLinks;
use App\Filament\Support\AdminRichText;
use App\Filament\Support\MediaAssetSelect;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

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

    /** @var array<string, string> */
    private const DIVIDER_LABELS = [
        'thin' => 'Thin',
        'subtle' => 'Subtle',
        'strong' => 'Strong',
        'dotted' => 'Dotted',
    ];

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'pages/custom/{section}';
    protected static ?string $title = 'Custom Page';
    protected string $view = 'filament.pages.custom-page-workspace';

    public int $sectionId;
    public int $settingsId;
    public string $pageTitle = '';
    public ?string $publicUrl = null;

    /** @var array<string, mixed> */
    public array $analytics = [];

    /** @var array<string, string> */
    public array $componentTypeOptions = self::COMPONENT_LABELS;

    /** @var array<string, string> */
    public array $dividerVariantOptions = self::DIVIDER_LABELS;

    /** @var array<string, string> */
    public array $availableSocialPlatforms = [];

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

        $changed = app(CustomPageEditorialService::class)->moveBlock($this->settings(), $index, $type, $direction);
        $this->selectedComponentTargets = [];
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()->title('Component order updated')->success()->send();
        }
    }

    /** @param list<string> $targets */
    public function reorderComponents(array $targets): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $sequence = [];
        foreach ($targets as $target) {
            if (! is_string($target) || ! str_contains($target, ':')) {
                throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);
            }

            [$index, $type] = explode(':', $target, 2);
            if (! ctype_digit($index) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
                throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);
            }

            $sequence[] = ['index' => (int) $index, 'type' => $type];
        }

        $changed = app(CustomPageEditorialService::class)->reorderBlocks($this->settings(), $sequence);
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

        $changed = app(CustomPageEditorialService::class)->moveSelectedBlocks($this->settings(), $targets, $direction);
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

    public function setContactToggle(int $index, string $type, string $field, bool $enabled): void
    {
        app(CustomPageEditorialService::class)->setContactToggle($this->settings(), $index, $type, $field, $enabled);
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function setContactSocialPlatform(int $index, string $type, string $platform, bool $enabled): void
    {
        if (! array_key_exists($platform, $this->availableSocialPlatforms)) {
            throw ValidationException::withMessages(['component' => 'This social platform is not available from General.']);
        }

        app(CustomPageEditorialService::class)->setContactSocialPlatform($this->settings(), $index, $type, $platform, $enabled);
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function addComponentAction(): Action
    {
        return Action::make('addComponent')
            ->label('Add component')
            ->fillForm(fn (): array => [
                'type' => 'text',
                'image_decorative' => false,
                'variant' => 'thin',
                'form_visibility' => 'visible',
                'status_text' => null,
                'show_email' => true,
                'show_form' => true,
                'social_platforms' => array_keys($this->availableSocialPlatforms),
            ])
            ->schema($this->componentEditorSchema(includeTypeSelect: true))
            ->modalHeading('Add component')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Add component')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                app(CustomPageEditorialService::class)->addBlock($this->settings(), $this->componentPayload($data));
                $this->selectedComponentTargets = [];
                $this->reloadWorkspace();
                Notification::make()->title('Component added')->success()->send();
            });
    }

    public function editComponentAction(): Action
    {
        return Action::make('editComponent')
            ->label('Edit')
            ->fillForm(fn (array $arguments): array => $this->componentEditorData($this->actionComponent($arguments)))
            ->schema($this->componentEditorSchema(includeTypeSelect: false))
            ->modalHeading(function (array $arguments): string {
                $block = $this->actionComponent($arguments);
                return 'Edit '.(self::COMPONENT_LABELS[(string) $block['type']] ?? 'component');
            })
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Save')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $existing = $this->actionComponent($arguments);
                $changed = app(CustomPageEditorialService::class)->updateBlock(
                    $this->settings(),
                    $index,
                    $type,
                    $this->componentPayload($data, $existing),
                );
                $this->selectedComponentTargets = [];
                $this->reloadWorkspace();

                Notification::make()->title($changed ? 'Component saved' : 'No component changes')->success()->send();
            });
    }

    public function changeComponentTypeAction(): Action
    {
        return Action::make('changeComponentType')
            ->label('Change component type')
            ->requiresConfirmation(fn (array $arguments): bool => $this->componentTypeChangeLosesContent($arguments))
            ->modalHeading('Change component type?')
            ->modalDescription(function (array $arguments): string {
                [, $oldType] = $this->actionComponentTarget($arguments);
                $targetType = $this->actionTargetComponentType($arguments);

                return 'Changing '.(self::COMPONENT_LABELS[$oldType] ?? $oldType)
                    .' to '.(self::COMPONENT_LABELS[$targetType] ?? $targetType)
                    .' can remove component-specific content that cannot be carried over.';
            })
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Change type')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                [$index, $oldType] = $this->actionComponentTarget($arguments);
                $targetType = $this->actionTargetComponentType($arguments);
                $changed = app(CustomPageEditorialService::class)->convertBlock($this->settings(), $index, $oldType, $targetType);
                $this->selectedComponentTargets = [];
                $this->reloadWorkspace();

                if ($changed) {
                    Notification::make()->title('Component type updated')->success()->send();
                }
            });
    }

    public function deleteComponentAction(): Action
    {
        return Action::make('deleteComponent')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete component?')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->deleteBlock($this->settings(), $index, $type);
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
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
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

    public function addListEntryAction(): Action
    {
        return Action::make('addListEntry')
            ->label('Add list entry')
            ->fillForm(fn (): array => ['visible' => true])
            ->schema($this->listEntrySchema())
            ->modalHeading('Add list entry')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Add entry')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->addListItem($this->settings(), $index, $type, $this->listItemPayload($data));
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry added')->success()->send();
            });
    }

    public function editListEntryAction(): Action
    {
        return Action::make('editListEntry')
            ->label('Edit')
            ->fillForm(fn (array $arguments): array => $this->actionListItem($arguments))
            ->schema($this->listEntrySchema())
            ->modalHeading(fn (array $arguments): string => 'Edit '.(string) ($this->actionListItem($arguments)['title'] ?? 'list entry'))
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Save')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $itemIndex = $this->actionListItemIndex($arguments);
                app(CustomPageEditorialService::class)->updateListItem(
                    $this->settings(),
                    $index,
                    $type,
                    $itemIndex,
                    $this->listItemPayload($data),
                );
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry saved')->success()->send();
            });
    }

    public function deleteListEntryAction(): Action
    {
        return Action::make('deleteListEntry')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete list entry?')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $itemIndex = $this->actionListItemIndex($arguments);
                app(CustomPageEditorialService::class)->deleteListItem($this->settings(), $index, $type, $itemIndex);
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry deleted')->success()->send();
            });
    }

    public function addCvEntryAction(): Action
    {
        return Action::make('addCvEntry')
            ->label('Add CV entry')
            ->fillForm(fn (): array => ['section' => 'CV', 'date_precision' => 'unknown'])
            ->schema($this->cvEntrySchema())
            ->modalHeading('Add CV entry')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Create draft')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                app(CvEntryEditorialService::class)->createDraft($data);
                $this->loadComponentProjection(refreshCvCount: true);
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
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Save')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                app(CvEntryEditorialService::class)->update($this->actionCvEntry($arguments), $data);
                $this->loadComponentProjection(refreshCvCount: true);
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
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $arguments): void {
                app(EditorialRecordService::class)->deleteCv($this->actionCvEntry($arguments));
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('CV entry deleted')->success()->send();
            });
    }

    public function moveCvEntry(int $entryId, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        $changed = app(EditorialRecordService::class)->move($entry, $direction);
        $this->loadComponentProjection(refreshCvCount: true);

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

        $this->loadComponentProjection(refreshCvCount: true);
        Notification::make()->title('CV entry updated')->success()->send();
    }

    private function loadAvailableSocialPlatforms(): void
    {
        $general = PublicContentSetting::general();
        $this->availableSocialPlatforms = collect(SocialLinks::visible($general->getAttribute('social_links')))
            ->mapWithKeys(static fn (array $link): array => [$link['platform'] => SocialLinks::label($link['platform'])])
            ->all();
    }

    private function loadAnalyticsSnapshot(SiteSection $section): void
    {
        $path = app(SiteNodeRoute::class)->path($section);
        if (! is_string($path) || $path === '') {
            $this->analytics = [];
            return;
        }

        $this->analytics = app(ArtistReportingService::class)->customPage($path, '30d');
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
    }

    private function loadComponentProjection(bool $refreshCvCount): void
    {
        $blocks = $this->settings()->components();
        $this->unfilteredComponentCount = count($blocks);
        $this->hasCvList = collect($blocks)->contains(static fn (array $block): bool => ($block['type'] ?? null) === 'cv_list');

        $cvRecords = collect();
        if ($this->hasCvList) {
            $cvRecords = CvEntry::query()->orderBy('position')->orderBy('id')->get();
            if ($refreshCvCount || $this->cvEntryCount === 0) {
                $this->cvEntryCount = $cvRecords->count();
            }
        } else {
            $this->cvEntryCount = 0;
        }

        $imageIds = collect($blocks)
            ->filter(static fn (array $block): bool => ($block['type'] ?? null) === 'image')
            ->pluck('media_asset_id')
            ->filter(static fn (mixed $id): bool => is_numeric($id))
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()->values()->all();

        $imageNames = $imageIds === [] ? [] : MediaAsset::query()
            ->whereIn('id', $imageIds)
            ->pluck('original_filename', 'id')
            ->mapWithKeys(static fn (mixed $name, mixed $id): array => [(int) $id => (string) $name])
            ->all();

        $counts = array_fill_keys(array_keys(self::COMPONENT_LABELS), 0);
        $listEntryCount = 0;
        $projected = [];
        $needle = mb_strtolower(trim($this->componentSearch));

        foreach ($blocks as $index => $block) {
            $type = is_string($block['type'] ?? null) ? $block['type'] : '';
            if (isset($counts[$type])) {
                $counts[$type]++;
            }
            if ($type === 'list' && is_array($block['items'] ?? null)) {
                $listEntryCount += count($block['items']);
            }
            if ($this->componentType !== 'any' && $type !== $this->componentType) {
                continue;
            }

            $mediaId = is_numeric($block['media_asset_id'] ?? null) ? (int) $block['media_asset_id'] : null;
            $imageName = $mediaId !== null ? ($imageNames[$mediaId] ?? null) : null;
            $children = $this->componentChildren($block, $cvRecords->all());
            $parentSearch = mb_strtolower($this->componentParentSearchText($block, $imageName));

            if ($needle !== '') {
                $parentMatches = str_contains($parentSearch, $needle);
                $matchingChildren = array_values(array_filter(
                    $children,
                    static fn (array $child): bool => str_contains(mb_strtolower((string) ($child['search_text'] ?? '')), $needle),
                ));

                if (! $parentMatches && $matchingChildren === []) {
                    continue;
                }
                if (! $parentMatches) {
                    $children = $matchingChildren;
                }
            }

            $projected[] = [
                'index' => $index,
                'type' => $type,
                'type_label' => self::COMPONENT_LABELS[$type] ?? 'Component',
                'content' => $this->componentContent($block, $imageName),
                'target' => $index.':'.$type,
                'editable' => $type !== 'cv_list',
                'can_move_up' => $index > 0,
                'can_move_down' => $index < count($blocks) - 1,
                'is_cv_list' => $type === 'cv_list',
                'is_list' => $type === 'list',
                'is_contact' => $type === 'contact',
                'children' => $children,
            ];
        }

        $this->components = $projected;
        $entries = $listEntryCount + ($this->hasCvList ? $this->cvEntryCount : 0);
        $this->metrics = [
            ['label' => 'Components', 'value' => number_format(count($blocks)), 'description' => 'Page sequence'],
            ['label' => 'Entries', 'value' => number_format($entries), 'description' => 'CV + list entries'],
            ['label' => 'Images', 'value' => number_format($counts['image']), 'description' => 'Image components'],
            ['label' => 'Visits', 'value' => $this->metricValue($this->analytics['page']['visits'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Views', 'value' => $this->metricValue($this->analytics['page']['views'] ?? null), 'description' => 'This page · 30d'],
            ['label' => 'Contact messages', 'value' => $this->metricValue($this->analytics['contact_messages'] ?? null), 'description' => 'Site-wide successful submissions · 30d'],
        ];
    }

    /**
     * @param array<string, mixed> $block
     * @param list<CvEntry> $cvRecords
     * @return list<array<string, mixed>>
     */
    private function componentChildren(array $block, array $cvRecords): array
    {
        $type = $block['type'] ?? null;

        if ($type === 'cv_list') {
            $count = count($cvRecords);
            return array_values(array_map(function (CvEntry $entry, int $index) use ($count): array {
                $meta = array_values(array_filter([
                    $entry->getAttribute('organisation'),
                    $entry->getAttribute('location'),
                ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));

                $search = implode(' ', array_filter([
                    $entry->getAttribute('year_text'),
                    $entry->getAttribute('title'),
                    $entry->getAttribute('organisation'),
                    $entry->getAttribute('location'),
                    $entry->getAttribute('body'),
                    $entry->getAttribute('state'),
                ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));

                return [
                    'kind' => 'cv',
                    'key' => 'cv-'.(int) $entry->getKey(),
                    'date' => (string) ($entry->getAttribute('year_text') ?? ''),
                    'entry' => (string) $entry->getAttribute('title'),
                    'detail' => implode(' · ', $meta),
                    'status' => ucfirst((string) $entry->getAttribute('state')),
                    'state' => (string) $entry->getAttribute('state'),
                    'entry_id' => (int) $entry->getKey(),
                    'can_move_up' => $this->componentReorderEnabled() && $index > 0,
                    'can_move_down' => $this->componentReorderEnabled() && $index < $count - 1,
                    'search_text' => $search,
                ];
            }, $cvRecords, array_keys($cvRecords)));
        }

        if ($type === 'list') {
            $items = is_array($block['items'] ?? null) ? array_values($block['items']) : [];
            $children = [];
            foreach ($items as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }

                $detail = implode(' · ', array_values(array_filter([
                    is_string($item['meta'] ?? null) ? trim($item['meta']) : '',
                    is_string($item['location'] ?? null) ? trim($item['location']) : '',
                    $this->contentExcerpt($item['body'] ?? null, 110),
                ], static fn (string $value): bool => $value !== '')));

                $children[] = [
                    'kind' => 'list',
                    'key' => 'list-'.$itemIndex,
                    'date' => is_string($item['date'] ?? null) ? $item['date'] : '',
                    'entry' => is_string($item['title'] ?? null) ? $item['title'] : '',
                    'detail' => $detail,
                    'status' => (bool) ($item['visible'] ?? true) ? 'Visible' : 'Hidden',
                    'item_index' => $itemIndex,
                    'search_text' => implode(' ', array_filter([
                        $item['date'] ?? null,
                        $item['title'] ?? null,
                        $item['meta'] ?? null,
                        $item['location'] ?? null,
                        $item['body'] ?? null,
                        $item['url'] ?? null,
                        (bool) ($item['visible'] ?? true) ? 'Visible' : 'Hidden',
                    ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')),
                ];
            }
            return $children;
        }

        if ($type === 'contact') {
            $visible = ($block['form_state'] ?? 'enabled') !== 'hidden';
            $children = [[
                'kind' => 'contact',
                'key' => 'contact-visibility',
                'entry' => 'Form visibility',
                'detail' => '',
                'status' => $visible ? 'Visible' : 'Hidden',
                'action' => 'edit',
                'search_text' => 'Form visibility '.($visible ? 'Visible' : 'Hidden'),
            ]];

            foreach (['show_email' => 'Public email', 'show_form' => 'Contact form'] as $field => $label) {
                $enabled = (bool) ($block[$field] ?? true);
                $children[] = [
                    'kind' => 'contact',
                    'key' => 'contact-'.$field,
                    'entry' => $label,
                    'detail' => 'General',
                    'status' => $enabled ? 'On' : 'Off',
                    'action' => 'toggle',
                    'field' => $field,
                    'enabled' => $enabled,
                    'search_text' => $label.' '.($enabled ? 'On Visible' : 'Off Hidden').' General',
                ];
            }

            $selected = is_array($block['social_platforms'] ?? null)
                ? array_values(array_filter($block['social_platforms'], 'is_string'))
                : [];

            foreach ($this->availableSocialPlatforms as $platform => $label) {
                $enabled = in_array($platform, $selected, true);
                $children[] = [
                    'kind' => 'contact',
                    'key' => 'contact-social-'.$platform,
                    'entry' => $label,
                    'detail' => 'Social link from General',
                    'status' => $enabled ? 'On' : 'Off',
                    'action' => 'social',
                    'platform' => $platform,
                    'enabled' => $enabled,
                    'search_text' => $label.' Social link General '.($enabled ? 'On Visible' : 'Off Hidden'),
                ];
            }

            return $children;
        }

        return [];
    }

    private function componentReorderEnabled(): bool
    {
        return trim($this->componentSearch) === '' && $this->componentType === 'any';
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

    private function actionTargetComponentType(array $arguments): string
    {
        $type = $arguments['targetType'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported component type.']);
        }
        return $type;
    }

    private function componentTypeChangeLosesContent(array $arguments): bool
    {
        return app(CustomPageEditorialService::class)->conversionLosesContent(
            $this->actionComponent($arguments),
            $this->actionTargetComponentType($arguments),
        );
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

    private function actionListItemIndex(array $arguments): int
    {
        $itemIndex = $arguments['itemIndex'] ?? null;
        if (! is_numeric($itemIndex) || (int) $itemIndex < 0) {
            throw ValidationException::withMessages(['component' => 'The selected list entry is invalid.']);
        }
        return (int) $itemIndex;
    }

    /** @return array<string, mixed> */
    private function actionListItem(array $arguments): array
    {
        $block = $this->actionComponent($arguments);
        if (($block['type'] ?? null) !== 'list') {
            throw ValidationException::withMessages(['component' => 'The selected component is not a List.']);
        }
        $itemIndex = $this->actionListItemIndex($arguments);
        $items = is_array($block['items'] ?? null) ? array_values($block['items']) : [];
        $item = $items[$itemIndex] ?? null;
        if (! is_array($item)) {
            throw ValidationException::withMessages(['component' => 'This list entry changed. Reload and try again.']);
        }
        return $item;
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
                ->afterStateUpdated(fn (Select $component) => $component
                    ->getContainer()
                    ->getComponent('dynamicComponentFields')
                    ->getChildSchema()
                    ->fill())
            : Hidden::make('type')->required();

        return [
            $typeField,
            Grid::make(1)
                ->schema(fn (Get $get): array => $this->componentTypeFields((string) $get('type')))
                ->key('dynamicComponentFields'),
        ];
    }

    /** @return list<mixed> */
    private function componentTypeFields(string $type): array
    {
        return match ($type) {
            'image' => [
                MediaAssetSelect::forId('media_asset_id', 'Image from Media Files', imagesOnly: true)->required(),
                Toggle::make('image_decorative')->label('Decorative image')->default(false),
            ],
            'text' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
                ...AdminRichText::schema('body', 'Text', 20000),
            ],
            'list' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
            ],
            'divider' => [
                Select::make('variant')->label('Divider')->options(self::DIVIDER_LABELS)->default('thin')->required(),
            ],
            'contact' => [
                Select::make('form_visibility')->label('Form visibility')->options([
                    'visible' => 'Visible',
                    'hidden' => 'Hidden',
                ])->default('visible')->required(),
                Hidden::make('status_text'),
                Toggle::make('show_email')->label('Show public email from General')->default(true),
                Toggle::make('show_form')->label('Show contact form')->default(true),
                Select::make('social_platforms')
                    ->label('Social links from General')
                    ->options($this->availableSocialPlatforms)
                    ->multiple()
                    ->default(array_keys($this->availableSocialPlatforms)),
            ],
            default => [],
        };
    }

    /** @return list<mixed> */
    private function listEntrySchema(): array
    {
        return [
            Toggle::make('visible')->label('Visible on public page')->default(true),
            TextInput::make('date')->label('Date / year')->maxLength(120),
            TextInput::make('title')->label('Entry')->required()->maxLength(240),
            TextInput::make('meta')->label('Organisation / context')->maxLength(240),
            TextInput::make('location')->maxLength(240),
            TextInput::make('url')->label('Optional link')->url()->maxLength(2048),
            ...AdminRichText::schema('body', 'Details', 10000),
        ];
    }

    /** @return list<mixed> */
    private function cvEntrySchema(): array
    {
        return [
            Hidden::make('section')->default('CV')->required(),
            TextInput::make('title')->label('Entry')->required()->maxLength(240),
            TextInput::make('year_text')->label('Displayed date / year')->required()->maxLength(80),
            Select::make('date_precision')->options([
                'unknown' => 'Unknown',
                'year' => 'Year',
                'month' => 'Month',
                'day' => 'Day',
            ])->required()->default('unknown'),
            DatePicker::make('starts_on')->nullable(),
            DatePicker::make('ends_on')->nullable(),
            TextInput::make('organisation')->maxLength(240)->nullable(),
            TextInput::make('location')->maxLength(240)->nullable(),
            ...AdminRichText::schema('body', 'Details', 10000),
            TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
        ];
    }

    /** @param array<string, mixed> $block */
    private function componentEditorData(array $block): array
    {
        if (($block['type'] ?? null) === 'divider') {
            return [
                ...$block,
                'variant' => in_array($block['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true)
                    ? $block['variant']
                    : 'thin',
            ];
        }
        if (($block['type'] ?? null) !== 'contact') {
            return $block;
        }
        return [
            ...$block,
            'form_visibility' => ($block['form_state'] ?? 'enabled') === 'hidden' ? 'hidden' : 'visible',
            'status_text' => $block['status_text'] ?? null,
        ];
    }

    /**
     * @param array<string, mixed> $data
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function componentPayload(array $data, ?array $existing = null): array
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
            'text' => ['type' => 'text', 'title' => $data['title'] ?? null, 'body' => $data['body'] ?? null],
            'list' => [
                'type' => 'list',
                'title' => $data['title'] ?? null,
                'items' => is_array($existing['items'] ?? null) ? array_values($existing['items']) : [],
            ],
            'divider' => [
                'type' => 'divider',
                'variant' => in_array($data['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true) ? $data['variant'] : 'thin',
            ],
            'contact' => [
                'type' => 'contact',
                'form_state' => ($data['form_visibility'] ?? 'visible') === 'hidden' ? 'hidden' : 'enabled',
                'status_text' => $data['status_text'] ?? ($existing['status_text'] ?? null),
                'show_email' => (bool) ($data['show_email'] ?? true),
                'show_form' => (bool) ($data['show_form'] ?? true),
                'social_platforms' => is_array($data['social_platforms'] ?? null) ? array_values($data['social_platforms']) : [],
            ],
        };
    }

    /** @param array<string, mixed> $data */
    private function listItemPayload(array $data): array
    {
        return [
            'visible' => (bool) ($data['visible'] ?? true),
            'date' => $data['date'] ?? null,
            'title' => $data['title'] ?? null,
            'meta' => $data['meta'] ?? null,
            'location' => $data['location'] ?? null,
            'url' => $data['url'] ?? null,
            'body' => $data['body'] ?? null,
        ];
    }

    /** @param array<string, mixed> $block @return array{primary:string,secondary:string,meta:string} */
    private function componentContent(array $block, ?string $imageName): array
    {
        $type = $block['type'] ?? null;
        if ($type === 'image') {
            return ['primary' => $imageName ?: 'Image unavailable', 'secondary' => '', 'meta' => ''];
        }
        if ($type === 'text') {
            $title = is_string($block['title'] ?? null) ? trim($block['title']) : '';
            $body = $this->contentExcerpt($block['body'] ?? null);
            return ['primary' => $title !== '' ? $title : $body, 'secondary' => $title !== '' ? $body : '', 'meta' => ''];
        }
        if ($type === 'list') {
            $title = is_string($block['title'] ?? null) ? trim($block['title']) : '';
            $items = is_array($block['items'] ?? null) ? array_values(array_filter($block['items'], 'is_array')) : [];
            return ['primary' => $title, 'secondary' => '', 'meta' => count($items).' '.(count($items) === 1 ? 'entry' : 'entries')];
        }
        if ($type === 'cv_list') {
            return ['primary' => '', 'secondary' => '', 'meta' => $this->cvEntryCount.' '.($this->cvEntryCount === 1 ? 'entry' : 'entries')];
        }
        if ($type === 'divider') {
            $variant = is_string($block['variant'] ?? null) ? $block['variant'] : 'thin';
            return ['primary' => self::DIVIDER_LABELS[$variant] ?? self::DIVIDER_LABELS['thin'], 'secondary' => '', 'meta' => ''];
        }
        if ($type === 'contact') {
            $selected = is_array($block['social_platforms'] ?? null) ? count($block['social_platforms']) : 0;
            return ['primary' => 'Contact settings', 'secondary' => '', 'meta' => $selected.' social '.($selected === 1 ? 'link' : 'links')];
        }
        return ['primary' => '', 'secondary' => '', 'meta' => ''];
    }

    private function contentExcerpt(mixed $value, int $limit = 170): string
    {
        if (! is_string($value)) {
            return '';
        }
        $value = preg_replace('/!\[\]\(media:\d+\)/', '[Image]', $value) ?? $value;
        $text = preg_replace('/\s+/u', ' ', trim(strip_tags($value))) ?? '';
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, max(1, $limit - 1))).'…';
    }

    /** @param array<string, mixed> $block */
    private function componentParentSearchText(array $block, ?string $imageName): string
    {
        $type = is_string($block['type'] ?? null) ? $block['type'] : '';
        $parts = [self::COMPONENT_LABELS[$type] ?? $type];
        if ($type === 'image') {
            $parts[] = $imageName;
        } elseif ($type === 'text') {
            $parts[] = $block['title'] ?? null;
            $parts[] = $block['body'] ?? null;
        } elseif ($type === 'list') {
            $parts[] = $block['title'] ?? null;
        } elseif ($type === 'divider') {
            $variant = is_string($block['variant'] ?? null) ? $block['variant'] : 'thin';
            $parts[] = self::DIVIDER_LABELS[$variant] ?? $variant;
        }

        return implode(' ', array_filter($parts, static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));
    }

    private function metricValue(mixed $metric): string
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return '—';
        }
        $value = (float) $metric['value'];
        return number_format($value, $value === floor($value) ? 0 : 1);
    }

    private function settings(): CustomPageSetting
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->findOrFail($this->settingsId);
        return $settings;
    }
}
