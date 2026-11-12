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
use Filament\Schemas\Components\Grid;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;

trait GalleryWorkspaceUploadSettings
{
    public function gallerySettingsAction(): Action
    {
        $action = Action::make('gallerySettings')
            ->label('Settings')
            ->fillForm(function (): array {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);

                return [
                    'name' => (string) $gallery->getAttribute('name'),
                    'show_on_home' => (bool) $gallery->getAttribute('show_on_home'),
                    'landscape_detail_enabled' => GalleryPresentation::enabled($gallery),
                    'portrait_detail_enabled' => GalleryPresentation::portraitEnabled($gallery),
                    'artwork_page_surface_mode' => GalleryPresentation::artworkPageSurfaceMode($gallery),
                    'artwork_menu_surface_wide' => GalleryPresentation::wideArtworkMenu($gallery),
                    'edge_navigation_mode' => GalleryPresentation::edgeMode($gallery),
                    'artwork_divider' => GalleryPresentation::divider($gallery),
                    'sort_mode' => GalleryPresentation::sortMode($gallery),
                    'video_sound_enabled' => GalleryPresentation::allowVideoSound($gallery),
                    'show_artwork_information' => GalleryPresentation::showArtworkInformation($gallery),
                    'show_media_credits' => GalleryPresentation::showMediaCredits($gallery),
                    'show_media_copyright' => GalleryPresentation::showMediaCopyright($gallery),
                    'caption_layout' => GalleryPresentation::captionLayout($gallery),
                    'auto_publish_new_artworks' => app(AdminAutoPublishPreferences::class)->enabled(AdminAutoPublishPreferences::GALLERY),
                ];
            })
            ->schema([
                // The shared Gallery controls use two columns; the title spans the row.
                // Home-only eligibility is edited exclusively in the Home workspace.
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema([
                        AdminControl::text('name', 'Gallery title')
                            ->required()
                            ->maxLength(160)
                            ->columnSpanFull(),
                        AdminControl::toggle('show_on_home', 'Eligible for homepage presentation'),
                        AdminControl::select('sort_mode')
                            ->label(AdminHelp::label(
                                'Artwork order',
                                'Gallery artwork ordering',
                                'Manual: drag or edit positions; new artworks appear first. Newest artwork date: the Gallery always displays works by date instead of manual position. To sort manual positions only once, save Manual first and use Sort once by artwork date.',
                            ))
                            ->options(GalleryPresentation::SORT_OPTIONS)
                            ->default(GalleryPresentation::SORT_MANUAL)
                            ->required(),
                        AdminControl::toggle('show_artwork_information', 'Show Artwork information below each work'),
                        AdminControl::toggle('show_media_credits', 'Show authored Media File credits'),
                        AdminControl::toggle('show_media_copyright', 'Show configured copyright notices'),
                        AdminControl::select('caption_layout', 'Caption arrangement')
                            ->options(GalleryPresentation::CAPTION_LAYOUTS)
                            ->required(),
                        AdminControl::toggle('video_sound_enabled', 'Allow visitors to unmute looping Gallery videos'),
                        AdminControl::toggle('auto_publish_new_artworks')
                            ->label(AdminHelp::label(
                                'Auto-publish new Artworks',
                                'Automatic Artwork publishing',
                                'Only available media with suitable ALT text and a published Gallery can be published. If the checks fail, creation is rejected without keeping a partial draft.',
                            ))
                            ->live()
                            ->afterStateUpdated(static function (mixed $state): void {
                                if (app(AdminAutoPublishPreferences::class)->update(
                                    AdminAutoPublishPreferences::GALLERY, (bool) $state,
                                )) {
                                    app(AdminNotifier::class)->notification(title: 'Gallery settings saved', status: 'success');
                                }
                            }),
                        AdminControl::toggle('landscape_detail_enabled', 'Expand landscape Artwork pages'),
                        AdminControl::toggle('portrait_detail_enabled', 'Expand portrait Artwork pages'),
                        AdminControl::select('artwork_page_surface_mode', 'Artwork page surface')
                            ->options(GalleryPresentation::ARTWORK_SURFACE_OPTIONS)
                            ->default(GalleryPresentation::ARTWORK_SURFACE_INHERIT)
                            ->required(),
                        AdminControl::toggle('artwork_menu_surface_wide', 'Match Menu surface to Artwork page width')
                            ->default(false),
                        AdminControl::select('artwork_divider', 'Between Artworks')
                            ->options(GalleryPresentation::DIVIDER_OPTIONS)
                            ->default(GalleryPresentation::DIVIDER_NONE)
                            ->required(),
                        AdminControl::select('edge_navigation_mode', 'Artwork navigation at sequence ends')
                            ->options([
                                GalleryPresentation::EDGE_DISABLED => 'Disabled arrows (default)',
                                GalleryPresentation::EDGE_HIDDEN => 'Hide unavailable arrows',
                                GalleryPresentation::EDGE_CYCLIC => 'Cyclic (last ↔ first)',
                            ])
                            ->default(GalleryPresentation::EDGE_DISABLED)
                            ->required(),
                    ]),
            ])
            ->modalHeading('Gallery settings')
            ->extraModalFooterActions([
                Action::make('sortArtworksByDateOnce')
                    ->label('Sort once by artwork date')
                    ->color('gray')
                    ->disabled(fn (): bool => ($this->galleryContext['sort_mode'] ?? GalleryPresentation::SORT_MANUAL) !== GalleryPresentation::SORT_MANUAL)
                    ->tooltip('Save Manual ordering first. This replaces manual positions with a one-time date sort; future edits remain manual.')
                    ->action(function (): void {
                        /** @var ArtworkCategory $gallery */
                        $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                        if (GalleryPresentation::sortMode($gallery) !== GalleryPresentation::SORT_MANUAL) {
                            throw ValidationException::withMessages(['sort_mode' => 'Save Manual ordering first.']);
                        }
                        $sortedIds = GalleryPresentation::orderArtworksByDate($gallery->artworks()->getQuery())
                            ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
                        $currentIds = $gallery->artworks()->orderBy('position')->orderBy('id')
                            ->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
                        if ($sortedIds === $currentIds) {
                            return;
                        }
                        app(GalleryEditorialService::class)->reorderArtworks($gallery, $sortedIds);
                        $this->loadGallery((int) $gallery->getKey());
                        $this->refreshWorkspaceAfterMutation();
                        app(AdminNotifier::class)->notification(
                            title: 'Manual artwork order sorted by date',
                            status: 'success',
                        );
                    }),
            ])
            ->action(function (array $data): void {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                $service = app(GalleryEditorialService::class);

                try {
                    $updated = $service->update($gallery, [
                        'name' => $data['name'],
                        'show_on_home' => (bool) ($data['show_on_home'] ?? false),
                        'landscape_detail_enabled' => $data['landscape_detail_enabled'] ?? false,
                        'portrait_detail_enabled' => $data['portrait_detail_enabled'] ?? false,
                        'artwork_page_surface_mode' => $data['artwork_page_surface_mode'] ?? GalleryPresentation::ARTWORK_SURFACE_INHERIT,
                        'artwork_menu_surface_wide' => $data['artwork_menu_surface_wide'] ?? false,
                        'edge_navigation_mode' => $data['edge_navigation_mode'] ?? GalleryPresentation::EDGE_DISABLED,
                        'artwork_divider' => $data['artwork_divider'] ?? GalleryPresentation::DIVIDER_NONE,
                        'sort_mode' => $data['sort_mode'] ?? GalleryPresentation::SORT_MANUAL,
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
