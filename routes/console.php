<?php

use App\Domain\Admin\AdminLocalHistoryResetService;
use App\Domain\Content\ExhibitionAddressNormalizer;
use App\Domain\Contact\ContactMessageRetentionService;
use App\Domain\Media\MediaCapacityService;
use App\Domain\Media\MediaThumbnailCutoverService;
use App\Domain\Media\MediaIntegrityService;
use App\Domain\Media\MediaRightsWorkingPreparation;
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
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

// Offline and opt-in pre-release consolidation. Never invoked by HTTP or deploy.
Artisan::command('media:thumbnails:consolidate {--apply : Convert GD thumbnails to libvips} {--rebaseline-checkpoints : Recompute pre-release checkpoint hashes while preserving their rows} {--invalidate-thumbnail-rights : Explicitly invalidate obsolete generated thumbnail rights selections}', function (MediaThumbnailCutoverService $cutover): int {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Thumbnail consolidation is only available on a local pre-release database.');
        return 1;
    }

    $preview = $cutover->preview();
    $this->info("Original images: {$preview['assets']} · Legacy thumbnails: {$preview['variants']} · Restorable checkpoints: {$preview['checkpoints']} · Rights selections: {$preview['rights_selections']}");

    if (! $this->option('apply')) {
        $this->comment('Preview only. --apply is required to convert the thumbnail registry.');
        if ($preview['checkpoints'] > 0) {
            $this->warn('--rebaseline-checkpoints is additionally required to preserve but rehash pre-release history.');
        }
        return 0;
    }

    try {
        $result = $cutover->apply((bool) $this->option('rebaseline-checkpoints'), (bool) $this->option('invalidate-thumbnail-rights'));
        $this->info("Converted {$result['converted']} thumbnails. Rebaselined {$result['rebased']} checkpoints; invalidated {$result['invalidated_rights']} thumbnail rights selections for offline re-preparation; reclaimed {$result['reclaimed']} unreferenced old derivative files.");
        return 0;
    } catch (\Throwable $exception) {
        $this->error($exception->getMessage());
        return 1;
    }
})->purpose('Preview or execute the explicit local libvips thumbnail consolidation');

// Contact messages are retained in this database for 180 days. The command
// is preview-only unless --apply is explicit. Existing mailboxes and backup
// retention are separate policies. No independent worker or polling loop.
Artisan::command('contact:purge {--apply : Delete contact messages older than 180 days}', function (ContactMessageRetentionService $retention): int {
    $cutoff = $retention->cutoff();
    $eligible = $retention->eligibleCount($cutoff);
    $this->info($eligible.' contact messages older than 180 days are eligible for deletion.');

    if (! $this->option('apply')) {
        $this->comment('Preview only. Use --apply to execute permanent database deletion.');

        return 0;
    }

    $deleted = $retention->purge($cutoff);
    $this->info($deleted.' expired contact messages deleted from the database.');

    return 0;
})->purpose('Preview or purge database contact messages after 180 days');

// Activated only where an operator runs Laravel's standard schedule:run.
// That runtime scheduler has not been verified on the server.
Schedule::command('contact:purge --apply')->daily()->withoutOverlapping()->onOneServer();

// Legacy Exhibition addresses are normalised only by explicit local action:
// the preview is the default; ambiguous strings and edited structured fields
// are not inferred, and existing geocoded coordinates remain untouched.
Artisan::command('exhibitions:normalize-addresses {--apply : Apply the displayed unambiguous changes}', function (ExhibitionAddressNormalizer $normalizer) {
    if (! app()->environment('local')) {
        $this->error('Address normalization is restricted to the local environment.');

        return 1;
    }

    $records = DB::table('exhibitions')
        ->where('legacy_source', LegacyPublicCvImporter::SOURCE)
        ->orderBy('position')
        ->get(['id', 'title', 'location_text', 'postal_code', 'city', 'country']);

    $candidates = [];
    $review = [];
    $preserved = 0;

    foreach ($records as $entry) {
        if (filled($entry->city) || filled($entry->postal_code) || filled($entry->country)) {
            $preserved++;

            continue;
        }

        $parsed = $normalizer->parse($entry->location_text);
        if ($parsed === null) {
            $review[] = [(int) $entry->id, (string) $entry->title, (string) ($entry->location_text ?? '')];

            continue;
        }

        $candidates[] = [
            'id' => (int) $entry->id,
            'title' => (string) $entry->title,
            'old' => $entry->location_text,
            'new' => $parsed,
        ];
    }

    $this->table(['ID', 'Exhibition', 'Street', 'Postal code', 'City'], array_map(
        static fn (array $item): array => [
            $item['id'], $item['title'], $item['new']['location_text'] ?? '—',
            $item['new']['postal_code'] ?? '—', $item['new']['city'] ?? '—',
        ],
        $candidates,
    ));

    if ($review !== []) {
        $this->warn('Ambiguous legacy locations require manual review; no changes will be guessed for them.');
        $this->table(['ID', 'Exhibition', 'Unstructured location'], $review);
    }

    $this->info(count($candidates).' candidates · '.count($review).' to review · '.$preserved.' already structured/preserved');

    if (! $this->option('apply')) {
        $this->comment('Preview only. Run with --apply to update eligible local records.');

        return 0;
    }

    $changed = DB::transaction(function () use ($candidates): int {
        $changed = 0;
        foreach ($candidates as $item) {
            $changed += DB::table('exhibitions')
                ->where('id', $item['id'])
                ->where('legacy_source', LegacyPublicCvImporter::SOURCE)
                ->where('location_text', $item['old'])
                ->whereNull('city')
                ->whereNull('postal_code')
                ->whereNull('country')
                ->update([...$item['new'], 'updated_at' => now()]);
        }

        return $changed;
    });

    $this->info($changed.' existing legacy Exhibitions normalized. Other records and map coordinates were preserved.');

    return 0;
})->purpose('Preview or apply conservative local legacy Exhibition address normalization');

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');


