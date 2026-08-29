<?php

namespace App\Providers;

use App\Domain\Content\PublicSiteContext;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        View::composer('layouts.app', function ($view): void {
            $view->with(app(PublicSiteContext::class)->layoutData());
        });
    }
}
