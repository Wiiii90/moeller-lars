<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Filament\Resources\CvEntries\CvEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCvEntry extends CreateRecord
{
    protected static string $resource = CvEntryResource::class;
}
