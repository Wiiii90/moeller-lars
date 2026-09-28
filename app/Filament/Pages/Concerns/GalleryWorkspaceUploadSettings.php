<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Artwork\ArtworkMaterialPresetService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\Dialogs\InteractsWithAdminEditDialogAutosave;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMaterialPreset;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Illuminate\Support\Facades\DB;

trait GalleryWorkspaceUploadSettings
{
    use InteractsWithAdminEditDialogAutosave;

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
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->helperText('Changing this keeps the previous Gallery URL as a redirect.'),
                        Textarea::make('description')->rows(5)->maxLength(10000)->nullable()->columnSpanFull(),
                        Toggle::make('show_on_home')->label('Eligible for homepage presentation')->columnSpanFull(),
                    ]),
            ])
            ->modalHeading('Gallery settings')
            ->action(function (array $data): void {
                /** @var ArtworkCategory $gallery */
                $gallery = ArtworkCategory::query()->findOrFail((int) $this->galleryContext['id']);
                $currentSlug = (string) $gallery->getAttribute('slug');
                $service = app(GalleryEditorialService::class);

                $changed = DB::transaction(function () use ($service, $gallery, $currentSlug, $data): bool {
                    $updated = $service->update($gallery, [
                        'name' => $data['name'],
                        'description' => $data['description'] ?? null,
                        'show_on_home' => (bool) ($data['show_on_home'] ?? false),
                    ]);
                    $changed = $updated->wasChanged();

                    $newSlug = trim((string) ($data['slug'] ?? ''));
                    if ($newSlug !== $currentSlug) {
                        $service->changeSlug($gallery, $newSlug);
                        $changed = true;
                    }

                    return $changed;
                });

                $this->loadGallery((int) $gallery->getKey());
                $this->loadMoveTargets();
                $this->refreshWorkspaceAfterMutation();
                if ($changed) {
                    app(\App\Domain\Admin\AdminNotifier::class)->transient()->title('Gallery settings saved')->success()->send();
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
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
                    ->addActionLabel('Add material')
                    ->helperText('Removing a preset only removes the suggestion. Existing artworks keep their saved Material text.'),
            ])
            ->modalHeading('Material presets')
            ->action(function (array $data): void {
                $changed = app(ArtworkMaterialPresetService::class)->sync(is_array($data['presets'] ?? null) ? $data['presets'] : []);
                if ($changed) {
                    app(\App\Domain\Admin\AdminNotifier::class)->transient()->title('Material presets saved')->success()->send();
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Default);
    }
}
