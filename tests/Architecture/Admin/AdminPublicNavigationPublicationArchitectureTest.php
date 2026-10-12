<?php

it('has one publication-owned public navigation policy without editable menu flags', function (): void {
    $public = file_get_contents(app_path('Domain/Content/PublicNavigationService.php'));
    $section = file_get_contents(app_path('Models/SiteSection.php'));
    $placement = file_get_contents(app_path('Domain/Content/SiteSectionEditorialService.php'));
    $pages = file_get_contents(app_path('Filament/Pages/SitePages.php'));
    $pageDialog = file_get_contents(app_path('Filament/Pages/Concerns/ManagesSitePageEditDialog.php'));
    $customDialog = file_get_contents(app_path('Filament/Pages/Concerns/CustomPageWorkspaceLifecycle.php'));
    $homeDialog = file_get_contents(app_path('Filament/Support/HomeSettingsDialog.php'));
    $homeWorkspace = file_get_contents(app_path('Filament/Pages/HomePresentation.php'));
    $pageView = file_get_contents(resource_path('views/filament/pages/site-pages.blade.php'));

    expect($public)
        ->toContain("where('state', 'published')")
        ->toContain("SiteSectionType::Home->value")
        ->not->toContain('show_in_navigation')
        ->and($section)->not->toContain('show_in_navigation')
        ->and($placement)->not->toContain('show_in_navigation')
        ->and($placement)->toContain('publishedPositionConflict')
        ->and($pages)->not->toContain('toggleSectionNavigation')
        ->not->toContain('show_in_navigation')
        ->and($pageDialog)->not->toContain('show_in_navigation')
        ->and($customDialog)->not->toContain('show_in_navigation')
        ->and($homeDialog)->not->toContain('show_in_navigation')
        ->and($homeWorkspace)->not->toContain('show_in_navigation')
        ->and($pageView)->not->toContain('In navigation')
        ->toContain('label="Galleries"');
});

it('keeps publication snapshot column parity while indexing published navigation positions', function (): void {
    $migration = file_get_contents(database_path('migrations/2026_10_12_000001_make_public_navigation_follow_publication.php'));
    $version = file_get_contents(app_path('Domain/Publication/PublicationVersionService.php'));

    expect($migration)
        ->toContain("['public', 'committed']")
        ->toContain('site_sections_published_root_position_index')
        ->toContain('site_sections_published_child_position_index')
        ->toContain("WHERE parent_id IS NULL AND state = 'published'")
        ->not->toContain('CREATE UNIQUE INDEX site_sections_published_')
        ->not->toContain('DROP COLUMN')
        ->and($version)
        ->toContain('schemaHash()');
});
