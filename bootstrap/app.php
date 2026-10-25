<?php

use App\Http\Middleware\AttachPublicationState;
use App\Http\Middleware\RecordOperationalMetrics;
use App\Http\Middleware\SecurityHeaders;
use App\Http\Middleware\UseCommittedPublicState;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\SubstituteBindings;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->trustProxies(at: '*');
        $middleware->append(RecordOperationalMetrics::class);

        // Keep session persistence outside REPEATABLE READ, but resolve public
        // route models only after entering the consistent Committed snapshot.
        $middleware->prependToPriorityList(SubstituteBindings::class, UseCommittedPublicState::class);

        $middleware->web(
            append: [
                UseCommittedPublicState::class,
                SecurityHeaders::class,
                AttachPublicationState::class,
            ],
        );
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash([
            'current_password',
            'password',
            'password_confirmation',
        ]);

        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
