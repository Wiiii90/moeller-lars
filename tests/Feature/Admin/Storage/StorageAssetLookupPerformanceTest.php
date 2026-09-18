<?php

use App\Filament\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Models\MediaAsset;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('reuses one Storage media asset lookup within the component instance', function (): void {
    $firstAsset = MediaAsset::query()->create([
        'storage_key' => 'originals/perf-first.jpg',
        'original_filename' => 'perf-first.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 12,
        'sha256' => hash('sha256', 'perf-first'),
        'state' => 'available',
    ]);
    $secondAsset = MediaAsset::query()->create([
        'storage_key' => 'originals/perf-second.jpg',
        'original_filename' => 'perf-second.jpg',
        'mime_type' => 'image/jpeg',
        'byte_size' => 13,
        'sha256' => hash('sha256', 'perf-second'),
        'state' => 'available',
    ]);

    $assetSelects = [];
    DB::listen(function (QueryExecuted $query) use (&$assetSelects): void {
        $sql = strtolower(ltrim($query->sql));
        if (str_starts_with($sql, 'select') && str_contains($sql, 'media_assets')) {
            $assetSelects[] = $query->sql;
        }
    });

    $page = new ListMediaAssets;
    $assetById = new ReflectionMethod($page, 'assetById');

    $first = $assetById->invoke($page, (int) $firstAsset->getKey());
    $repeat = $assetById->invoke($page, (int) $firstAsset->getKey());
    $second = $assetById->invoke($page, (int) $secondAsset->getKey());

    expect($first)->toBe($repeat)
        ->and($first->is($firstAsset))->toBeTrue()
        ->and($second->is($secondAsset))->toBeTrue()
        ->and($assetSelects)->toHaveCount(2);
});
