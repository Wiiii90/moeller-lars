<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminAutoPublishPreferences;
use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\GalleryPresentation;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\AdminHelp;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\ArtworkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                        'artwork_page_layout' => $data['artwork_page_layout'] ?? GalleryPresentation::ARTWORK_PAGE_STANDARD,
                        'edge_navigation_mode' => $data['edge_navigation_mode'] ?? GalleryPresentation::EDGE_DISABLED,
                        'artwork_divider' => $data['artwork_divider'] ?? GalleryPresentation::DIVIDER_NONE,
                        // Sort changes are exclusively committed through the
                        // confirmed Action, never the Edit modal's autosave.
                        'sort_mode' => GalleryPresentation::sortMode($gallery),
                        'video_sound_enabled' => $data['video_sound_enabled'] ?? false,
                        'show_artwork_information' => $data['show_artwork_information'] ?? true,
                        'show_media_credits' => $data['show_media_credits'] ?? false,
                        'show_media_copyright' => $data['show_media_copyright'] ?? false,
                        'caption_layout' => $data['caption_layout'] ?? GalleryPresentation::CAPTION_RIGHTS_LEFT,
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
                $preferenceChanged = app(AdminAutoPublishPreferences::class)->update(
                    AdminAutoPublishPreferences::GALLERY,
                    (bool) ($data['auto_publish_new_artworks']
                        ?? app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::GALLERY)),
                );
                if ($changed || $preferenceChanged) {
                    app(AdminNotifier::class)->notification(
                        title: 'Gallery settings saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /**
     * The Gallery Stage and the existing full modal render exactly the same
     * control definitions. Only column count and save timing differ.
     * Homepage selection is deliberately absent: Home owns that setting.
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

        $autoPublish = AdminControl::toggle('auto_publish_new_artworks')
            ->label(AdminHelp::label(
                'Auto-publish new Artworks',
                'Automatic Artwork publishing',
                'Eligible new Artworks are published automatically; unfinished Artworks remain editable drafts.',
            ))
            ->live()
            ->afterStateUpdated(function (mixed $state): void {
                if (app(AdminAutoPublishPreferences::class)->update(
                    AdminAutoPublishPreferences::GALLERY, (bool) $state,
                )) {
                    app(AdminNotifier::class)->notification(
                        title: 'Gallery settings saved', status: 'success',
                    );
                }
            });

        if ($stage) {
            $autoPublish->columnSpanFull();
        }

        return Grid::make(['default' => 1, 'md' => 2])
            ->schema([
                $this->gallerySettingControl($name, 'name', $stage),
                $order,
                $this->gallerySettingControl(
                    AdminControl::toggle('show_artwork_information', 'Show Artwork information below each work'),
                    'show_artwork_information', $stage,
                ),
                $this->gallerySettingControl(
                    AdminControl::toggle('show_media_credits', 'Show authored Media File credits'),
                    'show_media_credits', $stage,
                ),
                $this->gallerySettingControl(
                    AdminControl::toggle('show_media_copyright', 'Show configured copyright notices'),
                    'show_media_copyright', $stage,
                ),
                $this->gallerySettingControl(
                    AdminControl::select('caption_layout', 'Caption arrangement')
                        ->options(GalleryPresentation::CAPTION_LAYOUTS)->required(! $stage),
                    'caption_layout', $stage,
                ),
                $this->gallerySettingControl(
                    AdminControl::toggle('video_sound_enabled', 'Allow visitors to unmute looping Gallery videos'),
                    'video_sound_enabled', $stage,
                ),
                $autoPublish,
                $this->gallerySettingControl(
                    AdminControl::select('artwork_page_layout', 'Artwork page layout')
                        ->options(GalleryPresentation::ARTWORK_PAGE_LAYOUTS)
                        ->default(GalleryPresentation::ARTWORK_PAGE_STANDARD)
                         ->placeholder(null)->selectablePlaceholder(false)
                        ->required(! $stage),
                    'artwork_page_layout', $stage,
                ),
                $this->gallerySettingControl(
                    AdminControl::select('artwork_divider', 'Between Artworks')
                        ->options(GalleryPresentation::DIVIDER_OPTIONS)
                        ->default(GalleryPresentation::DIVIDER_NONE)->required(! $stage),
                    'artwork_divider', $stage,
                ),
                $this->gallerySettingControl(
                    AdminControl::select('edge_navigation_mode', 'Artwork navigation at sequence ends')
                        ->options([
                            GalleryPresentation::EDGE_DISABLED => 'Disabled arrows (default)',
                            GalleryPresentation::EDGE_HIDDEN => 'Hide unavailable arrows',
                            GalleryPresentation::EDGE_CYCLIC => 'Cyclic (last ↔ first)',
                        ])
                        ->default(GalleryPresentation::EDGE_DISABLED)->required(! $stage),
                    'edge_navigation_mode', $stage,
                ),
            ]);
    }

    private function gallerySettingControl(TextInput|Select|Toggle $field, string $name, bool $stage): TextInput|Select|Toggle
    {
        if (! $stage) {
            return $field;
        }
        if ($name !== 'name') {
            $field->columnSpanFull();
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
            'artwork_page_layout' => GalleryPresentation::artworkPageLayout($gallery),
            'edge_navigation_mode' => GalleryPresentation::edgeMode($gallery),
            'artwork_divider' => GalleryPresentation::divider($gallery),
            'sort_mode' => GalleryPresentation::sortMode($gallery),
            'video_sound_enabled' => GalleryPresentation::allowVideoSound($gallery),
            'show_artwork_information' => GalleryPresentation::showArtworkInformation($gallery),
            'show_media_credits' => GalleryPresentation::showMediaCredits($gallery),
            'show_media_copyright' => GalleryPresentation::showMediaCopyright($gallery),
            'caption_layout' => GalleryPresentation::captionLayout($gallery),
            'auto_publish_new_artworks' => app(AdminAutoPublishPreferences::class)
                ->enabled(AdminAutoPublishPreferences::GALLERY),
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

    /** Stage controls save through the same single Gallery editorial owner. */
    public function updateGalleryStageSetting(string $field, mixed $value): void
    {
        $editable = [
            'name',
            'show_artwork_information',
            'show_media_credits',
            'show_media_copyright',
            'caption_layout',
            'video_sound_enabled',
            'artwork_page_layout',
            'artwork_divider',
            'edge_navigation_mode',
        ];
        if (! in_array($field, $editable, true)) {
            return;
        }

        /** @var ArtworkCategory $gallery */
        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
        try {
            $updated = app(GalleryEditorialService::class)->update($gallery, [$field => $value]);
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
