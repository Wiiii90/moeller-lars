<?php

namespace App\Providers;

use App\Database\PublicationPostgresGrammar;
use App\Domain\Content\PublicSiteContext;
use App\Domain\Publication\PublicationReadContext;
use Illuminate\Database\Connection;
use Illuminate\Database\PostgresConnection;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

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
        View::composer('layouts.app', function ($view): void {
            $view->with(app(PublicSiteContext::class)->layoutData());
        });
    }
}
