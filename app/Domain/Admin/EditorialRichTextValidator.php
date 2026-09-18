<?php

namespace App\Domain\Admin;

use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Content\UnsafeRichTextException;
use Illuminate\Validation\ValidationException;

final class EditorialRichTextValidator
{
    public function __construct(private readonly SafeRichTextRenderer $renderer) {}

    public function validate(mixed $source, string $field, bool $allowEmbeddedMedia = false): void
    {
        if ($source === null || $source === '') {
            return;
        }

        if (! is_string($source)) {
            throw ValidationException::withMessages([
                $field => 'Rich-text content must be text.',
            ]);
        }

        try {
            $this->renderer->assertValid($source, allowEmbeddedMedia: $allowEmbeddedMedia);
        } catch (UnsafeRichTextException) {
            throw ValidationException::withMessages([
                $field => 'This text contains unsupported formatting, media or an unsafe link.',
            ]);
        }
    }
}
