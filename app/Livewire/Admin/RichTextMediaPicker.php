<?php

namespace App\Livewire\Admin;

use App\Domain\Media\MediaPreviewVariant;
use App\Filament\Support\MediaAssetSelect;
use App\Models\MediaAsset;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Locked;
use Livewire\Component;

final class RichTextMediaPicker extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    #[Locked]
    public string $ownerId;

    #[Locked]
    public string $editorKey;

    public ?string $openingId = null;

    public ?int $mediaAssetId = null;

    public function boot(): void
    {
        $user = Filament::auth()->user();
        abort_unless($user && $user->canAccessPanel(Filament::getPanel('admin')), 403);
    }

    public function mount(string $ownerId, string $editorKey): void
    {
        $this->ownerId = $ownerId;
        $this->editorKey = $editorKey;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            MediaAssetSelect::makeId('mediaAssetId', 'Image from Storage', imagesOnly: true)
                ->hiddenLabel()
                ->nullable()
                ->live()
                ->afterStateUpdated(function (mixed $state, Select $field): void {
                    $id = filter_var($state, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
                    if ($id === false || ! is_string($this->openingId) || ! preg_match('/^[a-f0-9-]{36}$/', $this->openingId)) {
                        return;
                    }

                    $asset = MediaAsset::query()->whereKey($id)->where('state', 'available')
                        ->where('mime_type', 'like', 'image/%')->with('variants')->firstOrFail();
                    if (MediaPreviewVariant::imageThumbnail($asset) === null) {
                        return;
                    }

                    $payload = Crypt::encryptString(json_encode([
                        'owner' => $this->ownerId,
                        'editor' => $this->editorKey,
                        'opening' => $this->openingId,
                        'asset' => (int) $id,
                    ], JSON_THROW_ON_ERROR));
                    $field->state(null);
                    $this->dispatch('artist-rich-media-selected', opening: $this->openingId, payload: $payload);
                }),
        ]);
    }

    public function render(): View
    {
        return view('livewire.admin.rich-text-media-picker');
    }
}
