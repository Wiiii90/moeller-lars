<?php

use App\Domain\Analytics\AnalyticsReportAvailability;

it('distinguishes unavailable partial reports from successful empty reports', function () {
    $availability = AnalyticsReportAvailability::fromReport([
        'warnings' => [
            'Countries report is unavailable.',
            'Referrer websites report is unavailable.',
            'Per-artwork interaction report is unavailable.',
            'Traffic time-series data is unavailable.',
        ],
    ]);

    expect($availability->isAvailable('countries'))->toBeFalse()
        ->and($availability->isAvailable('continents'))->toBeTrue()
        ->and($availability->isAvailable('referrer_websites'))->toBeFalse()
        ->and($availability->isAvailable('socials'))->toBeTrue()
        ->and($availability->isAvailable('artwork_events'))->toBeFalse()
        ->and($availability->isAvailable('series'))->toBeFalse()
        ->and($availability->anyAvailable(['countries', 'referrer_websites']))->toBeFalse()
        ->and($availability->anyAvailable(['countries', 'continents']))->toBeTrue();
});

it('treats reports without an unavailable warning as available even when their dataset is empty', function () {
    $availability = AnalyticsReportAvailability::fromReport([
        'warnings' => [],
        'countries' => [],
        'content' => [],
    ]);

    expect($availability->isAvailable('countries'))->toBeTrue()
        ->and($availability->isAvailable('content'))->toBeTrue()
        ->and($availability->isAvailable('unknown_future_report'))->toBeTrue();
});
