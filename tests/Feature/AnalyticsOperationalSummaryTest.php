<?php

use App\Filament\Pages\Analytics;

it('separates actionable operational failures instead of collapsing them into one error count', function () {
    $method = new ReflectionMethod(Analytics::class, 'buildOperationalSummary');
    $summary = $method->invoke(new Analytics, [
        ['name' => 'error:http_404', 'value' => 5.0, 'sample_count' => null],
        ['name' => 'error:http_5xx', 'value' => 2.0, 'sample_count' => null],
        ['name' => 'error:request_exception', 'value' => 3.0, 'sample_count' => null],
        ['name' => 'bot:request', 'value' => 11.0, 'sample_count' => null],
        ['name' => 'operation:admin_request', 'value' => 7.0, 'sample_count' => null],
        ['name' => 'performance:request_duration_ms', 'value' => 360.0, 'sample_count' => 3],
        ['name' => 'performance:admin_request_duration_ms', 'value' => 450.0, 'sample_count' => 3],
    ]);

    expect($summary)->toBe([
        '5xx responses' => 2,
        'Request exceptions' => 3,
        '404 responses' => 5,
        'Bot requests' => 11,
        'Average response' => '120 ms',
        'Admin requests' => 7,
        'Average admin response' => '150 ms',
    ]);
});

it('keeps zero failure counts explicit when the selected period has no failures', function () {
    $method = new ReflectionMethod(Analytics::class, 'buildOperationalSummary');
    $summary = $method->invoke(new Analytics, []);

    expect($summary)->toMatchArray([
        '5xx responses' => 0,
        'Request exceptions' => 0,
        '404 responses' => 0,
        'Average response' => 'No data',
        'Average admin response' => 'No data',
    ]);
});
