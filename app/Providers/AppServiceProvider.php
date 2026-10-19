<?php

namespace App\Providers;

use App\Database\PublicationPostgresGrammar;
use App\Domain\Admin\AdminMutationSnapshotBuffer;
use App\Domain\Admin\AdminUndoContext;
use App\Domain\Content\PublicSiteContext;
use App\Domain\Media\MediaTypePolicy;
use App\Domain\Publication\PublicationReadContext;
use App\Models\User;
use App\Support\AdminPasswordPolicy;
use App\Support\LocalPreviewDatabaseGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Vite;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(
            PublicationReadContext::class,
            static fn (): PublicationReadContext => new PublicationReadContext,
        );
        $this->app->scoped(
            AdminMutationSnapshotBuffer::class,
            static fn (): AdminMutationSnapshotBuffer => new AdminMutationSnapshotBuffer,
        );
        $this->app->scoped(
            AdminUndoContext::class,
            static fn (): AdminUndoContext => new AdminUndoContext,
        );

        Connection::resolverFor('pgsql', static function ($pdo, $database, $prefix, array $config): PostgresConnection {
            $connection = new PostgresConnection($pdo, $database, $prefix, $config);
            $connection->setQueryGrammar(new PublicationPostgresGrammar($connection));

            return $connection;
        });
    }

    public function boot(): void
    {
        // Livewire's temporary upload validator defaults to 12 MiB. Keep the
        // ingress size tied to the one canonical MediaTypePolicy; individual
        // consumers and MediaIngestService still enforce their own type limits.
        config()->set(
            'livewire.temporary_file_upload.rules',
            'file|max:'.(int) ceil(MediaTypePolicy::maxUploadBytes() / 1024),
        );
        // The transfer timeout is not a client polling timer.
        config()->set('livewire.temporary_file_upload.max_upload_time', 10);

        // Livewire's data-navigate-track detects changes in ?id=, not in the
        // Vite hashed file basename. Give the EXISTING Laravel/Vite tags one
        // manifest revision shared by CSS and JS so a built-image change
        // forces a real page reload instead of mixing an old SPA module
        // with new HTML/CSS. Hot Vite and missing manifests stay native.
        if (! Vite::isRunningHot() && ($revision = Vite::manifestHash()) !== null) {
            Vite::createAssetPathsUsing(static function (string $path, ?bool $secure) use ($revision): string {
                $url = asset($path, $secure);

                return str_starts_with(ltrim($path, '/'), 'build/')
                    && (str_ends_with($path, '.js') || str_ends_with($path, '.css'))
                        ? $url.'?id='.$revision
                        : $url;
            });
        }

        // Keep all contact limits at the existing HTTP boundary. A shared
        // cache makes the hourly ceiling effective across independent IPs.
        // Hash valid sender addresses to avoid storing personal data in
        // cache keys. Bad/oversized addresses still hit IP + global limits
        // and are subsequently rejected by the existing form validation.
        RateLimiter::for('public-contact', static function (Request $request): array {
            $limits = [
                Limit::perMinute(5)->by('public-contact:ip:'.($request->ip() ?? 'unknown')),
                Limit::perHour(60)->by('public-contact:site'),
            ];

            $email = $request->input('email');
            if (is_string($email) && strlen($email) <= 320
                && filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false) {
                $sender = strtolower(trim($email));
                $limits[] = Limit::perHour(6)->by('public-contact:sender:'.hash_hmac(
                    'sha256', $sender, (string) config('app.key'),
                ));
            }

            return $limits;
        });

        Password::defaults(fn (): Password => AdminPasswordPolicy::rule());

        Gate::define('viewPulse', static fn (User $user): bool => (bool) $user->getAttribute('is_admin'));

        Event::listen('eloquent.updating: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                app(AdminMutationSnapshotBuffer::class)->begin($model);
            }
        });
        Event::listen('eloquent.updated: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                app(AdminMutationSnapshotBuffer::class)->finish($model);
            }
        });
        Event::listen('eloquent.created: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                app(AdminMutationSnapshotBuffer::class)->created($model);
            }
        });
        Event::listen('eloquent.deleting: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                app(AdminMutationSnapshotBuffer::class)->beginDelete($model);
            }
        });
        Event::listen('eloquent.deleted: *', static function (string $eventName, array $payload): void {
            $model = $payload[0] ?? null;
            if ($model instanceof Model) {
                app(AdminMutationSnapshotBuffer::class)->finishDelete($model);
            }
        });

        Event::listen(CommandStarting::class, function (CommandStarting $event): void {
            if (in_array($event->command, [
                'db:wipe',
                'migrate:fresh',
                'migrate:refresh',
                'migrate:reset',
                'migrate:rollback',
            ], true)) {
                LocalPreviewDatabaseGuard::assertDestructiveCommandIsAllowed();
            }
        });

        View::composer('layouts.app', function ($view): void {
            $view->with(app(PublicSiteContext::class)->layoutData());
        });
    }
}
