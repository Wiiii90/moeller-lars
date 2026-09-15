<?php

namespace App\Providers;

use App\Database\PublicationPostgresGrammar;
use App\Domain\Content\PublicSiteContext;
use App\Domain\Publication\PublicationReadContext;
use App\Models\User;
use App\Support\AdminPasswordPolicy;
use App\Support\LocalPreviewDatabaseGuard;
use Illuminate\Console\Events\CommandStarting;
use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
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

        Connection::resolverFor('pgsql', static function ($pdo, $database, $prefix, array $config): PostgresConnection {
            $connection = new PostgresConnection($pdo, $database, $prefix, $config);
            $connection->setQueryGrammar(new PublicationPostgresGrammar($connection));

            return $connection;
        });
    }

    public function boot(): void
    {
        Password::defaults(fn (): Password => AdminPasswordPolicy::rule());

        Gate::define('viewPulse', static fn (User $user): bool => (bool) $user->getAttribute('is_admin'));

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
