<?php

use App\Http\Middleware\RecordOperationalMetrics;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

it('defers operational metric persistence until response termination', function (): void {
    $request = Request::create('/admin/pages', 'GET');
    $middleware = app(RecordOperationalMetrics::class);
    $date = now()->toDateString();

    $before = (int) DB::table('daily_metrics')
        ->where('metric_date', $date)
        ->where('metric_name', 'operation:admin_request')
        ->where('source', 'application')
        ->sum('sample_count');

    $response = $middleware->handle($request, fn () => response('OK'));

    $during = (int) DB::table('daily_metrics')
        ->where('metric_date', $date)
        ->where('metric_name', 'operation:admin_request')
        ->where('source', 'application')
        ->sum('sample_count');

    expect($during)->toBe($before);

    $middleware->terminate($request, $response);

    $after = (int) DB::table('daily_metrics')
        ->where('metric_date', $date)
        ->where('metric_name', 'operation:admin_request')
        ->where('source', 'application')
        ->sum('sample_count');

    expect($after)->toBe($before + 1);
});