Artisan::command('admin:reset-local-history {--force : Skip the confirmation prompt}', function (AdminLocalHistoryResetService $reset) {
    if (! app()->environment('local')) {
        $this->error('admin:reset-local-history is available only in the local environment.');

        return 1;
    }

    if (! (bool) $this->option('force') && ! $this->confirm(
        'Delete local Activity, Undo receipts, notifications, feed pins and derived publication/audit history while preserving editorial content and publication snapshots?',
    )) {
        $this->info('Local admin history reset cancelled.');

        return 0;
    }

    $deleted = $reset->reset();

    $this->info('Local admin history reset complete. Editorial content and publication snapshots were preserved.');
    foreach ($deleted as $table => $count) {
        if ($count > 0) {
            $this->line(sprintf('%s: %d', $table, $count));
        }
    }

    return 0;
})->purpose('Clear disposable local admin history without deleting editorial content or publication snapshots');

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

// Explicit, bounded local rights-file backfill. Not scheduled or reachable
// from HTTP. Default is a read-only preview; --apply is separately gated.
Artisan::command('media:rights:prepare {--after=0 : Cursor media ID} {--limit=10 : Images per batch (1-20)} {--apply : Prepare selected Working image files}', function (MediaRightsWorkingPreparation $preparation): int {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Rights-file preparation is local/disposable-only.');
        return 1;
    }

    $after = filter_var($this->option('after'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
    $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if ($after === false || $limit === false) {
        $this->error('Use --after >= 0 and --limit from 1 to 20.');
        return 64;
    }

    $apply = (bool) $this->option('apply');
    if ($apply && (! config('media.rights_preparation_enabled', false) || config('media.rights_delivery_enabled', false))) {
        $this->error('Offline preparation must be explicitly enabled while public rights delivery remains disabled.');
        return 1;
    }

    $assets = MediaAsset::query()
        ->where('id', '>', $after)
        ->whereIn('mime_type', \App\Domain\Media\MediaTypePolicy::IMAGE_MIME_TYPES)
        ->orderBy('id')
        ->limit($limit)
        ->get(['id', 'original_filename']);
    $prepared = 0;
    $lastId = (int) $after;
    $candidates = $assets->pluck('id')->map(static fn ($id): int => (int) $id)->all();
    foreach ($assets as $asset) {
        $id = (int) $asset->getKey();
        if ($apply) {
            try {
                if ($preparation->prepare($id)) {
                    $prepared++;
                }
            } catch (\Throwable $exception) {
                $this->error('Rights preparation stopped at media #'.$id.': '.$exception->getMessage());
                $this->warn('Last completed cursor: --after='.$lastId.'. Re-run from that cursor after correcting the error.');

                return 1;
            }
        }
        $lastId = $id;
        if ($apply) {
            $this->line('Completed media #'.$id.'; resume cursor --after='.$lastId);
        }
    }
    $this->line(json_encode([
        'scanned' => $assets->count(),
        'prepared' => $prepared,
        'candidate_ids' => $candidates,
        'next_after' => (int) $lastId,
        'preview_only' => ! $apply,
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
    if (! $apply) {
        $this->comment('No rights files or Working selections changed. Apply is local/disposable only.');
    }

    return 0;
})->purpose('Preview or stage a bounded batch of image rights artifacts offline');

// Dedicated existing publication cleanup owner; no new scheduler/worker.
// Dry run is the default and no ordinary HTTP request uses this operation.
Artisan::command('media:rights:cleanup {--limit=10 : Maximum candidate count (1-20)} {--apply : Reclaim unreferenced generated rights files}', function (\App\Domain\Publication\PublicationMediaCleanupService $cleanup): int {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Rights artifact cleanup is restricted to local/disposable testing.');
        return 1;
    }

    $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 20]]);
    if ($limit === false) {
        $this->error('Provide --limit between 1 and 20.');
        return 64;
    }

    if (! config('media.rights_preparation_enabled', false)
        || config('media.rights_delivery_enabled', false)) {
        $this->error('Rights artifact inspection requires offline preparation ON and public delivery OFF.');
        return 1;
    }

    try {
        $candidates = $cleanup->rightsCleanupCandidates($limit);
        $result = [
            'unreferenced_registry' => count($candidates['unreferenced']),
            'orphaned_files' => count($candidates['orphan_keys']),
            'preview_only' => ! $this->option('apply'),
        ];
        if ($this->option('apply')) {
            $result = [...$result, ...$cleanup->reclaimRightsArtifacts($limit, true)];
        }
        $this->line(json_encode($result, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR));
        if (! $this->option('apply')) {
            $this->comment('Preview only: no generated files or rights selections changed.');
        }
        return 0;
    } catch (\Throwable $exception) {
        $this->error('Rights cleanup aborted: '.$exception->getMessage());
        return 1;
    }
})->purpose('Preview or reclaim unreferenced rights copies without touching source media');

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
