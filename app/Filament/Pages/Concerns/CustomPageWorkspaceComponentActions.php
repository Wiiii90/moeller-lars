<?php

namespace App\Filament\Pages\Concerns;

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
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Enums\Width;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceComponentActions
{
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

    public function moveSelected(string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['component' => 'The move direction is invalid.']);
        }

        $parents = $this->selectedComponentTargetData();
        $children = $this->selectedChildTargetData();
        if ($parents === [] && $children === []) {
            return;
        }

        $changed = DB::transaction(function () use ($parents, $children, $direction): bool {
            $childrenChanged = $this->moveSelectedChildTargets($children, $direction);
            $parentsChanged = $parents !== []
                && app(CustomPageEditorialService::class)->moveSelectedBlocks($this->settings(), $parents, $direction);

            return $childrenChanged || $parentsChanged;
        });

        $count = count($parents) + count($children);
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            Notification::make()
                ->title('Selection moved')
                ->body($count.' selected '.($count === 1 ? 'item' : 'items').' updated in '.($parents !== [] && $children !== [] ? 'their own scopes.' : 'order.'))
                ->success()
                ->send();
        }
    }

    public function moveSelectedComponents(string $direction): void
    {
        $this->moveSelected($direction);
    }

    public function setComponentPublished(int $index, string $type, bool $published): void
    {
        $block = $this->componentAt($index, $type);
        $block['published'] = $published;
        app(CustomPageEditorialService::class)->updateBlock($this->settings(), $index, $type, $block);
        $this->loadComponentProjection(refreshCvCount: false);
    }

    public function publishSelected(): void
    {
        $this->setSelectedPublished(true);
    }

    public function unpublishSelected(): void
    {
        $this->setSelectedPublished(false);
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
                'media_asset_id' => null,
            ])
            ->schema($this->componentEditorSchema(includeTypeSelect: true))
            ->modalHeading('Add component')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Add component')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (array $data): void {
                DB::transaction(function () use ($data): void {
                    app(CustomPageEditorialService::class)->addBlock($this->settings(), $this->componentPayload($data));
                    if (($data['type'] ?? null) === 'cv_list') {
                        $this->syncCvEntryEditorRows($data['cv_entries'] ?? null);
                    }
                });

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

                DB::transaction(function () use ($data, $index, $type, $existing): void {
                    app(CustomPageEditorialService::class)->updateBlock(
                        $this->settings(),
                        $index,
                        $type,
                        $this->componentPayload($data, $existing),
                    );
                    if ($type === 'cv_list') {
                        $this->syncCvEntryEditorRows($data['cv_entries'] ?? null);
                    }
                });

                $this->clearSelections();
                $this->reloadWorkspace();
                Notification::make()->title('Component saved')->success()->send();
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

    public function deleteSelectedAction(): Action
    {
        return Action::make('deleteSelected')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected items?')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Delete')->extraAttributes(['class' => 'custom-page-dialog__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'custom-page-dialog__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'custom-page-dialog'])
            ->action(function (): void {
                $parents = $this->selectedComponentTargetData();
                $children = $this->selectedChildTargetData();
                if ($parents === [] && $children === []) {
                    return;
                }

                DB::transaction(function () use ($parents, $children): void {
                    $this->deleteSelectedChildTargets($children, $parents);
                    if ($parents !== []) {
                        app(CustomPageEditorialService::class)->deleteBlocks($this->settings(), $parents);
                    }
                });

                $count = count($parents) + count($children);
                $this->clearSelections();
                $this->reloadWorkspace();
                Notification::make()
                    ->title('Selection deleted')
                    ->body($count.' selected '.($count === 1 ? 'item' : 'items').' processed.')
                    ->success()
                    ->send();
            });
    }

    public function deleteSelectedComponentsAction(): Action
    {
        return $this->deleteSelectedAction();
    }
}
