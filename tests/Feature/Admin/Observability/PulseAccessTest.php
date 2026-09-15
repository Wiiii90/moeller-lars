<?php

use App\Models\User;
use Laravel\Pulse\Recorders;

it('restricts the Pulse dashboard to administrator accounts', function (): void {
    $nonAdmin = User::factory()->create();
    $admin = User::factory()->admin()->create();

    $this->actingAs($nonAdmin, 'web')
        ->get('/pulse')
        ->assertForbidden();

    $this->actingAs($admin, 'web')
        ->get('/pulse')
        ->assertSuccessful();
});

it('keeps runtime telemetry bounded and opt in by default', function (): void {
    $recorders = config('pulse.recorders');

    expect(config('pulse.enabled'))->toBeFalse()
        ->and(config('pulse.storage.trim.keep'))->toBe('3 days')
        ->and(config('pulse.ingest.trim.keep'))->toBe('3 days')
        ->and($recorders[Recorders\CacheInteractions::class]['enabled'])->toBeFalse()
        ->and($recorders[Recorders\UserRequests::class]['enabled'])->toBeFalse()
        ->and($recorders[Recorders\UserJobs::class]['enabled'])->toBeFalse()
        ->and($recorders[Recorders\Queues::class]['enabled'])->toBeTrue()
        ->and($recorders[Recorders\SlowJobs::class]['enabled'])->toBeTrue()
        ->and($recorders[Recorders\SlowRequests::class]['threshold'])->toBe(1000)
        ->and($recorders[Recorders\SlowQueries::class]['threshold'])->toBe(250)
        ->and($recorders[Recorders\SlowOutgoingRequests::class]['threshold'])->toBe(750)
        ->and($recorders[Recorders\SlowOutgoingRequests::class]['groups'])
        ->toBe([
            '#^https?://([^/?#]+).*$#' => '\\1',
        ]);
});
