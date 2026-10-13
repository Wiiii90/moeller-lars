<?php

use App\Domain\Content\JournalMediaRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;

it('distinguishes inherited Journal notices from per-asset copyright overrides', function (): void {
    $previousResolver = Model::getConnectionResolver();
    $connection = new Connection(
        static fn () => throw new LogicException('This unit test must not open a database connection.'),
        'unit-test',
    );
    $resolver = new ConnectionResolver(['unit-test' => $connection]);
    $resolver->setDefaultConnection('unit-test');
    Model::setConnectionResolver($resolver);

    try {
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
    } finally {
        if ($previousResolver === null) {
            Model::unsetConnectionResolver();
        } else {
            Model::setConnectionResolver($previousResolver);
        }
    }
});
