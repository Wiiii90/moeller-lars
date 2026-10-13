<?php

use App\Domain\Content\JournalMediaRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;

it('distinguishes inherited Journal notices from per-asset copyright overrides', function (): void {
    $renderer = new JournalMediaRenderer(\Mockery::mock(PublicMedia::class));
    $usage = new JournalEntryMedia;
    $asset = new MediaAsset;
    $usage->setRelation('mediaAsset', $asset);

    $asset->setRawAttributes(['copyright_notice_mode' => MediaAsset::COPYRIGHT_INHERIT]);
    expect($renderer->inheritsDefaultNotice($usage))->toBeTrue();

    $asset->setRawAttributes(['copyright_notice_mode' => MediaAsset::COPYRIGHT_OVERRIDE]);
    expect($renderer->inheritsDefaultNotice($usage))->toBeFalse();

    $asset->setRawAttributes(['copyright_notice_mode' => MediaAsset::COPYRIGHT_NONE]);
    expect($renderer->inheritsDefaultNotice($usage))->toBeFalse();
});
