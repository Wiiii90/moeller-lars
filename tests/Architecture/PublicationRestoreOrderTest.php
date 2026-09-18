<?php

use App\Domain\Publication\PublicationSnapshot;

it('restores publication snapshots in foreign-key dependency order', function (): void {
    expect(PublicationSnapshot::RESTORE_TABLES)
        ->toHaveCount(count(PublicationSnapshot::TABLES))
        ->and(array_diff(PublicationSnapshot::TABLES, PublicationSnapshot::RESTORE_TABLES))->toBe([])
        ->and(array_diff(PublicationSnapshot::RESTORE_TABLES, PublicationSnapshot::TABLES))->toBe([]);

    $position = array_flip(PublicationSnapshot::RESTORE_TABLES);

    expect($position['media_assets'])->toBeLessThan($position['artwork_media'])
        ->and($position['artworks'])->toBeLessThan($position['artwork_media'])
        ->and($position['media_assets'])->toBeLessThan($position['media_variants'])
        ->and($position['site_sections'])->toBeLessThan($position['journal_entry_media'])
        ->and($position['blog_posts'])->toBeLessThan($position['journal_entry_media'])
        ->and($position['exhibitions'])->toBeLessThan($position['journal_entry_media']);
});
