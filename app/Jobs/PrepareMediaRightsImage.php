<?php

namespace App\Jobs;

use App\Domain\Media\MediaRightsWorkingPreparation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PrepareMediaRightsImage implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $mediaAssetId)
    {
        $this->onQueue('media-rights');
    }

    public function handle(MediaRightsWorkingPreparation $preparation): void
    {
        if (config('media.rights_preparation_enabled', false) === true) {
            $preparation->prepare($this->mediaAssetId);
        }
    }
}
