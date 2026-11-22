<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminAutoPublishPreferences;
use App\Domain\Content\CustomPageEditorialService;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\CustomPageSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;

trait CustomPageWorkspaceComponentActions
{
    #[Locked]
    public ?array $editingComponentOriginal = null;

    public function moveComponent(int $index, string $type, string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }

        $changed = app(CustomPageEditorialService::class)->moveBlock($this->settings(), $index, $type, $direction);
        $this->clearSelections();
        $this->reloadWorkspace();

        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Component order updated',
                status: 'success',
            );
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
            if (! str_contains($target, ':')) {
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
            app(AdminNotifier::class)->notification(
                title: 'Component order updated',
                status: 'success',
            );
        }
    }

    /** One native Livewire Sortable entry for every hierarchy level.
     * The destination group ID alone determines the new parent. */
    private function placeSortedComponent(string $target, int $position, int|string|null $groupId): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        $changed = app(CustomPageEditorialService::class)->placeComponent(
            $this->settings(), $target, $position, $groupId,
        );
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(title: 'Component order updated', status: 'success');
        }
    }

    public function sortComponent(string $target, int $position, int|string|null $groupId = null): void
    {
        $this->placeSortedComponent($target, $position, $groupId ?? 'root');
    }

    public function moveSelected(string $direction): void
    {
        if (! $this->componentReorderEnabled()) {
            return;
        }
        if (! in_array($direction, ['up', 'down'], true)) {
            throw ValidationException::withMessages(['component' => 'Invalid move direction.']);
        }
        $targets = $this->selectedComponentTargetData();
        if ($targets === []) {
            return;
        }
        $changed = app(CustomPageEditorialService::class)->moveSelectedBlocks(
            $this->settings(), $targets, $direction,
        );
        $count = count($targets);
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Selection moved',
                body: $count.' selected '.($count === 1 ? 'component' : 'components').' moved.',
                status: 'success',
            );
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
        $changed = app(CustomPageEditorialService::class)->updateBlock($this->settings(), $index, $type, $block);
        $this->loadComponentProjection();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                $published ? 'Component published' : 'Component unpublished',
                status: 'success',
            );
        }
    }

    public function publishSelected(): void
    {
        $this->setSelectedPublished(true);
    }

    public function unpublishSelected(): void
    {
        $this->setSelectedPublished(false);
    }

    private function setSelectedPublished(bool $published): void
    {
        $targets = $this->selectedComponentTargetData();
        if ($targets === []) {
            return;
        }
        $changed = DB::transaction(function () use ($targets, $published): bool {
            $changed = false;
            foreach ($targets as $target) {
                $block = $this->componentAt($target['index'], $target['type']);
                if (CustomPageSetting::componentPublished($block) === $published) {
                    continue;
                }
                $block['published'] = $published;
                $changed = app(CustomPageEditorialService::class)->updateBlock(
                    $this->settings(), $target['index'], $target['type'], $block,
                ) || $changed;            }

            return $changed;
        });
        $this->clearSelections();
        $this->reloadWorkspace();
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: $published ? 'Selection published' : 'Selection unpublished',
                status: 'success',
            );
        }
    }

    /** Direct Stage mutations reuse the canonical editorial owner, not dialog actions. */
    public function showCustomStagePanel(string $panel): void
    {
        if (in_array($panel, ['create', 'layout', 'settings'], true)) {
            $this->customStagePanel = $panel;
        }
    }

    public function focusStageComponent(int $index, string $type): void
    {
        $component = $this->componentAt($index, $type);
        $this->selectedComponentTargets = [$index.':'.$type];
        $this->stageSelectedLayoutId = $type === 'layout' ? (string) ($component['id'] ?? '') : null;
        $this->syncCustomPreviewFocus();
    }

    public function updatedStageQuickType(): void
    {
        $this->stageQuickSourceId = '';
    }

    /** Page templates, not individual Gallery/Journal records. */
    public function stageQuickSources(): array
    {
        if (! in_array($this->stageQuickType, CustomPageSetting::EMBEDDED_COMPONENT_TYPES, true)) {
            return [];
        }

        return app(\App\Domain\Content\CustomPageEmbeddedContent::class)->sourceOptions($this->stageQuickType);
    }

    public function createStageComponent(): void
    {
        $type = $this->stageQuickType;
        if (! in_array($type, CustomPageSetting::COMPONENT_TYPES, true)) {
            return;
        }

        $data = [
            'type' => $type,
            'publication_state' => 'unpublished',
            'title' => $this->stageQuickTitle,
            'body' => $this->stageQuickBody,
            'layout_area_id' => $this->stageQuickAreaId !== '' ? $this->stageQuickAreaId : null,
            'layout_mode' => 'rows',
            'columns' => 2,
            'rows' => 1,
            'gap' => 'medium',
            'column_layout' => 'equal',
            'media_asset_id' => null,
            'image_decorative' => false,
            'source_page_id' => ctype_digit($this->stageQuickSourceId) ? (int) $this->stageQuickSourceId : null,
            'gallery_presentation' => 'sequence',
        ];
        try {
            $changed = app(CustomPageEditorialService::class)->addBlock($this->settings(), $this->componentPayload($data));
            if (! $changed) {
                return;
            }
            $index = count($this->settings()->fresh()->components()) - 1;
            $this->stageQuickTitle = '';
            $this->stageQuickBody = '';
            $this->stageQuickSourceId = '';
            $this->customStagePanel = 'layout';
            $this->reloadWorkspace();
            $this->focusStageComponent($index, $type);
            app(AdminNotifier::class)->notification(title: 'Unpublished component created', status: 'success');
        } catch (ValidationException $exception) {
            $this->stageValidationFailure('Component was not created', $exception);
        }
    }

    public function sortStageComponent(string $target, int $position, int|string|null $groupId = null): void
    {
        try {
            $changed = app(CustomPageEditorialService::class)->placeLayoutComponent(
                $this->settings(), $target, $position, (string) ($groupId ?? 'root'),
            );
            $this->clearSelections();
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Layout placement updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->reloadWorkspace();
            $this->stageValidationFailure('Layout placement unchanged', $exception);
        }
    }

    public function setStageLayoutProperty(string $id, string $field, string $value): void
    {
        if (! in_array($field, ['layout_mode', 'columns', 'rows', 'gap', 'column_layout'], true)) {
            return;
        }
        $selected = $this->stageLayoutById($id);
        if ($selected === null) {
            return;
        }
        $old = $selected['block'];
        $settings = [
            'layout_mode' => (string) ($old['layout_mode'] ?? 'rows'),
            'columns' => (int) $old['columns'],
            'rows' => (int) $old['rows'],
            'gap' => (string) $old['gap'],
            'column_layout' => (string) ($old['column_layout'] ?? 'equal'),
        ];
        $settings[$field] = in_array($field, ['columns', 'rows'], true) ? (int) $value : $value;
        if ($settings['layout_mode'] === 'columns') {
            $settings['rows'] = 1;
        }
        if ($settings['columns'] !== 2) {
            $settings['column_layout'] = 'equal';
        }

        try {
            $updated = array_replace($old, CustomPageSetting::layoutBlock(
                CustomPageSetting::componentPublished($old),
                $settings['columns'], $settings['rows'], $settings['gap'],
                $old, $settings['column_layout'], $settings['layout_mode'],
            ));
            $changed = app(CustomPageEditorialService::class)->updateBlock(
                $this->settings(), $selected['index'], 'layout', $updated, $old,
            );
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Layout updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->reloadWorkspace();
            $this->stageValidationFailure('Layout unchanged', $exception);
        }
    }

    public function setTableComponentLayout(int $index, string $type, string $destination): void
    {
        $original = $this->componentAt($index, $type);
        $updated = $original;
        if ($destination === '') {
            unset($updated['layout_area_id']);
        } else {
            $updated['layout_area_id'] = $destination;
        }

        try {
            $changed = app(CustomPageEditorialService::class)->updateBlock(
                $this->settings(), $index, $type, $updated, $original,
            );
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Layout assignment updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->reloadWorkspace();
            $this->stageValidationFailure('Layout assignment unchanged', $exception);
        }
    }

    public function setStageEmbeddedProperty(int $index, string $type, string $field, string $value): void
    {
        if ($type !== 'gallery' || $field !== 'gallery_presentation'
            || ! in_array($value, ['sequence', 'two_columns'], true)) {
            return;
        }

        $original = $this->componentAt($index, $type);
        $updated = [...$original, $field => $value];
        try {
            $changed = app(CustomPageEditorialService::class)->updateBlock(
                $this->settings(), $index, $type, $updated, $original,
            );
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Gallery presentation updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->reloadWorkspace();
            $this->stageValidationFailure('Gallery presentation unchanged', $exception);
        }
    }

    public function setStageEmbeddedPage(int $index, string $type, string $pageId): void
    {
        if (! in_array($type, CustomPageSetting::EMBEDDED_COMPONENT_TYPES, true)) {
            return;
        }
        if ($pageId !== '' && ! ctype_digit($pageId)) {
            return;
        }

        $original = $this->componentAt($index, $type);
        $updated = [...$original,
            'source_page_id' => $pageId === '' ? null : (int) $pageId];
        try {
            $changed = app(CustomPageEditorialService::class)->updateBlock(
                $this->settings(), $index, $type, $updated, $original,
            );
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Source page updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->reloadWorkspace();
            $this->stageValidationFailure('Source page unchanged', $exception);
        }
    }

    public function setStageComponentBoundary(int $index, string $type, string $border): void
    {
        if (! in_array($border, ['none', ...CustomPageSetting::DIVIDER_VARIANTS], true)) {
            return;
        }

        $original = $this->componentAt($index, $type);
        $updated = $original;
        if ($border === 'none') {
            unset($updated['layout_divider_before']);
        } else {
            $updated['layout_divider_before'] = $border;
        }

        try {
            $changed = app(CustomPageEditorialService::class)->updateBlock(
                $this->settings(), $index, $type, $updated, $original,
            );
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Component boundary updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->reloadWorkspace();
            $this->stageValidationFailure('Component boundary unchanged', $exception);
        }
    }

    public function setStageLayoutAreaBorder(string $id, string $areaId, string $border): void
    {
        if (! in_array($border, ['none', ...CustomPageSetting::DIVIDER_VARIANTS], true)) {
            return;
        }
        $selected = $this->stageLayoutById($id);
        if ($selected === null) {
            return;
        }
        $old = $selected['block'];
        $areaIndex = array_search($areaId, array_column($old['areas'], 'id'), true);
        if ($areaIndex === false || ($old['areas'][$areaIndex]['border'] ?? 'none') === $border) {
            return;
        }
        $updated = $old;
        $updated['areas'][$areaIndex]['border'] = $border;
        try {
            $changed = app(CustomPageEditorialService::class)->updateBlock(
                $this->settings(), $selected['index'], 'layout', $updated, $old,
            );
            $this->reloadWorkspace();
            if ($changed) {
                app(AdminNotifier::class)->notification(title: 'Layout boundary updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->reloadWorkspace();
            $this->stageValidationFailure('Layout boundary unchanged', $exception);
        }
    }

    private function stageValidationFailure(string $title, ValidationException $exception): void
    {
        $messages = array_merge(...array_values($exception->errors()));
        app(AdminNotifier::class)->notification(
            title: $title,
            body: $messages[0] ?? 'The change could not be saved.',
            status: 'danger',
        );
    }

    public function addComponentAction(): Action
    {
        $action = Action::make('addComponent')
            ->label('Add component')
            ->fillForm(fn (array $arguments): array => [
                'type' => ($arguments['initialType'] ?? null) === 'layout' ? 'layout' : 'text',
                'layout_area_id' => is_string($arguments['layoutAreaId'] ?? null) ? $arguments['layoutAreaId'] : null,
                'layout_divider_before' => 'none',
                'publication_state' => app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::CUSTOM_PAGE)
                    ? 'published' : 'unpublished',
                'image_decorative' => false,
                'media_asset_id' => null,
            ])
            ->schema($this->componentEditorSchema())
            ->modalHeading(__('Add component'))
            ->action(function (array $data): void {
                app(CustomPageEditorialService::class)->addBlock($this->settings(), $this->componentPayload($data));

                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(
                    title: 'Component added',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Add component', AdminDialogSize::Large);
    }

    /** Full Layout creation belongs to the table toolbar, never the visual Stage. */
    public function addLayoutAction(): Action
    {
        $action = Action::make('addLayout')
            ->label('Add layout')
            ->fillForm(fn (): array => [
                'publication_state' => app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::CUSTOM_PAGE)
                    ? 'published' : 'unpublished',
                'layout_area_id' => null,
                'layout_divider_before' => 'none',
                'layout_mode' => 'rows',
                'columns' => 2,
                'rows' => 1,
                'column_layout' => 'equal',
                'gap' => 'medium',
            ])
            ->schema([
                \Filament\Schemas\Components\Grid::make()
                    ->columns(['md' => 2])
                    ->schema([
                        Select::make('publication_state')->label('Status')
                            ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                            ->required(),
                        Select::make('layout_area_id')->label('Place in')
                            ->options(fn (): array => $this->layoutAreaOptions())
                            ->placeholder('Full width')->nullable(),
                        ...$this->componentTypeFields('layout'),
                        Select::make('layout_divider_before')->label('Separator above')
                            ->options(['none' => 'None', ...self::DIVIDER_LABELS])
                            ->required(),
                    ]),
            ])
            ->modalHeading('Add layout')
            ->action(function (array $data): void {
                app(CustomPageEditorialService::class)->addBlock(
                    $this->settings(), $this->componentPayload([...$data, 'type' => 'layout']),
                );
                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(title: 'Layout added', status: 'success');
            });

        return AdminDialog::create($action, 'Add layout', AdminDialogSize::Large);
    }

    public function editComponentAction(): Action
    {
        return $this->componentEditingAction('editComponent');
    }

    private function componentEditingAction(string $actionName): Action
    {
        $action = Action::make($actionName)
            ->label('Edit')
            ->fillForm(function (array $arguments): array {
                $block = $this->actionComponent($arguments);
                $this->editingComponentOriginal = $block;

                return $this->componentEditorData($block);
            })
            ->schema(fn (array $arguments): array => $this->componentEditorSchema(
                onTypeChange: function (mixed $state) use ($arguments): void {
                    $target = is_string($state) ? $state : '';
                    [, $currentType] = $this->actionComponentTarget($arguments);
                    if ($target === $currentType || ! array_key_exists($target, self::COMPONENT_LABELS)) {
                        return;
                    }
                    $this->replaceMountedAction('changeComponentType', [
                        ...$arguments,
                        'targetType' => $target,
                    ]);
                },
                onContactChange: function (string $key, mixed $value) use ($arguments): void {
                    [$index, $type] = $this->actionComponentTarget($arguments);
                    if ($type === 'contact') {
                        $this->persistContactEditorField($index, $key, $value);
                    }
                },
                onImageChange: function (mixed $state) use ($arguments): void {
                    [$index, $type] = $this->actionComponentTarget($arguments);
                    if (! in_array($type, CustomPageSetting::MEDIA_COMPONENT_TYPES, true)) {
                        return;
                    }

                    try {
                        $id = filter_var($state, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                        if ($id === false) {
                            throw ValidationException::withMessages(['media_asset_id' => 'Choose a matching media file from Storage.']);
                        }
                        $existing = $this->componentAt($index, $type);
                        $changed = app(CustomPageEditorialService::class)->updateBlock(
                            $this->settings(),
                            $index,
                            $type,
                            [...$existing, 'media_asset_id' => $id],
                            $this->editingComponentOriginal,
                        );
                    } catch (ValidationException $exception) {
                        $this->throwComponentEditValidation($exception);
                    }
                    $this->editingComponentOriginal = $this->settings()->components()[$index];
                    if (! $changed) {
                        return;
                    }

                    $this->clearSelections();
                    $this->loadComponentProjection();
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(
                        title: 'Component saved',
                        status: 'success',
                    );
                },
            ))
            ->modalHeading(__('Edit component'))
            ->extraModalFooterActions(fn (array $arguments): array => $this->componentEditHeaderActions($arguments, $actionName))
            ->action(function (array $data, array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                $existing = $this->actionComponent($arguments);
                if (($data['type'] ?? null) !== $type) {
                    return; // A type change is handled by changeComponentType.
                }

                try {
                    $payload = $this->componentPayload($data, $existing);
                    $changed = app(CustomPageEditorialService::class)->updateBlock(
                        $this->settings(), $index, $type, $payload, $this->editingComponentOriginal,
                    );
                } catch (ValidationException $exception) {
                    $this->throwComponentEditValidation($exception);
                }
                $this->editingComponentOriginal = $this->settings()->components()[$index];

                $this->clearSelections();
                $this->reloadWorkspace();
                if ($changed) {
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(
                        title: 'Component saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /**
     * Autosave exactly one Contact field through the existing Custom Page
     * editorial mutation. Sibling settings are rebuilt from the current
     * persisted block, never overwritten by a stale modal snapshot.
     */
    private function persistContactEditorField(int $index, string $key, mixed $value): void
    {
        $existing = $this->componentAt($index, 'contact');
        $data = $this->componentEditorData($existing);
        if (str_starts_with($key, 'contact_social_selected.')) {
            $platform = substr($key, strlen('contact_social_selected.'));
            if (! array_key_exists($platform, $this->availableSocialPlatforms)) {
                return;
            }
            $data['contact_social_selected'][$platform] = (bool) $value;
        } elseif (in_array($key, [
            'title', 'contact_email_enabled', 'contact_social_enabled',
            'contact_form_enabled',
        ], true)) {
            $data[$key] = $value;
        } else {
            throw new \InvalidArgumentException('Unknown Contact field.');
        }

        try {
            $payload = $this->contactComponentPayload(
                $data, CustomPageSetting::componentPublished($existing), $existing,
            );
            $editor = app(CustomPageEditorialService::class);
            $changed = $editor->updateBlock(
                $this->settings(), $index, 'contact', $payload, $this->editingComponentOriginal,
            );
        } catch (ValidationException $exception) {
            $this->throwComponentEditValidation($exception);
        }
        $this->editingComponentOriginal = $this->componentAt($index, 'contact');
        if (! $changed) {
            return;
        }
        $this->loadComponentProjection();
        $this->forceRender();
        app(AdminNotifier::class)->notification(title: 'Component saved', status: 'success');
    }

    private function throwComponentEditValidation(ValidationException $exception): never
    {
        $path = $this->getMountedActionSchema()->getStatePath();
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$path.'.'.($field === 'component' ? 'type' : $field)] = $messages;
        }
        throw ValidationException::withMessages($errors);
    }

    /** @param array<string,mixed> $target */
    private function retargetComponentEditor(string $actionName, array $target): void
    {
        $this->cacheAction($this->componentEditingAction($actionName));
        $this->replaceMountedAction($actionName, $target);
    }

    /** @param array<string,mixed> $arguments
     * @return list<Action>
     */
    private function componentEditHeaderActions(array $arguments, string $actionName): array
    {
        [$index, $type] = $this->actionComponentTarget($arguments);
        $block = $this->componentAt($index, $type);
        $count = count($this->settings()->components());
        $target = ['componentIndex' => $index, 'componentType' => $type];
        $actions = [];

        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $canMove = $this->componentReorderEnabled() && ($direction === 'up' ? $index > 0 : $index < $count - 1);
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($index, $type, $direction, $actionName): void {
                $this->moveComponent($index, $type, $direction);
                $nextIndex = $index + ($direction === 'up' ? -1 : 1);
                $this->retargetComponentEditor($actionName, ['componentIndex' => $nextIndex, 'componentType' => $type]);
            })->disabled(! $canMove);
        }

        $published = CustomPageSetting::componentPublished($block);
        $actions[] = AdminDialog::headerAction(
            'dialogTogglePublication',
            $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
            function () use ($index, $type, $published, $target, $actionName): void {
                $this->setComponentPublished($index, $type, ! $published);
                $this->retargetComponentEditor($actionName, $target);
            },
        );
        $actions[] = AdminDialog::headerAction('dialogDeleteComponent', AdminRowAction::Delete, function () use ($target): void {
            $this->replaceMountedAction('deleteComponent', $target);
        })->disabled(! CustomPageSetting::canDeleteComponent($block, $this->settings()->components()));

        return $actions;
    }

    public function changeComponentTypeAction(): Action
    {
        $description = function (array $arguments): string {
            [, $oldType] = $this->actionComponentTarget($arguments);
            $targetType = $this->actionTargetComponentType($arguments);

            return 'Changing '.(self::COMPONENT_LABELS[$oldType] ?? $oldType)
                .' to '.(self::COMPONENT_LABELS[$targetType] ?? $targetType)
                .' can remove component-specific content that cannot be carried over.';
        };

        $action = Action::make('changeComponentType')
            ->label('Change component type')
            ->action(function (array $arguments): void {
                [$index, $oldType] = $this->actionComponentTarget($arguments);
                $targetType = $this->actionTargetComponentType($arguments);
                $changed = app(CustomPageEditorialService::class)->convertBlock($this->settings(), $index, $oldType, $targetType);
                $this->clearSelections();
                $this->reloadWorkspace();

                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Component type updated',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::confirm(
            $action,
            'Change type?',
            $description,
            'Change type',
            condition: fn (array $arguments): bool => $this->componentTypeChangeLosesContent($arguments),
        );
    }

    public function deleteComponentAction(): Action
    {
        $action = Action::make('deleteComponent')
            ->label('Delete')
            ->color('danger')
            ->action(function (array $arguments): void {
                [$index, $type] = $this->actionComponentTarget($arguments);
                app(CustomPageEditorialService::class)->deleteBlock($this->settings(), $index, $type);
                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(
                    title: 'Component deleted',
                    status: 'success',
                );
            });

        return AdminDialog::confirm(
            $action,
            'Delete component?',
            submitLabel: 'Delete',
            danger: true,
        );
    }

    public function deleteSelectedAction(): Action
    {
        $action = Action::make('deleteSelected')
            ->label('Delete selected')
            ->color('danger')
            ->action(function (): void {
                $parents = $this->selectedComponentTargetData();
                if ($parents === []) {
                    return;
                }
                $changed = app(CustomPageEditorialService::class)->deleteBlocks($this->settings(), $parents);
                if (! $changed) {
                    return;
                }
                $count = count($parents);
                $this->clearSelections();
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(
                    title: 'Selection deleted',
                    body: $count.' selected '.($count === 1 ? 'item' : 'items').' processed.',
                    status: 'success',
                );
            });

        return AdminDialog::confirm(
            $action,
            'Delete items?',
            submitLabel: 'Delete',
            danger: true,
        );
    }

    public function deleteSelectedComponentsAction(): Action
    {
        return $this->deleteSelectedAction();
    }
}
