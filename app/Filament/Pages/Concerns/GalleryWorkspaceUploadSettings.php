<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Artwork\GalleryPresentation;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMaterialPreset;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;
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
                    'slug' => (string) $gallery->getAttribute('slug'),
                    'description' => $gallery->getAttribute('description'),
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
                ];
            })
            ->schema([
                Grid::make()
                    ->columns(['md' => 2])
                    ->schema([
                        TextInput::make('name')->label('Gallery title')->required()->maxLength(160),
                        TextInput::make('slug')
                            ->label('Public URL slug')
                            ->required()
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                        Textarea::make('description')->rows(5)->maxLength(10000)->nullable()->columnSpanFull(),
                        Toggle::make('show_on_home')->label('Eligible for homepage presentation')->columnSpanFull(),
                        Select::make('sort_mode')
                            ->label('Artwork order')
                            ->options(GalleryPresentation::SORT_OPTIONS)
                            ->default(GalleryPresentation::SORT_MANUAL)
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('show_artwork_information')
                            ->label('Show Artwork information below each work')
                            ->columnSpanFull(),
                        Toggle::make('show_media_credits')
                            ->label('Show authored Media File credits')
                            ->columnSpanFull(),
                        Toggle::make('show_media_copyright')
                            ->label('Show configured copyright notices')
                            ->columnSpanFull(),
                        Select::make('caption_layout')
                            ->label('Caption arrangement')
                            ->options(GalleryPresentation::CAPTION_LAYOUTS)
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('video_sound_enabled')
                            ->label('Allow visitors to unmute looping Gallery videos')
                            ->columnSpanFull(),
                        Toggle::make('landscape_detail_enabled')
                            ->label('Expand landscape Artwork pages')
                            ->columnSpanFull(),
                        Toggle::make('portrait_detail_enabled')
                            ->label('Expand portrait Artwork pages')
                            ->columnSpanFull(),
                        Select::make('artwork_page_surface_mode')
                            ->label('Artwork page surface')
                            ->options(GalleryPresentation::ARTWORK_SURFACE_OPTIONS)
                            ->default(GalleryPresentation::ARTWORK_SURFACE_INHERIT)
                            ->required()
                            ->columnSpanFull(),
                        Toggle::make('artwork_menu_surface_wide')
                            ->label('Match Menu surface to Artwork page width')
                            ->helperText('Only the Menu background becomes wider. Navigation items keep their usual size and spacing.')
                            ->default(false)
                            ->columnSpanFull(),
                        Select::make('artwork_divider')
                            ->label('Between Artworks')
                            ->options(GalleryPresentation::DIVIDER_OPTIONS)
                            ->default(GalleryPresentation::DIVIDER_NONE)
                            ->required()
                            ->columnSpanFull(),
                        Select::make('edge_navigation_mode')
                            ->label('Artwork navigation at sequence ends')
                            ->options([
                                GalleryPresentation::EDGE_DISABLED => 'Disabled arrows (default)',
                                GalleryPresentation::EDGE_HIDDEN => 'Hide unavailable arrows',
                                GalleryPresentation::EDGE_CYCLIC => 'Cyclic (last ↔ first)',
                            ])
                            ->default(GalleryPresentation::EDGE_DISABLED)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->modalHeading('Gallery settings')
            ->action(function (array $data): void {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                $currentSlug = (string) $gallery->getAttribute('slug');
                $service = app(GalleryEditorialService::class);

                try {
                    $changed = DB::transaction(function () use ($service, $gallery, $currentSlug, $data): bool {
                        $updated = $service->update($gallery, [
                            'name' => $data['name'],
                            'description' => $data['description'] ?? null,
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

                        $newSlug = trim((string) ($data['slug'] ?? ''));
                        if ($newSlug !== $currentSlug) {
                            $service->changeSlug($gallery, $newSlug);
                            $changed = true;
                        }

                        return $changed;
                    });
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
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Gallery settings saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Default);
    }

    public function materialPresetsAction(): Action
    {
        $action = Action::make('materialPresets')
            ->label('Materials')
            ->fillForm(fn (): array => [
                'presets' => ArtworkMaterialPreset::query()->orderBy('name')->pluck('name')->all(),
            ])
            ->schema([
                Repeater::make('presets')
                    ->label('Reusable material presets')
                    ->simple(
                        TextInput::make('name')
                            ->label('Material')
                            ->required()
                            ->maxLength(240),
                    )
                    ->addActionLabel('Add material'),
            ])
            ->modalHeading('Material presets')
            ->action(function (array $data): void {
                $changed = app(ArtworkMaterialPresetService::class)->sync(is_array($data['presets'] ?? null) ? $data['presets'] : []);
                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Material presets saved',
                        status: 'success',
                    );
                }
            });

        // Repeater add/delete/reorder operations are a draft collection.
        // Commit once using the shared Command lifecycle, not Edit blur events.
        return AdminDialog::command($action, 'Apply changes', AdminDialogSize::Default);
    }
}
