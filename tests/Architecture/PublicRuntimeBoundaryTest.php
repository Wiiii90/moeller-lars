<?php

it('keeps public controllers orchestration-only and outside editorial services', function (): void {
    $root = dirname(__DIR__, 2);
    $controllers = [
        file_get_contents($root.'/app/Http/Controllers/PublicArtworkController.php'),
        file_get_contents($root.'/app/Http/Controllers/PublicSiteSectionController.php'),
    ];

    foreach ($controllers as $controller) {
        expect($controller)
            ->not->toContain('EditorialService')
            ->not->toContain('::query()')
            ->not->toContain('Illuminate\\Database\\Eloquent')
            ->not->toContain('App\\Models\\Redirect');
    }

    $sections = file_get_contents($root.'/app/Domain/Content/PublicSiteSectionQuery.php');
    expect($sections)
        ->toContain('SitePreviewContext')
        ->toContain('constrainSectionQuery')
        ->toContain("where('reason', \$reason)")
        ->toContain('if ($this->preview->active())');

    $journals = file_get_contents($root.'/app/Domain/Content/PublicJournalQuery.php');
    expect($journals)
        ->not->toContain('BlogEditorialService')
        ->toContain('->publiclyVisible()')
        ->toContain("where('state', '<>', 'archived')")
        ->toContain("with('mediaUsages.mediaAsset.variants')");

    $customPages = file_get_contents($root.'/app/Domain/Content/PublicCustomPageQuery.php');
    expect($customPages)
        ->toContain("loadMissing('customPageSetting')")
        ->toContain("with('variants')")
        ->toContain('PublicContentSetting::general()');
});
