<?php

namespace App\Jobs;

use App\Domain\Media\MediaTypePolicy;
use App\Models\MediaAsset;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PrepareMediaRightsBatch implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public readonly int $afterId = 0)
    {
        $this->onConnection('database')->onQueue('media-rights');
    }

    public function handle(): void
    {
        if (config('media.rights_preparation_enabled', false) !== true) {
            return;
        }

        $ids = MediaAsset::query()
            ->where('id', '>', $this->afterId)
            ->where('state', 'available')
            ->whereIn('mime_type', MediaTypePolicy::IMAGE_MIME_TYPES)
            ->orderBy('id')
            ->limit(20)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        foreach ($ids as $id) {
            PrepareMediaRightsImage::dispatch($id);
        }

        if (count($ids) === 20) {
            self::dispatch(end($ids));
        }
    }
}
