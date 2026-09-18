<?php

use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaIntegrityService;
use App\Domain\Migration\LegacyArtworkManifestImporter;
use App\Domain\Migration\LegacyMigrationValidator;
use App\Domain\Migration\LegacyPublicCvImporter;
use App\Domain\Migration\LegacyPublicProfileImporter;
use App\Domain\Migration\LegacyPublicProfileMediaValidator;
use App\Domain\Migration\SiteSectionMigrationValidator;
use App\Models\MediaAsset;
use App\Models\User;
use App\Support\PulseReport;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('pulse:report {--hours=24 : Lookback window in hours (1-168)} {--format=markdown : Output format: markdown or json} {--limit=50 : Maximum rows per aggregate section (1-100)}', function (PulseReport $pulseReport) {
    $hours = filter_var($this->option('hours'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 168],
    ]);
    if ($hours === false) {
        $this->error('--hours must be an integer between 1 and 168.');

        return 64;
    }

    $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
        'options' => ['min_range' => 1, 'max_range' => 100],
    ]);
    if ($limit === false) {
        $this->error('--limit must be an integer between 1 and 100.');

        return 64;
    }

    $format = strtolower(trim((string) $this->option('format')));
    if (! in_array($format, ['markdown', 'json'], true)) {
        $this->error('--format must be either markdown or json.');

        return 64;
    }

    $report = $pulseReport->build((int) $hours, (int) $limit);

    if ($format === 'json') {
        $this->line(json_encode(
            $report,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR,
        ));

        return 0;
    }

    $this->line($pulseReport->toMarkdown($report));

    return 0;
})->purpose('Export a read-only Laravel Pulse telemetry snapshot as Markdown or JSON');

Artisan::command('media:measure-capacity', function (MediaCapacityService $capacity) {
    $capacity->forgetCachedSnapshot();
    $snapshot = $capacity->cachedSnapshot();

    $this->line(json_encode([
        'status' => $snapshot['status'] ?? 'unavailable',
        'measurement_available' => (bool) ($snapshot['measurement_available'] ?? false),
        'authoritative_bytes' => $snapshot['authoritative_bytes'] ?? null,
        'generated_bytes' => $snapshot['generated_bytes'] ?? null,
        'remaining_bytes' => $snapshot['remaining_bytes'] ?? null,
        'quota_bytes' => $snapshot['quota_bytes'] ?? null,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Refresh the cached authoritative Storage capacity snapshot');

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
    if ($password !== $confirmation) {
        throw new RuntimeException('Admin password confirmation does not match.');
    }
    if (User::query()->whereRaw('LOWER(email) = ?', [$email])->exists()) {
        throw new RuntimeException('A user with this email already exists.');
    }

    $passwordValidator = Validator::make(
        ['password' => $password],
        ['password' => ['required', Password::default()]],
    );

    if ($passwordValidator->fails()) {
        throw new RuntimeException($passwordValidator->errors()->first('password'));
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

    $this->info("Imported {$count} verified legacy Vita source rows into Biography/Exhibition targets, public profile details and the verified Vita portrait.");
})->purpose('Import verified public legacy Vita/profile content and portrait after the artwork snapshot import');

Artisan::command('legacy:import-artworks {manifest} {media-root}', function (LegacyArtworkManifestImporter $importer) {
    $result = $importer->import(
        (string) $this->argument('manifest'),
        (string) $this->argument('media-root'),
    );

    $this->info("Imported {$result['categories']} categories, {$result['artworks']} artworks and {$result['media']} original media assets.");
})->purpose('Import a reviewed legacy artwork manifest and authoritative original media');

Artisan::command('legacy:validate {manifest}', function (
    LegacyMigrationValidator $validator,
    LegacyPublicProfileMediaValidator $profileMediaValidator,
    SiteSectionMigrationValidator $siteSectionValidator,
) {
    $manifestPath = (string) $this->argument('manifest');
    $result = $validator->validate($manifestPath);
    $profileMedia = $profileMediaValidator->validate($manifestPath);
    $siteSections = $siteSectionValidator->validate();

    $result['source'] = [
        ...$result['source'],
        'profile_media' => $profileMedia['source'],
        'site_sections' => $siteSections['source'],
    ];
    $result['target'] = [
        ...$result['target'],
        'profile_media' => $profileMedia['target'],
        'site_sections' => $siteSections['target'],
    ];
    $result['errors'] = [...$result['errors'], ...$profileMedia['errors'], ...$siteSections['errors']];
    $result['ok'] = $result['errors'] === [];

    $json = json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    $this->line($json);

    return $result['ok'] ? 0 : 1;
})->purpose('Validate imported legacy content, media and canonical SiteSection projection against the reviewed source state');
