<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Admin\CvEntryEditorialService;
use App\Domain\Admin\EditorialRecordService;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Content\CustomPageEditorialService;
use App\Domain\Content\SiteNodeType;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SiteSectionEditorialService;
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
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
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
        'legal_disclaimer' => 'Legal Disclaimer',
    ];

    /** @var array<string, string> */
    private const CONTACT_CHILD_LABELS = [
        'public_email' => 'Public Email',
        'social_links' => 'Social Media Links',
        'contact_form' => 'Contact Form',
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
    public ?string $previewUrl = null;

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

    /** @var list<string> */
    public array $selectedChildTargets = [];

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
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function updatedComponentType(): void
    {
        if ($this->componentType !== 'any' && ! array_key_exists($this->componentType, self::COMPONENT_LABELS)) {
            $this->componentType = 'any';
        }

        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function resetComponentFilters(): void
    {
        $this->componentSearch = '';
        $this->componentType = 'any';
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function pageSettingsAction(): Action
    {
        return Action::make('pageSettings')
            ->label('Settings')
            ->fillForm(function (): array {
                $section = $this->section();

                return [
                    'publication_state' => (string) $section->getAttribute('state') === 'published' ? 'published' : 'unpublished',
                    'show_in_navigation' => (bool) $section->getAttribute('show_in_navigation'),
                    'parent_id' => $section->getAttribute('parent_id'),
                ];
            })
            ->schema([
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
            ->modalSubmitActionLabel('Save')
            ->action(function (array $data): void {
                app(SiteSectionEditorialService::class)->updatePlacement(
                    $this->section(),
                    ($data['publication_state'] ?? 'unpublished') === 'published' ? 'published' : 'hidden',
                    (bool) ($data['show_in_navigation'] ?? false),
                    is_numeric($data['parent_id'] ?? null) ? (int) $data['parent_id'] : null,
                );
                $this->reloadWorkspace();
                Notification::make()->title('Page settings saved')->success()->send();
            });
    }

    public function moveComponent(int $index, string $type, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $changed = app(CustomPageEditorialService::class)->moveBlock($this->settings(), $index, $type, $direction);
        $this->clearSelections();
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
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()->title('Component order updated')->success()->send();
        }
    }

    public function sortComponent(string $target, int $position): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $targets = collect($this->components)
            ->pluck('target')
            ->filter(static fn (mixed $value): bool => is_string($value))
            ->values()
            ->all();

        $from = array_search($target, $targets, true);
        if ($from === false) {
            throw ValidationException::withMessages(['component' => 'The component sequence is invalid.']);
        }

        $moved = $targets[$from];
        array_splice($targets, $from, 1);
        $position = max(0, min($position, count($targets)));
        array_splice($targets, $position, 0, [$moved]);

        $this->reorderComponents($targets);
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
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()
                ->title('Selected components moved')
                ->body($count.' component'.($count === 1 ? '' : 's').' updated.')
                ->success()
                ->send();
        }
    }

    public function setComponentPublished(int $index, string $type, bool $published): void
    {
        $block = $this->componentAt($index, $type);
        $block['published'] = $published;
        app(CustomPageEditorialService::class)->updateBlock($this->settings(), $index, $type, $block);
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function addComponentAction(): Action
    {
        return Action::make('addComponent')
            ->label('Add component')
            ->fillForm(fn (): array => [
                'type' => 'text',
                'publication_state' => 'published',
                'image_decorative' => false,
                'variant' => 'thin',
                'legal_disclaimer' => PublicContentSetting::general()->getAttribute('legal_disclaimer'),
            ])
            ->schema($this->componentEditorSchema(includeTypeSelect: true))
            ->modalHeading('Add component')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Add component')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                app(CustomPageEditorialService::class)->addBlock($this->settings(), $this->componentPayload($data));
                $this->syncLegalDisclaimerIfNeeded($data);
                $this->clearSelections();
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
                $this->syncLegalDisclaimerIfNeeded($data);
                $this->clearSelections();
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
                $this->clearSelections();
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
                $this->clearSelections();
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
                $this->clearSelections();
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
            ->fillForm(fn (): array => ['publication_state' => 'published'])
            ->schema($this->listEntrySchema())
            ->modalHeading('Add list entry')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Add entry')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->addListItem($this->settings(), $index, $type, $this->listItemPayload($data));
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry added')->success()->send();
            });
    }

    public function editListEntryAction(): Action
    {
        return Action::make('editListEntry')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $item = $this->actionListItem($arguments);
                return [...$item, 'publication_state' => CustomPageSetting::listItemPublished($item) ? 'published' : 'unpublished'];
            })
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
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry saved')->success()->send();
            });
    }

    public function setListEntryPublished(int $componentIndex, string $componentType, int $itemIndex, bool $published): void
    {
        app(CustomPageEditorialService::class)->setListItemPublished(
            $this->settings(),
            $componentIndex,
            $componentType,
            $itemIndex,
            $published,
        );
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function moveListEntry(int $componentIndex, string $componentType, int $itemIndex, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        app(CustomPageEditorialService::class)->moveListItem(
            $this->settings(),
            $componentIndex,
            $componentType,
            $itemIndex,
            $direction,
        );
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
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
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('List entry deleted')->success()->send();
            });
    }

    public function addContactChildAction(): Action
    {
        return Action::make('addContactChild')
            ->label('Add child')
            ->fillForm(fn (): array => [
                'child_type' => 'public_email',
                'publication_state' => 'published',
                'social_platforms' => array_keys($this->availableSocialPlatforms),
                'form_state' => 'enabled',
                'status_text' => null,
            ])
            ->schema(fn (array $arguments): array => $this->contactChildEditorSchema(null, includeTypeSelect: true, arguments: $arguments))
            ->modalHeading('Add Contact child')
            ->modalSubmitActionLabel('Add child')
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->addContactChild(
                    $this->settings(),
                    $index,
                    $type,
                    $this->contactChildPayload($data),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('Contact child added')->success()->send();
            });
    }

    public function editContactChildAction(): Action
    {
        return Action::make('editContactChild')
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $child = $this->actionContactChild($arguments);
                return [
                    ...$child,
                    'child_type' => $child['type'],
                    'publication_state' => CustomPageSetting::contactChildPublished($child) ? 'published' : 'unpublished',
                ];
            })
            ->schema(fn (array $arguments): array => $this->contactChildEditorSchema($this->actionContactChildType($arguments), false, $arguments))
            ->modalHeading(fn (array $arguments): string => 'Edit '.(self::CONTACT_CHILD_LABELS[$this->actionContactChildType($arguments)] ?? 'Contact child'))
            ->modalSubmitActionLabel('Save')
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $childType = $this->actionContactChildType($arguments);
                app(CustomPageEditorialService::class)->updateContactChild(
                    $this->settings(),
                    $index,
                    $type,
                    $childType,
                    $this->contactChildPayload([...$data, 'child_type' => $childType]),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('Contact child saved')->success()->send();
            });
    }

    public function setContactChildPublished(int $index, string $type, string $childType, bool $published): void
    {
        app(CustomPageEditorialService::class)->setContactChildPublished(
            $this->settings(),
            $index,
            $type,
            $childType,
            $published,
        );
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function moveContactChild(int $index, string $type, string $childType, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        app(CustomPageEditorialService::class)->moveContactChild(
            $this->settings(),
            $index,
            $type,
            $childType,
            $direction,
        );
        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function deleteContactChildAction(): Action
    {
        return Action::make('deleteContactChild')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete Contact child?')
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->deleteContactChild(
                    $this->settings(),
                    $index,
                    $type,
                    $this->actionContactChildType($arguments),
                );
                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: false);
                Notification::make()->title('Contact child deleted')->success()->send();
            });
    }

    public function addCvEntryAction(): Action
    {
        return Action::make('addCvEntry')
            ->label('Add CV entry')
            ->fillForm(fn (): array => ['section' => 'CV', 'date_precision' => 'unknown', 'image_media_asset_id' => null])
            ->schema($this->cvEntrySchema())
            ->modalHeading('Add CV entry')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Create draft')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                app(CvEntryEditorialService::class)->createDraft($data);
                $this->clearSelections();
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
                    'image_media_asset_id' => $entry->getAttribute('image_media_asset_id'),
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
                $this->clearSelections();
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
                $this->clearSelections();
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
        $this->clearSelections();
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

        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: true);
        Notification::make()->title('CV entry updated')->success()->send();
    }

    public function sortChild(string $target, int $position): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $parts = explode(':', $target);
        $kind = $parts[0] ?? null;
        if ($kind === 'cv' && isset($parts[1]) && ctype_digit($parts[1])) {
            /** @var CvEntry $entry */
            $entry = CvEntry::query()->findOrFail((int) $parts[1]);
            app(EditorialRecordService::class)->sortCv($entry, $position);
            $this->clearSelections();
            $this->loadComponentProjection(refreshCvCount: true);
            return;
        }

        if ($kind === 'list' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
            app(CustomPageEditorialService::class)->sortListItem(
                $this->settings(),
                (int) $parts[1],
                'list',
                (int) $parts[2],
                $position,
            );
            $this->clearSelections();
            $this->loadComponentProjection(refreshCvCount: false);
            return;
        }

        if ($kind === 'contact' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && array_key_exists($parts[2], self::CONTACT_CHILD_LABELS)) {
            app(CustomPageEditorialService::class)->sortContactChild(
                $this->settings(),
                (int) $parts[1],
                'contact',
                $parts[2],
                $position,
            );
            $this->clearSelections();
            $this->loadComponentProjection(refreshCvCount: false);
            return;
        }

        throw ValidationException::withMessages(['component' => 'The child sequence is invalid.']);
    }

    public function publishSelectedChildren(): void
    {
        $this->setSelectedChildrenPublished(true);
    }

    public function unpublishSelectedChildren(): void
    {
        $this->setSelectedChildrenPublished(false);
    }

    public function deleteSelectedChildrenAction(): Action
    {
        return Action::make('deleteSelectedChildren')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected entries?')
            ->action(function (): void {
                $targets = $this->selectedChildTargetData();
                $listGroups = [];
                $contactGroups = [];
                $cvIds = [];

                foreach ($targets as $target) {
                    if ($target['kind'] === 'list') {
                        $listGroups[$target['component_index']][] = $target['item_index'];
                    } elseif ($target['kind'] === 'contact') {
                        $contactGroups[$target['component_index']][] = $target['child_type'];
                    } elseif ($target['kind'] === 'cv') {
                        $cvIds[] = $target['entry_id'];
                    }
                }

                foreach ($listGroups as $componentIndex => $indices) {
                    app(CustomPageEditorialService::class)->deleteListItems($this->settings(), (int) $componentIndex, 'list', $indices);
                }
                foreach ($contactGroups as $componentIndex => $types) {
                    app(CustomPageEditorialService::class)->deleteContactChildren($this->settings(), (int) $componentIndex, 'contact', $types);
                }
                foreach (array_values(array_unique($cvIds)) as $entryId) {
                    /** @var CvEntry|null $entry */
                    $entry = CvEntry::query()->find($entryId);
                    if ($entry instanceof CvEntry) {
                        app(EditorialRecordService::class)->deleteCv($entry);
                    }
                }

                $this->clearSelections();
                $this->loadComponentProjection(refreshCvCount: true);
                Notification::make()->title('Selected entries deleted')->success()->send();
            });
    }

    private function setSelectedChildrenPublished(bool $published): void
    {
        $targets = $this->selectedChildTargetData();
        $listGroups = [];
        $contactGroups = [];
        $cvIds = [];

        foreach ($targets as $target) {
            if ($target['kind'] === 'list') {
                $listGroups[$target['component_index']][] = $target['item_index'];
            } elseif ($target['kind'] === 'contact') {
                $contactGroups[$target['component_index']][] = $target['child_type'];
            } elseif ($target['kind'] === 'cv') {
                $cvIds[] = $target['entry_id'];
            }
        }

        foreach ($listGroups as $componentIndex => $indices) {
            app(CustomPageEditorialService::class)->setListItemsPublished($this->settings(), (int) $componentIndex, 'list', $indices, $published);
        }
        foreach ($contactGroups as $componentIndex => $types) {
            app(CustomPageEditorialService::class)->setContactChildrenPublished($this->settings(), (int) $componentIndex, 'contact', $types, $published);
        }

        $records = EditorialRecordService::class;
        foreach (array_values(array_unique($cvIds)) as $entryId) {
            /** @var CvEntry|null $entry */
            $entry = CvEntry::query()->find($entryId);
            if (! $entry instanceof CvEntry) {
                continue;
            }
            $state = (string) $entry->getAttribute('state');
            if ($published && $state === 'draft') {
                app($records)->publish($entry);
            } elseif (! $published && $state === 'published') {
                app($records)->unpublish($entry);
            }
        }

        $this->clearSelections();
        $this->loadComponentProjection(refreshCvCount: true);
        Notification::make()->title($published ? 'Selected entries published' : 'Selected entries unpublished')->success()->send();
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
        $section = $this->section();
        $this->pageTitle = (string) ($section->getAttribute('title') ?: $section->getAttribute('navigation_label') ?: 'Custom Page');
        $this->publicUrl = (string) $section->getAttribute('state') === 'published'
            ? app(SiteNodeRoute::class)->url($section)
            : null;
        $this->previewUrl = app(SitePreviewContext::class)->previewUrlFor($section);

        $this->loadComponentProjection(refreshCvCount: true);
    }

    private function loadComponentProjection(bool $refreshCvCount): void
    {
        $settings = $this->settings();
        $blocks = $settings->components();
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
            $children = $this->componentChildren($settings, $block, $cvRecords->all(), $index);
            $parentSearch = mb_strtolower($this->componentParentSearchText($settings, $block, $imageName));

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
                'content' => $this->componentContent($settings, $block, $imageName),
                'status' => CustomPageSetting::componentPublished($block) ? 'Published' : 'Unpublished',
                'published' => CustomPageSetting::componentPublished($block),
                'target' => $index.':'.$type,
                'editable' => true,
                'can_move_up' => $index > 0,
                'can_move_down' => $index < count($blocks) - 1,
                'is_cv_list' => $type === 'cv_list',
                'is_list' => $type === 'list',
                'is_contact' => $type === 'contact',
                'contact_child_count' => $type === 'contact' ? count($settings->contactChildren($block)) : 0,
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

    /** @param list<CvEntry> $cvRecords
     *  @return list<array<string, mixed>>
     */
    private function componentChildren(CustomPageSetting $settings, array $block, array $cvRecords, int $componentIndex): array
    {
        $type = $block['type'] ?? null;

        if ($type === 'cv_list') {
            $count = count($cvRecords);
            return array_values(array_map(function (CvEntry $entry, int $index) use ($count): array {
                $meta = array_values(array_filter([
                    $entry->getAttribute('organisation'),
                    $entry->getAttribute('location'),
                ], static fn (mixed $value): bool => is_string($value) && trim($value) !== ''));
                $state = (string) $entry->getAttribute('state');
                $status = match ($state) {
                    'published' => 'Published',
                    'archived', 'hidden' => 'Archived',
                    default => 'Draft',
                };

                return [
                    'kind' => 'cv',
                    'key' => 'cv-'.(int) $entry->getKey(),
                    'target' => 'cv:'.(int) $entry->getKey(),
                    'date' => (string) ($entry->getAttribute('year_text') ?? ''),
                    'entry' => (string) $entry->getAttribute('title'),
                    'detail' => implode(' · ', $meta),
                    'status' => $status,
                    'state' => $state,
                    'published' => $state === 'published',
                    'entry_id' => (int) $entry->getKey(),
                    'can_move_up' => $this->componentReorderEnabled() && $index > 0,
                    'can_move_down' => $this->componentReorderEnabled() && $index < $count - 1,
                    'search_text' => implode(' ', array_filter([
                        $entry->getAttribute('year_text'),
                        $entry->getAttribute('title'),
                        $entry->getAttribute('organisation'),
                        $entry->getAttribute('location'),
                        $entry->getAttribute('body'),
                        $status,
                    ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')),
                ];
            }, $cvRecords, array_keys($cvRecords)));
        }

        if ($type === 'list') {
            $items = is_array($block['items'] ?? null) ? array_values($block['items']) : [];
            $children = [];
            $count = count($items);
            foreach ($items as $itemIndex => $item) {
                if (! is_array($item)) {
                    continue;
                }
                $published = CustomPageSetting::listItemPublished($item);
                $detail = implode(' · ', array_values(array_filter([
                    is_string($item['meta'] ?? null) ? trim($item['meta']) : '',
                    is_string($item['location'] ?? null) ? trim($item['location']) : '',
                    $this->contentExcerpt($item['body'] ?? null, 110),
                ], static fn (string $value): bool => $value !== '')));

                $children[] = [
                    'kind' => 'list',
                    'key' => 'list-'.$itemIndex,
                    'target' => 'list:'.$componentIndex.':'.$itemIndex,
                    'date' => is_string($item['date'] ?? null) ? $item['date'] : '',
                    'entry' => is_string($item['title'] ?? null) ? $item['title'] : '',
                    'detail' => $detail,
                    'status' => $published ? 'Published' : 'Unpublished',
                    'published' => $published,
                    'item_index' => $itemIndex,
                    'can_move_up' => $this->componentReorderEnabled() && $itemIndex > 0,
                    'can_move_down' => $this->componentReorderEnabled() && $itemIndex < $count - 1,
                    'search_text' => implode(' ', array_filter([
                        $item['date'] ?? null,
                        $item['title'] ?? null,
                        $item['meta'] ?? null,
                        $item['location'] ?? null,
                        $item['body'] ?? null,
                        $item['url'] ?? null,
                        $published ? 'Published' : 'Unpublished',
                    ], static fn (mixed $value): bool => is_string($value) && trim($value) !== '')),
                ];
            }
            return $children;
        }

        if ($type === 'contact') {
            $children = [];
            $contactChildren = $settings->contactChildren($block);
            $count = count($contactChildren);
            foreach ($contactChildren as $childIndex => $child) {
                if (! is_array($child)) {
                    continue;
                }
                $childType = is_string($child['type'] ?? null) ? $child['type'] : '';
                if (! array_key_exists($childType, self::CONTACT_CHILD_LABELS)) {
                    continue;
                }
                $published = CustomPageSetting::contactChildPublished($child);
                $detail = match ($childType) {
                    'public_email' => 'Canonical email from General',
                    'social_links' => $this->socialChildDetail($child),
                    'contact_form' => ($child['form_state'] ?? 'enabled') === 'under_construction'
                        ? 'Under construction'
                        : 'Contact form',
                    default => '',
                };
                $children[] = [
                    'kind' => 'contact',
                    'key' => 'contact-'.$childType,
                    'target' => 'contact:'.$componentIndex.':'.$childType,
                    'child_type' => $childType,
                    'date' => '',
                    'entry' => self::CONTACT_CHILD_LABELS[$childType],
                    'detail' => $detail,
                    'status' => $published ? 'Published' : 'Unpublished',
                    'published' => $published,
                    'can_move_up' => $this->componentReorderEnabled() && $childIndex > 0,
                    'can_move_down' => $this->componentReorderEnabled() && $childIndex < $count - 1,
                    'search_text' => self::CONTACT_CHILD_LABELS[$childType].' '.$detail.' '.($published ? 'Published' : 'Unpublished'),
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

    /** @return list<array<string,mixed>> */
    private function selectedChildTargetData(): array
    {
        $targets = [];
        foreach (array_values(array_unique($this->selectedChildTargets)) as $target) {
            if (! is_string($target)) {
                continue;
            }
            $parts = explode(':', $target);
            if (($parts[0] ?? null) === 'cv' && isset($parts[1]) && ctype_digit($parts[1])) {
                $targets[] = ['kind' => 'cv', 'entry_id' => (int) $parts[1]];
            } elseif (($parts[0] ?? null) === 'list' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && ctype_digit($parts[2])) {
                $targets[] = ['kind' => 'list', 'component_index' => (int) $parts[1], 'item_index' => (int) $parts[2]];
            } elseif (($parts[0] ?? null) === 'contact' && isset($parts[1], $parts[2]) && ctype_digit($parts[1]) && array_key_exists($parts[2], self::CONTACT_CHILD_LABELS)) {
                $targets[] = ['kind' => 'contact', 'component_index' => (int) $parts[1], 'child_type' => $parts[2]];
            }
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
        return $this->componentAt($index, $type);
    }

    /** @return array<string,mixed> */
    private function componentAt(int $index, string $type): array
    {
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

    private function actionContactChildType(array $arguments): string
    {
        $childType = $arguments['childType'] ?? null;
        if (! is_string($childType) || ! array_key_exists($childType, self::CONTACT_CHILD_LABELS)) {
            throw ValidationException::withMessages(['component' => 'The selected Contact child is invalid.']);
        }

        return $childType;
    }

    /** @return array<string,mixed> */
    private function actionContactChild(array $arguments): array
    {
        $block = $this->actionComponent($arguments);
        if (($block['type'] ?? null) !== 'contact') {
            throw ValidationException::withMessages(['component' => 'The selected component is not Contact.']);
        }
        $childType = $this->actionContactChildType($arguments);
        foreach ($this->settings()->contactChildren($block) as $child) {
            if (is_array($child) && ($child['type'] ?? null) === $childType) {
                return $child;
            }
        }

        throw ValidationException::withMessages(['component' => 'This Contact child changed. Reload and try again.']);
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
            Select::make('publication_state')
                ->label('Status')
                ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                ->default('published')
                ->required(),
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
                MediaAssetSelect::makeId('media_asset_id', 'Image from Media Files', imagesOnly: true)->required(),
                Toggle::make('image_decorative')->label('Decorative image')->default(false),
            ],
            'text' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
                ...AdminRichText::schema('body', 'Text', 20000),
            ],
            'list' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
            ],
            'cv_list' => [
                Placeholder::make('cv_list_note')
                    ->label('CV entries')
                    ->content('This component renders the canonical CV entry sequence. Entry content and lifecycle are managed in the child rows below.'),
            ],
            'divider' => [
                Select::make('variant')->label('Divider')->options(self::DIVIDER_LABELS)->default('thin')->required(),
            ],
            'contact' => [
                Placeholder::make('contact_note')
                    ->label('Contact children')
                    ->content('Public Email, Social Media Links and Contact Form are managed as ordered child components below this row.'),
            ],
            'legal_disclaimer' => [
                Textarea::make('legal_disclaimer')
                    ->label('Legal disclaimer from General')
                    ->rows(6)
                    ->maxLength(5000)
                    ->helperText('This edits the same canonical General setting. The page component stores no copied disclaimer text.'),
            ],
            default => [],
        };
    }

    /** @return list<mixed> */
    private function listEntrySchema(): array
    {
        return [
            Select::make('publication_state')
                ->label('Status')
                ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                ->default('published')
                ->required(),
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
            MediaAssetSelect::makeId('image_media_asset_id', 'Image from Media Files', imagesOnly: true)->nullable(),
            TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
        ];
    }

    /** @return list<mixed> */
    private function contactChildEditorSchema(?string $childType, bool $includeTypeSelect, array $arguments): array
    {
        $fields = [];
        if ($includeTypeSelect) {
            $fields[] = Select::make('child_type')
                ->label('Contact child')
                ->options($this->availableContactChildOptions($arguments))
                ->required()
                ->live();
        } else {
            $fields[] = Hidden::make('child_type')->default($childType)->required();
        }

        $fields[] = Select::make('publication_state')
            ->label('Status')
            ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
            ->default('published')
            ->required();

        $fields[] = Grid::make(1)->schema(function (Get $get) use ($childType): array {
            $type = $childType ?? (string) $get('child_type');
            return match ($type) {
                'public_email' => [
                    Placeholder::make('public_email_note')
                        ->label('Public Email')
                        ->content('Uses the canonical public email configured in General.'),
                ],
                'social_links' => [
                    Select::make('social_platforms')
                        ->label('Social links from General')
                        ->options($this->availableSocialPlatforms)
                        ->multiple()
                        ->default(array_keys($this->availableSocialPlatforms)),
                ],
                'contact_form' => [
                    Select::make('form_state')
                        ->label('Form presentation')
                        ->options(['enabled' => 'Enabled', 'under_construction' => 'Under construction'])
                        ->default('enabled')
                        ->required()
                        ->live(),
                    TextInput::make('status_text')
                        ->label('Status text')
                        ->maxLength(500)
                        ->required(fn (Get $get): bool => $get('form_state') === 'under_construction')
                        ->visible(fn (Get $get): bool => $get('form_state') === 'under_construction'),
                ],
                default => [],
            };
        });

        return $fields;
    }

    /** @param array<string,mixed> $block */
    private function componentEditorData(array $block): array
    {
        $data = [
            ...$block,
            'publication_state' => CustomPageSetting::componentPublished($block) ? 'published' : 'unpublished',
        ];
        if (($block['type'] ?? null) === 'divider') {
            $data['variant'] = in_array($block['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true)
                ? $block['variant']
                : 'thin';
        }
        if (($block['type'] ?? null) === 'legal_disclaimer') {
            $data['legal_disclaimer'] = PublicContentSetting::general()->getAttribute('legal_disclaimer');
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function componentPayload(array $data, ?array $existing = null): array
    {
        $type = $data['type'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported component type.']);
        }
        $published = ($data['publication_state'] ?? 'published') === 'published';

        return match ($type) {
            'image' => [
                'type' => 'image',
                'published' => $published,
                'media_asset_id' => is_numeric($data['media_asset_id'] ?? null) ? (int) $data['media_asset_id'] : null,
                'image_decorative' => (bool) ($data['image_decorative'] ?? false),
            ],
            'cv_list' => ['type' => 'cv_list', 'published' => $published],
            'text' => ['type' => 'text', 'published' => $published, 'title' => $data['title'] ?? null, 'body' => $data['body'] ?? null],
            'list' => [
                'type' => 'list',
                'published' => $published,
                'title' => $data['title'] ?? null,
                'items' => is_array($existing['items'] ?? null) ? array_values($existing['items']) : [],
            ],
            'divider' => [
                'type' => 'divider',
                'published' => $published,
                'variant' => in_array($data['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true) ? $data['variant'] : 'thin',
            ],
            'contact' => [
                'type' => 'contact',
                'published' => $published,
                'children' => $existing !== null
                    ? $this->settings()->contactChildren($existing)
                    : [
                        ['type' => 'public_email', 'published' => true],
                        ['type' => 'social_links', 'published' => true, 'social_platforms' => array_keys($this->availableSocialPlatforms)],
                        ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null],
                    ],
            ],
            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
        };
    }

    /** @return array<string,mixed> */
    private function listItemPayload(array $data): array
    {
        return [
            'published' => ($data['publication_state'] ?? 'published') === 'published',
            'date' => $data['date'] ?? null,
            'title' => $data['title'] ?? null,
            'meta' => $data['meta'] ?? null,
            'location' => $data['location'] ?? null,
            'url' => $data['url'] ?? null,
            'body' => $data['body'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function contactChildPayload(array $data): array
    {
        $type = $data['child_type'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::CONTACT_CHILD_LABELS)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported Contact child component.']);
        }
        $published = ($data['publication_state'] ?? 'published') === 'published';

        return match ($type) {
            'public_email' => ['type' => 'public_email', 'published' => $published],
            'social_links' => [
                'type' => 'social_links',
                'published' => $published,
                'social_platforms' => $this->validatedSocialPlatforms($data['social_platforms'] ?? []),
            ],
            'contact_form' => [
                'type' => 'contact_form',
                'published' => $published,
                'form_state' => ($data['form_state'] ?? 'enabled') === 'under_construction' ? 'under_construction' : 'enabled',
                'status_text' => filled($data['status_text'] ?? null) ? trim((string) $data['status_text']) : null,
            ],
        };
    }

    /** @param array<string,mixed> $block
     *  @return array{primary:string,secondary:string,meta:string}
     */
    private function componentContent(CustomPageSetting $settings, array $block, ?string $imageName): array
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
            return ['primary' => 'Canonical CV entries', 'secondary' => '', 'meta' => $this->cvEntryCount.' '.($this->cvEntryCount === 1 ? 'entry' : 'entries')];
        }
        if ($type === 'divider') {
            $variant = is_string($block['variant'] ?? null) ? $block['variant'] : 'thin';
            return ['primary' => self::DIVIDER_LABELS[$variant] ?? self::DIVIDER_LABELS['thin'], 'secondary' => '', 'meta' => ''];
        }
        if ($type === 'contact') {
            $count = count($settings->contactChildren($block));
            return ['primary' => 'Contact', 'secondary' => '', 'meta' => $count.' '.($count === 1 ? 'child' : 'children')];
        }
        if ($type === 'legal_disclaimer') {
            $text = PublicContentSetting::general()->getAttribute('legal_disclaimer');
            return ['primary' => 'General legal disclaimer', 'secondary' => $this->contentExcerpt($text, 120), 'meta' => 'Canonical General setting'];
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

    private function componentParentSearchText(CustomPageSetting $settings, array $block, ?string $imageName): string
    {
        $type = is_string($block['type'] ?? null) ? $block['type'] : '';
        $parts = [self::COMPONENT_LABELS[$type] ?? $type, CustomPageSetting::componentPublished($block) ? 'Published' : 'Unpublished'];
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
        } elseif ($type === 'contact') {
            $parts[] = implode(' ', array_map(
                static fn (array $child): string => self::CONTACT_CHILD_LABELS[(string) ($child['type'] ?? '')] ?? '',
                $settings->contactChildren($block),
            ));
        } elseif ($type === 'legal_disclaimer') {
            $parts[] = PublicContentSetting::general()->getAttribute('legal_disclaimer');
        }

        return implode(' ', array_filter($parts, static fn (mixed $part): bool => is_string($part) && trim($part) !== ''));
    }

    private function socialChildDetail(array $child): string
    {
        $platforms = is_array($child['social_platforms'] ?? null) ? $child['social_platforms'] : [];
        $labels = array_values(array_filter(array_map(
            fn (mixed $platform): ?string => is_string($platform) ? ($this->availableSocialPlatforms[$platform] ?? null) : null,
            $platforms,
        )));

        return $labels === [] ? 'No social links selected' : implode(', ', $labels);
    }

    /** @return array<string,string> */
    private function availableContactChildOptions(array $arguments): array
    {
        try {
            $block = $this->actionComponent($arguments);
        } catch (ValidationException) {
            return self::CONTACT_CHILD_LABELS;
        }
        if (($block['type'] ?? null) !== 'contact') {
            return [];
        }
        $existing = collect($this->settings()->contactChildren($block))->pluck('type')->filter('is_string')->all();

        return array_filter(
            self::CONTACT_CHILD_LABELS,
            static fn (string $label, string $type): bool => ! in_array($type, $existing, true),
            ARRAY_FILTER_USE_BOTH,
        );
    }

    /** @return list<string> */
    private function validatedSocialPlatforms(mixed $platforms): array
    {
        if (! is_array($platforms)) {
            return [];
        }
        $selected = [];
        foreach ($platforms as $platform) {
            if (! is_string($platform) || ! array_key_exists($platform, $this->availableSocialPlatforms)) {
                throw ValidationException::withMessages(['social_platforms' => 'Choose only social links currently available in General.']);
            }
            $selected[] = $platform;
        }

        return array_values(array_unique($selected));
    }

    private function syncLegalDisclaimerIfNeeded(array $data): void
    {
        if (($data['type'] ?? null) !== 'legal_disclaimer') {
            return;
        }
        $value = is_string($data['legal_disclaimer'] ?? null) ? trim($data['legal_disclaimer']) : '';
        app(AdminSettingsService::class)->updatePublicContent(
            PublicContentSetting::general(),
            ['legal_disclaimer' => $value === '' ? null : $value],
        );
    }

    /** @return array<int,string> */
    private function parentOptions(): array
    {
        return SiteSection::query()
            ->whereNull('parent_id')
            ->whereKeyNot($this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->filter(static fn (SiteSection $section): bool => $section->canContainChildren())
            ->mapWithKeys(static fn (SiteSection $section): array => [
                (int) $section->getKey() => (string) ($section->getAttribute('navigation_label') ?: $section->getAttribute('title')),
            ])
            ->all();
    }

    private function clearSelections(): void
    {
        $this->selectedComponentTargets = [];
        $this->selectedChildTargets = [];
    }

    private function metricValue(mixed $metric): string
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return '—';
        }
        $value = (float) $metric['value'];
        return number_format($value, $value === floor($value) ? 0 : 1);
    }

    private function section(): SiteSection
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($this->sectionId);
        return $section;
    }

    private function settings(): CustomPageSetting
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->findOrFail($this->settingsId);
        return $settings;
    }
}
