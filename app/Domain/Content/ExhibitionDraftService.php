<?php

namespace App\Domain\Content;

use App\Models\Exhibition;

final class ExhibitionDraftService
{
    public function __construct(
        private readonly ExhibitionEditorialService $editorial,
    ) {}

    /** @param array<string,mixed> $data */
    public function create(array $data): Exhibition
    {
        return $this->editorial->createDraft($data);
    }
}
