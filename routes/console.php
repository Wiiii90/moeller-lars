<?php

use App\Domain\Migration\LegacyPublicCvImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('legacy:import-public-cv', function (LegacyPublicCvImporter $importer) {
    $count = $importer->import();

    $this->info("Imported {$count} verified legacy CV entries.");
})->purpose('Import verified public legacy CV content into an empty target');
