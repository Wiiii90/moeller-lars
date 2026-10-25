<?php

// Test-only front controller. Real HTTP kernels, sessions, route binding and
// controllers are used; the only instrumentation observes SQL and pauses views.
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationReadContext;
use App\Domain\Publication\PublicationService;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use App\Support\LocalPreviewDatabaseGuard;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\View;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$root = realpath((string) getenv('PUBLIC_SESSION_RUNTIME'));
if (! $root || ! str_contains(str_replace('\\', '/', $root), '/storage/framework/testing/public-session-')) {
    throw new RuntimeException('An explicitly isolated Public session runtime is required.');
}
foreach (['GITHUB_ACTIONS', 'MOELLER_LARS_DISPOSABLE_TEST_DATABASE'] as $flag) {
    putenv($flag);
    unset($_ENV[$flag], $_SERVER[$flag]);
}
foreach (simplexml_load_file(dirname(__DIR__, 2).'/phpunit.xml')->php->env as $entry) {
    $name = (string) $entry['name'];
    if ((string) $entry['force'] === 'true' || getenv($name) === false) {
        putenv($name.'='.(string) $entry['value']);
        $_ENV[$name] = $_SERVER[$name] = (string) $entry['value'];
    }
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->useEnvironmentPath($root)->loadEnvironmentFrom('.env.testing');
$app->make(ConsoleKernel::class)->bootstrap();
$connection = DB::connection();
$pdo = $connection->getPdo();
$identity = $pdo->query("SELECT current_database(), current_user, inet_server_port(), current_setting('server_version_num'), (SELECT system_identifier::text FROM pg_control_system())")->fetch(PDO::FETCH_NUM);
if ($identity[0] !== getenv('PUBLIC_SESSION_DATABASE') || $identity[1] !== getenv('PUBLIC_SESSION_DATABASE')
    || ! str_starts_with($identity[0], 'public_session_') || (int) $identity[2] !== 5432
    || intdiv((int) $identity[3], 10000) !== 17 || $identity[4] !== getenv('PUBLIC_SESSION_CLUSTER')
    || config('database.connections.pgsql.host') !== '127.0.0.1'
    || (string) config('database.connections.pgsql.port') !== getenv('DB_PORT')
    || config('database.default') !== 'pgsql' || ! in_array(config('session.connection'), [null, 'pgsql'], true)
    || config('session.table') !== 'sessions' || config('cache.default') !== 'array'
    || config('database.connections.pgsql.url') || $app->configurationIsCached()
    || ! $app->environment('testing') || config('session.driver') !== 'database'
    || config('media.disk') !== 'local' || config('filesystems.default') !== 'local'
    || $app->environmentFilePath() !== $root.DIRECTORY_SEPARATOR.'.env.testing') {
    throw new RuntimeException('Effective Laravel/PDO/PHPUnit/session isolation mismatch.');
}
$paths = [storage_path(), Storage::disk('local')->path(''), Storage::disk('public')->path(''),
    config('cache.stores.file.path'), config('session.files'), config('view.compiled'),
    $app->getCachedConfigPath(), $app->getCachedPackagesPath(), $app->getCachedServicesPath(),
    $app->getCachedEventsPath(), $app->getCachedRoutesPath(), sys_get_temp_dir(), ini_get('upload_tmp_dir')];
foreach ($paths as $path) {
    $normalized = str_replace('\\', '/', (string) $path);
    $directory = is_dir($path) ? $path : dirname($path);
    if (! str_starts_with($normalized, str_replace('\\', '/', $root).'/') || str_contains($normalized, '/../')
        || ! is_dir($directory) || ! is_writable($directory)) {
        throw new RuntimeException('Non-isolated or unwritable runtime target: '.$normalized);
    }
}
$pdo->exec("SET lock_timeout = '15s'; SET statement_timeout = '20s'");
foreach (['GITHUB_ACTIONS' => 'true', 'MOELLER_LARS_DISPOSABLE_TEST_DATABASE' => '1'] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}
LocalPreviewDatabaseGuard::assertDisposableTestContext();

if (PHP_SAPI === 'cli') {
    if (($argv[1] ?? '') === 'prepare') {
        LocalPreviewDatabaseGuard::assertDestructiveCommandIsAllowed();
        Artisan::call('migrate:fresh', ['--force' => true]);
        $actor = User::factory()->admin()->create();
        auth()->login($actor);
        $editor = app(SiteSectionEditorialService::class);
        Storage::disk('local')->put('originals/session.png', base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+jRZkAAAAASUVORK5CYII='));
        $attributes = ['storage_key' => 'originals/session.png', 'original_filename' => 'session.png',
            'mime_type' => 'image/png', 'byte_size' => 68, 'sha256' => hash_file('sha256', Storage::disk('local')->path('originals/session.png')),
            'state' => 'available', 'alt_text' => 'Session publication fixture', 'width' => 1, 'height' => 1];
        $asset = MediaAsset::query()->create($attributes);
        Storage::disk('local')->copy('originals/session.png', 'variants/session.png');
        $variant = MediaVariant::query()->create(['media_asset_id' => $asset->id, 'variant_kind' => 'thumbnail',
            'storage_key' => 'variants/session.png', 'mime_type' => 'image/png', 'byte_size' => 68,
            'sha256' => $asset->sha256, 'transform_profile' => 'public-v1', 'state' => 'available', 'width' => 1, 'height' => 1]);
        Storage::disk('local')->copy('originals/session.png', 'originals/unreferenced.png');
        $private = MediaAsset::query()->create([...$attributes, 'storage_key' => 'originals/unreferenced.png', 'original_filename' => 'unreferenced.png']);
        foreach (['session-exhibitions', 'session-cv'] as $slug) {
            $page = $editor->createCustomPage('Committed '.$slug, $slug);
            $page->customPageSetting()->firstOrFail()->update(['blocks' => [[
                'type' => 'image', 'published' => true, 'media_asset_id' => $asset->id, 'image_decorative' => false,
            ]]]);
            $editor->updatePlacement($page, 'published', null);
        }
        DB::table('home_presentation_settings')->update(['template' => 'custom', 'skip_home' => false]);
        app(PublicationService::class)->commit($actor);
        // The binding MUST ignore a divergent Working asset and page projection.
        $asset->update(['storage_key' => 'originals/working-only.png']);
        DB::table('public.site_sections')->whereIn('slug', ['session-exhibitions', 'session-cv'])->update(['title' => 'Working only']);
        $fixture = ['asset' => $asset->id, 'variant' => $variant->id, 'private' => $private->id];
        file_put_contents($root.'/fixture.json', json_encode($fixture, JSON_THROW_ON_ERROR));
    }
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $stacks = [];
    foreach (['site.section', 'media.original', 'media.variant'] as $name) {
        $stacks[$name] = app('router')->gatherRouteMiddleware(app('router')->getRoutes()->getByName($name));
    }
    echo json_encode(['identity' => $identity, 'paths' => $paths, 'global' => $kernel->getGlobalMiddleware(), 'routes' => $stacks], JSON_THROW_ON_ERROR)."\n";
    exit(0);
}

$request = Request::capture();
$tag = $request->header('X-Session-Probe');
if ($tag !== null && ! preg_match('/^[a-z0-9-]+$/D', $tag)) {
    throw new RuntimeException('Invalid observation tag.');
}
$evidence = ['identity' => $identity, 'paths' => $paths, 'queries' => [], 'views' => []];
$connection->beforeExecuting(function ($sql) use ($connection, $pdo, &$evidence): void {
    if (str_contains($sql, '"sessions"') || str_contains($sql, '"media_assets"')) {
        $evidence['queries'][] = ['sql' => $sql, 'level' => $connection->transactionLevel(),
            'isolation' => $pdo->query('SHOW transaction_isolation')->fetchColumn(),
            'committed' => app(PublicationReadContext::class)->usesCommittedSnapshot()];
    }
});
View::composer('pages.custom', function () use ($request, $pdo, &$evidence): void {
    $slug = $request->segment(1);
    $read = static fn () => DB::table('site_sections')->where('slug', $slug)->value('title');
    $before = $read();
    $key = $request->header('X-Session-Barrier');
    if ($key !== null) {
        if (! in_array($key, ['781001', '781002'], true)) {
            throw new RuntimeException('Invalid barrier.');
        }
        // The controller holds these locks. pg_blocking_pids proves both HTTP
        // workers reached a real snapshot before either may save the session.
        $pdo->query('SELECT pg_advisory_xact_lock('.$key.')')->fetchAll();
    }
    $evidence['views'][] = ['before' => $before, 'after' => $read(), 'level' => DB::transactionLevel(),
        'isolation' => $pdo->query('SHOW transaction_isolation')->fetchColumn(),
        'committed' => app(PublicationReadContext::class)->usesCommittedSnapshot(),
        'navigate' => $request->header('X-Livewire-Navigate'), 'session' => $request->session()->getId()];
});
$kernel = $app->make(Kernel::class);
$response = $kernel->handle($request);
if ($tag !== null) {
    file_put_contents($root.'/'.$tag.'.json', json_encode($evidence, JSON_THROW_ON_ERROR));
}
$response->send();
$kernel->terminate($request, $response);
