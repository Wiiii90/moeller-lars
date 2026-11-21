<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkInformation;
use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\GalleryPresentation;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Controls\AdminPanelControl;
use App\Filament\Support\AdminHelp;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\ArtworkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceUploadSettings
{
    public function gallerySettingsAction(): Action
    {
        $action = Action::make('gallerySettings')
            ->label('Settings')
            ->fillForm(fn (): array => $this->gallerySettingsValues(
                ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']),
            ))
            ->schema([$this->gallerySettingsFields(false)])
            ->modalHeading('Gallery settings')
            ->action(function (array $data): void {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                $service = app(GalleryEditorialService::class);

                try {
                    $updated = $service->update($gallery, [
                        'name' => $data['name'],
                        'edge_navigation_mode' => $this->galleryEdgeNavigationMode($data, GalleryPresentation::edgeMode($gallery)),
                        'artwork_divider' => $data['artwork_divider'] ?? GalleryPresentation::DIVIDER_NONE,
                        // Sort changes are exclusively committed through the
                        // confirmed Action, never the Edit modal's autosave.
                        'sort_mode' => GalleryPresentation::sortMode($gallery),
                        'add_placement' => $data['add_placement'] ?? GalleryPresentation::addPlacement($gallery),
                        'artwork_information' => $this->galleryInformationFromForm($data, $gallery),
                        'media_viewer_enabled' => $data['media_viewer_enabled'] ?? GalleryPresentation::viewerEnabled($gallery),
                        'viewer_navigation_enabled' => $data['viewer_navigation_enabled'] ?? GalleryPresentation::viewerNavigationEnabled($gallery),
                        'viewer_navigation_cyclic' => (bool) ($data['viewer_navigation_enabled'] ?? GalleryPresentation::viewerNavigationEnabled($gallery))
                            && (bool) ($data['viewer_navigation_cyclic'] ?? GalleryPresentation::viewerNavigationCyclic($gallery)),
                        'artwork_pages_enabled' => $data['artwork_pages_enabled'] ?? GalleryPresentation::artworkPagesEnabled($gallery),
                    ]);
                    $changed = $updated->wasChanged();
                } catch (ValidationException $exception) {
                    // Domain errors use field names; the mounted Filament form
                    // owns their presentation path, including nested actions.
                    $statePath = $this->getMountedActionSchema()->getStatePath();
                    $errors = [];
                    foreach ($exception->errors() as $field => $messages) {
                        $errors[$statePath.'.'.$field] = $messages;
                    }

                    throw ValidationException::withMessages($errors);
                }

                $this->loadGallery((int) $gallery->getKey());
                $this->loadMoveTargets();
                $this->refreshWorkspaceAfterMutation();
                $this->refreshGalleryStageSettings();
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Gallery settings saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /**
     * Stage shows frequent controls; the Edit dialog keeps the full settings.
     * Both reuse the same fields and audited editorial service.
     */
    private function gallerySettingsFields(bool $stage): Grid
    {
        $name = AdminControl::text('name', 'Gallery title')
            ->required(! $stage)->maxLength(160);

        $order = AdminControl::select('sort_mode')
            ->label(AdminHelp::label(
                'Artwork order',
                'Gallery artwork ordering',
                'Manual uses saved positions. Date and Media Added modes continuously order the Gallery. A drag, Move or Position edit switches to Manual and keeps that sequence.',
            ))
            ->options(GalleryPresentation::SORT_OPTIONS)
            ->placeholder('')
            ->default(GalleryPresentation::SORT_MANUAL)
            ->required(! $stage)
            ->live()
            // Never submit this field through the generic Edit autosave.
            ->extraAlpineAttributes(['x-on:change.stop' => 'true'], merge: true)
            ->afterStateUpdated(function (mixed $state, callable $set) use ($stage): void {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                $currentMode = GalleryPresentation::sortMode($gallery);
                $set('sort_mode', $currentMode);
                if (! is_string($state) || $state === $currentMode) {
                    return;
                }
                if ($stage) {
                    $this->changeGalleryStageSort($state);
                    return;
                }
                $preview = app(GalleryEditorialService::class)->previewSortChange($gallery, $state);
                $this->mountAction('changeGallerySort', [
                    'sort_mode' => $state,
                    'signature' => $preview['signature'],
                ]);
            });

        $fields = [];
        if (! $stage) {
            $fields[] = $name->columnSpanFull();
        }
        $fields[] = $order;
        $fields[] = $this->gallerySettingControl(
            AdminControl::select('add_placement')
                ->label(AdminHelp::label(
                    'New Artwork placement',
                    'Placement when creating an Artwork',
                    'Automatic follows the current Artwork order without changing its mode. Fixed placements establish a manual sequence. A manually entered Position overrides this setting.',
                ))
                ->options(GalleryPresentation::ADD_OPTIONS)
                ->placeholder('')
                ->default(GalleryPresentation::ADD_FOLLOW)
                ->required(! $stage),
            'add_placement', $stage,
        );

        // Both selector fields and the two panel groups occupy the same
        // schema grid. One breakpoint controls their columns together.
        $fields[] = Grid::make(['default' => 1])
            ->schema([
                $this->galleryDetailPagesPanel($stage),
                $this->galleryViewerPanel($stage),
            ]);
        $fields[] = Grid::make(['default' => 1])
            ->schema([$this->galleryInformationPanel($stage)]);
        if (! $stage) {
            $fields[] = AdminControl::select('artwork_divider', 'Between Artworks')
                ->options(GalleryPresentation::DIVIDER_OPTIONS)
                ->default(GalleryPresentation::DIVIDER_NONE)
                ->required()
                ->columnSpanFull();
        }

        return Grid::make(['default' => 1, 'sm' => 2])->schema($fields);
    }

    /** Gallery information checkboxes share the Stage and Edit domain owner. */
    private function galleryInformationPanel(bool $stage): \Filament\Schemas\Components\Group
    {
        $update = fn () => $this->updateGalleryInformationFromActiveForm($stage);
        $fields = [];
        foreach (ArtworkInformation::FIELDS as $field => $label) {
            $checkbox = AdminControl::checkbox('info_'.$field, $label)->live();
            if ($stage) {
                $checkbox->afterStateUpdated($update);
            }
            $fields[] = $checkbox;
        }

        return AdminPanelControl::make(
            'Show Artwork Information', 'info_enabled', $fields,
            $stage ? $update : null,
            collapsible: true,
            dense: $stage,
        );
    }

    private function galleryViewerPanel(bool $stage): \Filament\Schemas\Components\Group
    {
        $navigation = AdminControl::checkbox('viewer_navigation_enabled', 'Navigation')
            ->live()
            ->afterStateUpdated(function (mixed $state, callable $set, callable $get) use ($stage): void {
                if (! (bool) $state) {
                    $set('viewer_navigation_cyclic', false);
                }
                if ($stage) {
                    $this->updateGalleryStageSettings([
                        'viewer_navigation_enabled' => (bool) $state,
                        'viewer_navigation_cyclic' => (bool) $state && (bool) $get('viewer_navigation_cyclic'),
                    ]);
                }
            });
        $cyclic = AdminControl::checkbox('viewer_navigation_cyclic', 'Cyclic')
            ->live()
            ->disabled(fn (callable $get): bool => ! (bool) $get('viewer_navigation_enabled'));
        if ($stage) {
            $cyclic->afterStateUpdated(fn (mixed $state) => $this->updateGalleryStageSetting(
                'viewer_navigation_cyclic', (bool) $state,
            ));
        }

        return AdminPanelControl::make('Activate Modal Viewer', 'media_viewer_enabled', [
            AdminPanelControl::dependencyTree($navigation, [$cyclic]),
        ], $stage ? fn (mixed $state) => $this->updateGalleryStageSetting('media_viewer_enabled', $state) : null, collapsible: true, dense: $stage);
    }

    private function galleryDetailPagesPanel(bool $stage): \Filament\Schemas\Components\Group
    {
        $navigation = AdminControl::checkbox('edge_navigation_enabled', 'Navigation')->live();
        $cyclic = AdminControl::checkbox('edge_navigation_cyclic', 'Cyclic')
            ->live()
            ->disabled(fn (callable $get): bool => ! (bool) $get('edge_navigation_enabled'));

        if ($stage) {
            $navigation->afterStateUpdated(function (mixed $state, callable $set): void {
                $data = $this->galleryStageSettingsData;
                $data['edge_navigation_enabled'] = (bool) $state;
                if (! (bool) $state) {
                    $set('edge_navigation_cyclic', false);
                    $data['edge_navigation_cyclic'] = false;
                }
                $this->updateGalleryStageSetting('edge_navigation_mode', $this->galleryEdgeNavigationMode($data));
            });
            $cyclic->afterStateUpdated(function (mixed $state): void {
                $data = $this->galleryStageSettingsData;
                $data['edge_navigation_cyclic'] = (bool) $state;
                $this->updateGalleryStageSetting('edge_navigation_mode', $this->galleryEdgeNavigationMode($data));
            });
        }

        return AdminPanelControl::make('Activate Artwork Pages', 'artwork_pages_enabled', [
            AdminPanelControl::dependencyTree($navigation, [$cyclic]),
        ], $stage ? fn (mixed $state) => $this->updateGalleryStageSetting('artwork_pages_enabled', $state) : null, collapsible: true, dense: $stage);
    }

    /** @param array<string,mixed> $data */
    private function galleryEdgeNavigationMode(array $data, string $fallback = GalleryPresentation::EDGE_DISABLED): string
    {
        if (! array_key_exists('edge_navigation_enabled', $data)) {
            // A hidden parent panel must not erase the previous navigation.
            return $fallback;
        }
        if (! (bool) $data['edge_navigation_enabled']) {
            return GalleryPresentation::EDGE_DISABLED;
        }

        return (bool) ($data['edge_navigation_cyclic'] ?? false)
            ? GalleryPresentation::EDGE_CYCLIC
            : GalleryPresentation::EDGE_HIDDEN;
    }

    /** @param array<string,mixed> $data */
    private function galleryInformationFromForm(array $data, ArtworkCategory $gallery): array
    {
        if (! (bool) ($data['info_enabled'] ?? false)) {
            // Filament does not dehydrate invisible child fields. Disabling
            // presentation must never discard its configured arrangement.
            $configured = ArtworkInformation::gallery($gallery);
            $configured['enabled'] = false;
            return $configured;
        }
        return ArtworkInformation::fromForm($data);
    }

    /** Stage persists changes via the same audited Gallery editorial owner. */
    private function updateGalleryInformationFromActiveForm(bool $stage): void
    {
        if (! $stage) {
            return;
        }
        $state = $this->galleryStageSettingsData;
        if (! is_array($state) || ! array_key_exists('info_enabled', $state)) {
            return;
        }

        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        $this->updateGalleryStageSetting(
            'artwork_information',
            $this->galleryInformationFromForm($state, $gallery),
        );
    }

    private function gallerySettingControl(TextInput|Select|Checkbox $field, string $name, bool $stage): TextInput|Select|Checkbox
    {
        if (! $stage) {
            return $field;
        }
        if ($field instanceof TextInput) {
            $field->live(onBlur: true);
        } else {
            $field->live();
        }

        return $field->afterStateUpdated(function (mixed $state) use ($name): void {
            $this->updateGalleryStageSetting($name, $state);
        });
    }

    /** @return array<string,mixed> */
    private function gallerySettingsValues(ArtworkCategory $gallery): array
    {
        return [
            'name' => (string) $gallery->getAttribute('name'),
            'edge_navigation_enabled' => GalleryPresentation::edgeMode($gallery) !== GalleryPresentation::EDGE_DISABLED,
            'edge_navigation_cyclic' => GalleryPresentation::edgeMode($gallery) === GalleryPresentation::EDGE_CYCLIC,
            'artwork_divider' => GalleryPresentation::divider($gallery),
            'sort_mode' => GalleryPresentation::sortMode($gallery),
            'add_placement' => GalleryPresentation::addPlacement($gallery),
            ...ArtworkInformation::formValues(ArtworkInformation::gallery($gallery)),
            'media_viewer_enabled' => GalleryPresentation::viewerEnabled($gallery),
            'viewer_navigation_enabled' => GalleryPresentation::viewerNavigationEnabled($gallery),
            'viewer_navigation_cyclic' => GalleryPresentation::viewerNavigationCyclic($gallery),
            'artwork_pages_enabled' => GalleryPresentation::artworkPagesEnabled($gallery),
        ];
    }

    public function galleryStageSettingsForm(Schema $schema): Schema
    {
        return $schema
            ->components([$this->gallerySettingsFields(true)])
            ->statePath('galleryStageSettingsData');
    }

    private function refreshGalleryStageSettings(): void
    {
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        $this->galleryStageSettingsForm->fill($this->gallerySettingsValues($gallery));
    }

    public function changeGallerySortAction(): Action
    {
        $service = app(GalleryEditorialService::class);
        $preview = function (array $arguments) use ($service): array {
            /** @var ArtworkCategory $gallery */
            $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);

            return $service->previewSortChange(
                $gallery,
                (string) ($arguments['sort_mode'] ?? ''),
            );
        };

        $action = Action::make('changeGallerySort')
            ->modalContent(fn (array $arguments): View => view(
                'filament.resources.artworks.partials.sort-change-confirmation',
                ['changes' => $preview($arguments)['changes']],
            ))
            ->action(function (array $arguments) use ($service): void {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);

                try {
                    $changed = $service->confirmSortChange(
                        $gallery,
                        (string) ($arguments['sort_mode'] ?? ''),
                        (string) ($arguments['signature'] ?? ''),
                    );
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Gallery order was not changed', $exception);
                    if (($arguments['source'] ?? '') !== 'stage') {
                        $this->replaceMountedAction('gallerySettings');
                    }

                    return;
                }

                $this->loadGallery((int) $gallery->getKey());
                $this->refreshWorkspaceAfterMutation();
                $this->refreshGalleryStageSettings();
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Gallery order updated',
                        status: 'success',
                    );
                }

                // Reopen the existing editor with persisted values rather
                // than restoring the old selected mode in the parent form.
                if (($arguments['source'] ?? '') !== 'stage') {
                    $this->replaceMountedAction('gallerySettings');
                }
            });

        return AdminDialog::confirm(
            $action,
            'Change Gallery order?',
            function (array $arguments) use ($preview): string {
                $count = count($preview($arguments)['changes']);
                $label = GalleryPresentation::SORT_OPTIONS[(string) ($arguments['sort_mode'] ?? '')] ?? 'the selected order';

                return 'Apply '.$label.'? '.$count.' Artwork '.($count === 1 ? 'position' : 'positions').' will change.';
            },
            'Apply order',
            false,
            fn (array $arguments): bool => $preview($arguments)['changes'] !== [],
        );
    }

    /** Stage and its dependent checkboxes share one audited mutation boundary. */
    public function updateGalleryStageSetting(string $field, mixed $value): void
    {
        $this->updateGalleryStageSettings([$field => $value]);
    }

    /** @param array<string,mixed> $changes */
    private function updateGalleryStageSettings(array $changes): void
    {
        $editable = [
            'add_placement',
            'media_viewer_enabled',
            'viewer_navigation_enabled',
            'viewer_navigation_cyclic',
            'artwork_pages_enabled',
            'edge_navigation_mode',
            'artwork_information',
        ];
        if ($changes === [] || array_diff(array_keys($changes), $editable) !== []) {
            return;
        }

        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        try {
            $updated = app(GalleryEditorialService::class)->update($gallery, $changes);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Gallery setting was not saved', $exception);
            $this->refreshGalleryStageSettings();
            return;
        }

        if ($updated->wasChanged()) {
            $this->loadGallery((int) $gallery->getKey());
            $this->refreshWorkspaceAfterMutation();
            $this->refreshGalleryStageSettings();
            app(AdminNotifier::class)->notification(
                title: 'Gallery settings saved', status: 'success',
            );
        }
    }

    public function changeGalleryStageSort(string $mode): void
    {
        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        if ($mode === GalleryPresentation::sortMode($gallery)) {
            return;
        }
        try {
            $preview = app(GalleryEditorialService::class)->previewSortChange($gallery, $mode);
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Gallery order was not updated', $exception);
            return;
        }

        $this->mountAction('changeGallerySort', [
            'sort_mode' => $mode,
            'signature' => $preview['signature'],
            'source' => 'stage',
        ]);
    }

    public function materialPresetsAction(): Action
    {
        $action = Action::make('materialPresets')
            ->label('Materials')
            ->modalHeading('Material presets')
            ->modalContent(fn (): View => view(
                'filament.resources.artworks.partials.material-presets-table',
                ['presets' => app(ArtworkMaterialPresetService::class)->ordered()],
            ));

        return AdminDialog::viewer($action, AdminDialogSize::Default)
            ->extraModalWindowAttributes(['class' => 'admin-dialog--table-viewer'], merge: true);
    }

    public function addMaterialPreset(string $name): bool
    {
        return $this->saveMaterialChange(
            fn (): bool => app(ArtworkMaterialPresetService::class)->add($name),
            'Material added',
        );
    }

    public function renameMaterialPreset(int $id, string $name): void
    {
        $this->saveMaterialChange(
            fn (): bool => app(ArtworkMaterialPresetService::class)->rename($id, $name),
            'Material renamed',
        );
    }

    public function deleteMaterialPreset(int $id): void
    {
        $this->saveMaterialChange(
            fn (): bool => app(ArtworkMaterialPresetService::class)->delete($id),
            'Material deleted',
        );
    }

    public function moveMaterialPreset(int $id, string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        $rows = app(ArtworkMaterialPresetService::class)->ordered();
        $current = $rows->search(static fn ($row): bool => (int) $row->getKey() === $id);
        if ($current === false) {
            return;
        }

        $this->sortMaterialPreset($id, $current + ($direction === 'up' ? -1 : 1));
    }

    public function sortMaterialPreset(int $id, int $position): void
    {
        $this->saveMaterialChange(
            fn (): bool => app(ArtworkMaterialPresetService::class)->move($id, $position),
            'Material order updated',
        );
    }

    private function saveMaterialChange(\Closure $mutation, string $title): bool
    {
        try {
            $changed = $mutation();
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Material not saved', $exception);

            return false;
        }

        if ($changed) {
            app(AdminNotifier::class)->notification(title: $title, status: 'success');
        }

        return $changed;
    }
}
