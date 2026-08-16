<?php

namespace App\Domain\Content;

use DomainException;

final class UnsafeRichTextException extends DomainException
{
    public static function unsupportedSyntax(): self
    {
        return new self('Unsupported rich-text syntax.');
    }

    public static function unsafeLink(): self
    {
        return new self('Unsafe rich-text link.');
    }
}
