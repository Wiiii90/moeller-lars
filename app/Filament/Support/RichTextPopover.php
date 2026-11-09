<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaPreviewVariant;
use App\Models\MediaAsset;
use Filament\Schemas\Components\View;
use Filament\Support\Components\Attributes\ExposedLivewireMethod;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Support\Facades\Crypt;
use Livewire\Attributes\Renderless;

final class RichTextPopover extends View
{
    #[ExposedLivewireMethod]
    #[Renderless]
    public function beginStorageSelection(string $opening): bool
    {
        if (! preg_match('/^[a-f0-9-]{36}$/', $opening)) {
            return false;
        }

        $this->state($opening);

        return true;
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    public function endStorageSelection(string $opening): void
    {
        if ($this->getState() === $opening) {
            $this->state(null);
        }
    }

    #[ExposedLivewireMethod]
    #[Renderless]
    /** @return array{id: string, src: string, alt: string}|null */
    public function validateStorageImage(string $payload, string $opening): ?array
    {
        try {
            $selection = json_decode(Crypt::decryptString($payload), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|\JsonException) {
            return null;
        }

        if (! is_array($selection) || ($selection['owner'] ?? null) !== $this->getLivewire()->getId()
            || ($selection['editor'] ?? null) !== $this->getKey() || ($selection['opening'] ?? null) !== $opening
            || $this->getState() !== $opening) {
            return null;
        }
        $id = filter_var($selection['asset'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id === false) {
            return null;
        }

        $asset = MediaAsset::query()->whereKey($id)->where('state', 'available')
            ->where('mime_type', 'like', 'image/%')->with('variants')->first();
        $variant = $asset instanceof MediaAsset ? MediaPreviewVariant::imageThumbnail($asset) : null;
        if (! $asset instanceof MediaAsset || $variant === null) {
            return null;
        }

        return [
            'id' => (string) $id,
            'src' => route('admin.media.variant', $variant),
            'alt' => (string) ($asset->getAttribute('alt_text') ?: ''),
        ];
    }
}
