<?php

// Independent application process: no RefreshDatabase transaction can hide fixtures
// or hold relation locks on behalf of the controlling PHPUnit process.
use App\Domain\Admin\AdminUndoService;
use App\Domain\Artwork\GalleryEditorialService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Media\MediaIngestService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Domain\Storage\SiteStorageReclaimService;
use App\Filament\Pages\Activity;
use App\Models\AdminActionReceipt;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\PublicationCheckpoint;
use App\Models\User;
use App\Support\LocalPreviewDatabaseGuard;
use Filament\Facades\Filament;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

require dirname(__DIR__, 2).'/vendor/autoload.php';

function emit(array $message): void
{
    echo json_encode($message, JSON_THROW_ON_ERROR)."\n";
    flush();
}

function undoThroughActivity(array $request): array
{
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $component = Livewire::test(Activity::class)
        ->mountAction('undoActivity', ['id' => $request['event']])
        ->callMountedAction();
    $events = collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification');
    $notifications = AdminNotification::query()->whereIn('id', $events->pluck('params.notification.notification_id'))->get();

    return [
        'notifications' => $notifications->map->only(['id', 'status'])->all(),
        'header_events' => $events->count(),
    ];
}

try {
    foreach (['GITHUB_ACTIONS', 'MOELLER_LARS_DISPOSABLE_TEST_DATABASE'] as $flag) {
        putenv($flag);
        unset($_ENV[$flag], $_SERVER[$flag]);
    }
    $root = realpath((string) getenv('CONCURRENCY_RUNTIME'));
    if (! $root || ! str_contains(str_replace('\\', '/', $root), '/storage/framework/testing/concurrency-')) {
        throw new RuntimeException('An explicitly isolated concurrency runtime is required.');
    }
    // Apply the actual PHPUnit rules before boot, including force=true overrides.
    foreach (simplexml_load_file(dirname(__DIR__, 2).'/phpunit.xml')->php->env as $entry) {
        $name = (string) $entry['name'];
        if ((string) $entry['force'] === 'true' || getenv($name) === false) {
            putenv($name.'='.(string) $entry['value']);
            $_ENV[$name] = $_SERVER[$name] = (string) $entry['value'];
        }
    }
    $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
    $app->useEnvironmentPath($root)->loadEnvironmentFrom('.env.testing');
    $app->make(Kernel::class)->bootstrap();
    $identity = DB::selectOne('SELECT current_database() AS db, current_user AS usr, inet_server_port() AS port, pg_backend_pid() AS pid, current_setting(\'server_version_num\') AS version');
    if ($identity->db !== getenv('CONCURRENCY_DATABASE') || $identity->usr !== getenv('CONCURRENCY_USER')
        || ! str_starts_with($identity->db, 'concurrency_') || (int) $identity->version < 170000 || (int) $identity->version >= 180000
        || (int) $identity->port !== 5432 || config('database.connections.pgsql.host') !== '127.0.0.1'
        || (string) config('database.connections.pgsql.port') !== getenv('CONCURRENCY_PORT')
        || config('database.connections.pgsql.url') || $app->configurationIsCached() || ! $app->environment('testing')
        || $app->environmentFilePath() !== $root.DIRECTORY_SEPARATOR.'.env.testing') {
        throw new RuntimeException('Effective Laravel/PDO/PHPUnit isolation mismatch.');
    }
    $paths = [storage_path(), Storage::disk('local')->path(''), Storage::disk('public')->path(''),
        config('cache.stores.file.path'), config('session.files'), config('view.compiled'),
        $app->getCachedConfigPath(), $app->getCachedPackagesPath(), $app->getCachedServicesPath(),
        $app->getCachedEventsPath(), $app->getCachedRoutesPath(), sys_get_temp_dir(), ini_get('upload_tmp_dir')];
    foreach ($paths as $path) {
        $normalized = str_replace('\\', '/', (string) $path);
        if (! str_starts_with($normalized, str_replace('\\', '/', $root).'/') || str_contains($normalized, '/../')) {
            throw new RuntimeException('Non-isolated runtime path: '.$normalized);
        }
        $directory = is_dir($path) ? $path : dirname($path);
        if (! is_dir($directory) || ! is_writable($directory)) {
            throw new RuntimeException('Runtime target is not writable: '.$directory);
        }
    }
    emit(['phase' => 'verified', 'identity' => (array) $identity, 'paths' => $paths]);
    foreach (['GITHUB_ACTIONS' => 'true', 'MOELLER_LARS_DISPOSABLE_TEST_DATABASE' => '1'] as $name => $value) {
        putenv($name.'='.$value);
        $_SERVER[$name] = $_ENV[$name] = $value;
    }
    LocalPreviewDatabaseGuard::assertDisposableTestContext();
    DB::statement("SET lock_timeout = '12s'");
    DB::statement("SET statement_timeout = '20s'");
    $request = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
    $operation = $request['operation'];
    if ($operation === 'prepare') {
        LocalPreviewDatabaseGuard::assertDestructiveCommandIsAllowed();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $actor = User::factory()->admin()->create();
        auth()->login($actor);
        $gallery = app(GalleryEditorialService::class)->create(['name' => 'Original Gallery', 'slug' => 'concurrency-gallery', 'description' => null]);
        $first = app(PublicationService::class)->commit($actor, 'Original');
        app(GalleryEditorialService::class)->update($gallery, ['name' => 'Renamed Gallery', 'description' => null]);
        $event = AuditEvent::query()->where('action', 'artwork_category.updated')->latest('id')->firstOrFail();
        $receipt = AdminActionReceipt::query()->where('audit_event_id', $event->id)->firstOrFail();
        $fixture = ['actor' => $actor->id, 'gallery' => $gallery->id, 'receipt' => $receipt->id, 'event' => $event->id, 'first' => $first->id];
        for ($index = 0; $index < 10; $index++) {
            AdminNotification::query()->create(['user_id' => $actor->id, 'source_id' => 'existing:'.$index, 'status' => 'info', 'title' => 'Existing notification']);
        }
        if (($request['scenario'] ?? '') === 'revert') {
            $second = app(PublicationService::class)->commit($actor, 'Second');
            app(GalleryEditorialService::class)->update($gallery->fresh(), ['name' => 'Third Gallery', 'description' => null]);
            $fixture['second'] = $second->id;
        }
        if (($request['scenario'] ?? '') === 'media') {
            $asset = app(MediaIngestService::class)->ingest(UploadedFile::fake()->image('retained.jpg', 64, 48));
            $fixture['asset'] = $asset->id;
            $fixture['key'] = $asset->storage_key;
            $fixture['sha'] = hash_file('sha256', Storage::disk(config('media.disk'))->path($asset->storage_key));
            $fixture['target'] = app(PublicationService::class)->commit($actor, 'Stored original')->id;
            app(MediaAssetEditorialService::class)->delete($asset);
            $fixture['deleted'] = app(PublicationService::class)->commit($actor, 'Deleted original')->id;
        }
        emit(['phase' => 'result', 'ok' => true, 'fixture' => $fixture]);
        exit(0);
    }
    auth()->login(User::query()->findOrFail($request['actor']));
    // Fetch as the UI does, before entering the service. The service must recheck
    // recovery eligibility after waiting for its transaction lock.
    $checkpoint = isset($request['target']) ? PublicationCheckpoint::query()->findOrFail($request['target']) : null;
    $paused = false;
    DB::listen(function ($query) use ($request, &$paused): void {
        if (($request['fail_after_audit'] ?? false) && str_starts_with(strtolower($query->sql), 'insert into "audit_events"')) {
            throw new RuntimeException('Injected failure after recovery mutation and audit insert, before transaction commit.');
        }
        if ($paused || ! isset($request['pause']) || ! str_contains(strtolower($query->sql), $request['pause'])) {
            return;
        }
        $paused = true;
        emit(['phase' => 'locked', 'sql' => $query->sql]);
        if (trim((string) fgets(STDIN)) !== 'continue') {
            throw new RuntimeException('Controller abandoned the held transaction.');
        }
    });
    emit(['phase' => 'started']);
    $value = match ($operation) {
        'undo' => app(AdminUndoService::class)->undo($request['receipt']),
        'ui-undo' => undoThroughActivity($request),
        'commit' => app(PublicationService::class)->commit(auth()->user(), 'Concurrent commit'),
        'revert' => app(PublicationVersionService::class)->stageRevertOfCurrent(auth()->user()),
        'restore' => app(PublicationVersionService::class)->stageVersion($checkpoint, auth()->user()),
        'reclaim' => app(SiteStorageReclaimService::class)->reclaim(),
        'inspect' => ['live_hash' => app(PublicationVersionService::class)->snapshotHash('committed')],
        default => throw new InvalidArgumentException('Unknown operation'),
    };
    emit(['phase' => 'result', 'ok' => true, 'value' => $value]);
} catch (Throwable $exception) {
    emit(['phase' => 'result', 'ok' => false, 'exception' => $exception::class, 'message' => $exception->getMessage()]);
    exit(1);
}
