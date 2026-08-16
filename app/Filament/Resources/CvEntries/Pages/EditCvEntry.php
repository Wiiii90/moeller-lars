<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Filament\Resources\CvEntries\CvEntryResource;
use Filament\Resources\Pages\EditRecord;

class EditCvEntry extends EditRecord
{
    protected static string $resource = CvEntryResource::class;
}
