<?php

namespace App\Domain\Admin;

use App\Domain\Content\ExhibitionEditorialService;
use App\Models\Exhibition;

final class EditorialRecordService
{
    public function __construct(
        private readonly ExhibitionEditorialService $exhibitions,
    ) {}

    public function publish(Exhibition $record): Exhibition
    {
        return $this->exhibitions->publish($record);
    }

    public function unpublish(Exhibition $record): Exhibition
    {
        return $this->exhibitions->unpublish($record);
    }

    public function archive(Exhibition $record): Exhibition
    {
        return $this->exhibitions->archive($record);
    }

    public function restoreDraft(Exhibition $record): Exhibition
    {
        return $this->exhibitions->restoreDraft($record);
    }

    public function deleteExhibition(Exhibition $record): void
    {
        $this->exhibitions->delete($record);
    }

    public function canMove(Exhibition $record, string $direction): bool
    {
        return $this->exhibitions->canMove($record, $direction);
    }

    public function move(Exhibition $record, string $direction): bool
    {
        return $this->exhibitions->move($record, $direction);
    }
}
