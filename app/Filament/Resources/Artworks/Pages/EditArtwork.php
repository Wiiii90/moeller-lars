<?php

namespace App\Filament\Resources\Artworks\Pages;

use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Pages\SitePages;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\ArtworkEditDialog;
use App\Models\Artwork;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditArtwork extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = ArtworkResource::class;

    public bool $returnToGallery = false;

    public function mount(int|string $record): void
    {
        parent::mount($record);
        $this->returnToGallery = request()->integer('gallery') > 0;
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        /** @var Artwork $artwork */
        $artwork = $this->getRecord();

        return app(ArtworkEditDialog::class)->fill($artwork);
    }

    /** @param array<string, mixed> $data */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var Artwork $record */
        $changed = app(ArtworkEditDialog::class)->save($record, $data, allowGalleryAssignment: true);
        $this->adminEditorSetMutationChanged($changed);

        return $record->fresh();
    }

    protected function adminEditorSavedNotificationTitle(): string
    {
        return 'Artwork saved';
    }

    protected function getRedirectUrl(): string
    {
        $galleryId = $this->artworkRecord()->getAttribute('artwork_category_id');
        if ($galleryId !== null) {
            $galleryUrl = GalleryWorkspace::getUrl( ['gallery' => (int) $galleryId]);
            if ($this->returnToGallery) {
                return $galleryUrl;
            }

            return $this->editorReturnUrl($galleryUrl);
        }

        return $this->editorReturnUrl(SitePages::getUrl());
    }

    private function artworkRecord(): Artwork
    {
        /** @var Artwork $record */
        $record = $this->getRecord();

        return $record;
    }
}
