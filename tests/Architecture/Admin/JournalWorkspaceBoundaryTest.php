<?php

it('keeps Journal workspace read projection outside the Filament page', function (): void {
    $root = dirname(__DIR__, 3);
    $workspace = file_get_contents($root.'/app/Filament/Pages/JournalWorkspace.php');
    $readModel = file_get_contents($root.'/app/Filament/Support/JournalWorkspaceReadModel.php');

    expect($workspace)
        ->toContain('JournalWorkspaceReadModel::class')
        ->not->toContain('ArtistReportingService')
        ->not->toContain('JournalEntryMedia')
        ->not->toContain('PublicMedia')
        ->not->toContain('applyTimingFilter(')
        ->not->toContain('coverThumbnailUrl(');

    expect($readModel)
        ->toContain('ArtistReportingService')
        ->toContain('includeMetrics')
        ->toContain('JournalEntryMedia::ROLE_COVER')
        ->toContain('->forPage($page, $pageSize)')
        ->toContain('if ($includeMetrics)');
});
