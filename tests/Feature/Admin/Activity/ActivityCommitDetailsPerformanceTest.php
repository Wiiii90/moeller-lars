<?php

use App\Filament\Pages\Activity;
use App\Filament\Support\AdminPublicationHistory;

it('reuses commit details within one Activity component instance', function (): void {
    $history = new class
    {
        public int $calls = 0;

        /** @return array<string, mixed>|null */
        public function checkpoint(int $checkpointId): ?array
        {
            $this->calls++;

            return [
                'id' => $checkpointId,
                'short_hash' => 'checkpoint-'.$checkpointId,
            ];
        }
    };

    app()->instance(AdminPublicationHistory::class, $history);

    $activity = new Activity;
    $commitDetails = new ReflectionMethod($activity, 'commitDetails');

    $first = $commitDetails->invoke($activity, ['id' => 41]);
    $second = $commitDetails->invoke($activity, ['id' => 41]);
    $other = $commitDetails->invoke($activity, ['id' => 42]);

    expect($first)->toBe($second)
        ->and($first)->toBe([
            'id' => 41,
            'short_hash' => 'checkpoint-41',
        ])
        ->and($other)->toBe([
            'id' => 42,
            'short_hash' => 'checkpoint-42',
        ])
        ->and($history->calls)->toBe(2);
});
