<?php

it('keeps Journal ordering newest-first by default and shared by admin and public reads', function (): void {
    $root = dirname(__DIR__, 3);
    $order = file_get_contents($root.'/app/Domain/Content/JournalEntryOrderService.php');
    $migration = file_get_contents($root.'/database/migrations/2026_09_17_000001_resequence_journal_entries_newest_first.php');
    $workspaceReadModel = file_get_contents($root.'/app/Filament/Support/JournalWorkspaceReadModel.php');
    $public = file_get_contents($root.'/app/Domain/Content/PublicJournalQuery.php');

    expect($order)
        ->toContain('Reserve the first canonical position')
        ->toContain("->orderBy('position')")
        ->toContain('$temporaryBase = $maximum + $records->count() + 1;')
        ->toContain("->update(['position' => \$offset + 2]);")
        ->toContain('return 1;');

    expect($migration)
        ->toContain("'draft' => 0")
        ->toContain("'scheduled' => 1")
        ->toContain("'published' => 2")
        ->toContain('exhibitionDateScore')
        ->toContain('legacyDateScore')
        ->toContain("\$this->persistOrder('blog_posts'")
        ->toContain("\$this->persistOrder('exhibitions'");

    expect($workspaceReadModel)
        ->toContain("->orderBy('position')")
        ->toContain("->orderBy('id')")
        ->toContain('->forPage($page, $pageSize)');

    expect($public)
        ->toContain("->orderBy('position')")
        ->toContain("->orderBy('id')")
        ->toContain("->with('mediaUsages.mediaAsset.variants')");
});
