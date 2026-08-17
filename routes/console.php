<?php

use App\Domain\Media\MediaIntegrityService;
use App\Domain\Migration\LegacyArtworkManifestImporter;
use App\Domain\Migration\LegacyMigrationValidator;
use App\Domain\Migration\LegacyPublicCvImporter;
use App\Domain\Migration\LegacyPublicProfileImporter;
use App\Domain\Migration\LegacyPublicProfileMediaValidator;
use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('admin:provision {--name=} {--email=}', function () {
    $name = trim((string) ($this->option('name') ?: $this->ask('Name')));
    $email = strtolower(trim((string) ($this->option('email') ?: $this->ask('Email'))));
    $password = (string) $this->secret('Password');
    $confirmation = (string) $this->secret('Confirm password');

    if ($name === '') {
        throw new RuntimeException('Admin name is required.');
    }
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('Admin email is invalid.');
    }
    if (strlen($password) < 12) {
        throw new RuntimeException('Admin password must contain at least 12 characters.');
    }
    if ($password !== $confirmation) {
        throw new RuntimeException('Admin password confirmation does not match.');
    }
    if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
        throw new RuntimeException('A user with this email already exists.');
    }

    DB::transaction(function () use ($name, $email, $password): void {
        $user = User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => $password,
        ]);
        $user->forceFill(['is_admin' => true])->save();
    });

    $this->info('Admin account created.');
})->purpose('Create the first explicitly authorized administration account');

Artisan::command('media:verify', function (MediaIntegrityService $integrity) {
    $checked = 0;
    $failures = [];

    foreach (MediaAsset::query()->orderBy('id')->cursor() as $asset) {
        $checked++;
        $issues = $integrity->issues($asset);
        if ($issues !== []) {
            $failures[] = [
                'media_asset_id' => (int) $asset->getKey(),
                'issues' => $issues,
            ];
        }
    }

    $result = [
        'ok' => $failures === [],
        'checked' => $checked,
        'failures' => $failures,
    ];

    $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return $result['ok'] ? 0 : 1;
})->purpose('Verify stored media files against database integrity metadata');

Artisan::command('legacy:import-public-cv {manifest} {media-root}', function (LegacyPublicCvImporter $cvImporter, LegacyPublicProfileImporter $profileImporter) {
    $count = DB::transaction(function () use ($cvImporter, $profileImporter): int {
        $count = $cvImporter->import();
        $profileImporter->import(
            (string) $this->argument('manifest'),
            (string) $this->argument('media-root'),
        );

        return $count;
    });

    $this->info("Imported {$count} verified legacy CV entries, public profile details and the verified Vita portrait.");
})->purpose('Import verified public legacy CV/profile content and portrait after the artwork snapshot import');

Artisan::command('legacy:import-artworks {manifest} {media-root}', function (LegacyArtworkManifestImporter $importer) {
    $result = $importer->import(
        (string) $this->argument('manifest'),
        (string) $this->argument('media-root'),
    );

    $this->info("Imported {$result['categories']} categories, {$result['artworks']} artworks and {$result['media']} original media assets.");
})->purpose('Import a reviewed legacy artwork manifest and authoritative original media');

Artisan::command('legacy:validate {manifest}', function (LegacyMigrationValidator $validator, LegacyPublicProfileMediaValidator $profileMediaValidator) {
    $manifestPath = (string) $this->argument('manifest');
    $result = $validator->validate($manifestPath);
    $profileMedia = $profileMediaValidator->validate($manifestPath);

    $result['source'] = [...$result['source'], 'profile_media' => $profileMedia['source']];
    $result['target'] = [...$result['target'], 'profile_media' => $profileMedia['target']];
    $result['errors'] = [...$result['errors'], ...$profileMedia['errors']];
    $result['ok'] = $result['errors'] === [];

    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $this->line($json);

    return $result['ok'] ? 0 : 1;
})->purpose('Validate imported legacy content and media against the reviewed source manifest');
