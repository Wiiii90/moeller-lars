<?php

it('keeps Journal ordering newest-first by default and shared by admin and public reads', function (): void {
    $root = dirname(__DIR__, 3);
    $order = file_get_contents($root.'/app/Domain/Content/JournalEntryOrderService.php');
    $migration = file_get_contents($root.'/database/migrations/2026_09_17_000001_resequence_journal_entries_newest_first.php');
    $workspace = file_get_contents($root.'/app/Filament/Pages/JournalWorkspace.php');
    $public = file_get_contents($root.'/app/Http/Controllers/PublicSiteSectionController.php');

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

    expect($workspace)
        ->toContain("->orderBy('position')->orderBy('id')->forPage")
        ->toContain("->where('site_section_id', \$this->sectionId)->orderBy('position')->orderBy('id')->pluck('id')");

    expect($public)
        ->toContain("->orderBy('position')->orderBy('id')->get()")
        ->toContain("->with('mediaUsages.mediaAsset.variants')->orderBy('position')->orderBy('id')->get()");
});
