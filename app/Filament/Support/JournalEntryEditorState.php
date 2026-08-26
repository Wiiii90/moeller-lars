<?php

namespace App\Filament\Support;

use App\Domain\Content\JournalEntryMediaService;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\MediaAsset;

final class JournalEntryEditorState
{
    public function __construct(private readonly JournalEntryMediaService $media) {}

    /** @return array<string, mixed> */
    public function for(BlogPost|Exhibition $entry): array
    {
        $entry->loadMissing('mediaUsages.mediaAsset.variants');

        foreach ($entry->mediaUsages as $usage) {
            $asset = $usage->getRelationValue('mediaAsset');
            if ($asset instanceof MediaAsset) {
                MediaAssetSelect::primeOptionLabel($asset);
            }
        }

        return $this->media->structuredEditorState($entry);
    }
}
