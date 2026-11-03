<?php

namespace App\Filament\Support;

use App\Domain\Media\MediaTypePolicy;
use Filament\Forms\Components\ViewField;

/**
 * Shared resumable transfer control for native Filament action/form schemas.
 * The value is the canonical MediaAsset ID, not a Livewire temporary file.
 */
final class ResumableMediaField
{
    public static function make(string $name, string $label, bool $imagesOnly = false): ViewField
    {
        return ViewField::make($name)
            ->label($label)
            ->view('filament.forms.components.resumable-media-upload')
            ->viewData([
                'mediaAccept' => implode(',', $imagesOnly
                    ? MediaTypePolicy::IMAGE_MIME_TYPES
                    : [...MediaTypePolicy::IMAGE_MIME_TYPES, ...MediaTypePolicy::VIDEO_MIME_TYPES]),
                'imagesOnly' => $imagesOnly,
            ])
            ->columnSpanFull();
    }
}
