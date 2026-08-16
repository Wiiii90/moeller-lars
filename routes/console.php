<?php

use App\Domain\Migration\LegacyArtworkManifestImporter;
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

Artisan::command('legacy:import-artworks {manifest} {media-root}', function (LegacyArtworkManifestImporter $importer) {
    $result = $importer->import(
        (string) $this->argument('manifest'),
        (string) $this->argument('media-root'),
    );

    $this->info("Imported {$result['categories']} categories, {$result['artworks']} artworks and {$result['media']} original media assets.");
})->purpose('Import a reviewed legacy artwork manifest and authoritative original media');
