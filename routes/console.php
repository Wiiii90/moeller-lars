<?php

use App\Domain\Migration\LegacyArtworkManifestImporter;
use App\Domain\Migration\LegacyMigrationValidator;
use App\Domain\Migration\LegacyPublicCvImporter;
use App\Domain\Migration\LegacyPublicProfileImporter;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('legacy:import-public-cv', function (LegacyPublicCvImporter $cvImporter, LegacyPublicProfileImporter $profileImporter) {
    $count = $cvImporter->import();
    $profileImporter->import();

    $this->info("Imported {$count} verified legacy CV entries and public profile details.");
})->purpose('Import verified public legacy CV and profile content into an empty target');

Artisan::command('legacy:import-artworks {manifest} {media-root}', function (LegacyArtworkManifestImporter $importer) {
    $result = $importer->import(
        (string) $this->argument('manifest'),
        (string) $this->argument('media-root'),
    );

    $this->info("Imported {$result['categories']} categories, {$result['artworks']} artworks and {$result['media']} original media assets.");
})->purpose('Import a reviewed legacy artwork manifest and authoritative original media');

Artisan::command('legacy:validate {manifest}', function (LegacyMigrationValidator $validator) {
    $result = $validator->validate((string) $this->argument('manifest'));
    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $this->line($json);

    return $result['ok'] ? 0 : 1;
})->purpose('Validate imported legacy content and media against the reviewed source manifest');
