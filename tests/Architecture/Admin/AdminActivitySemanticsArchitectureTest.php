<?php

it('keeps Activity projection split into area type change and details', function (): void {
    $root = dirname(__DIR__, 3);
    $feed = file_get_contents($root.'/app/Filament/Support/AdminActivityFeed.php');
    $presentation = file_get_contents($root.'/app/Domain/Admin/AdminActivityPresentation.php');
    $table = file_get_contents($root.'/resources/views/filament/pages/activity.blade.php');
    $details = file_get_contents($root.'/resources/views/filament/pages/partials/activity-details-dialog.blade.php');
    $commitDetails = file_get_contents($root.'/resources/views/filament/pages/partials/activity-commit-details-dialog.blade.php');

    expect($feed)
        ->toContain("'change' => \$change")
        ->toContain("'details' => \$details")
        ->toContain("'type' => AdminActionCatalog::familyOptions()")
        ->not->toContain("'action' => \$actionLabel");

    expect($presentation)
        ->toContain("Ordered artworks")
        ->toContain('swapped positions')
        ->toContain('returned to its starting order')
        ->toContain('AdminOrderingState::denormalize');

    expect($table)
        ->toContain("{{ \$event['change'] }}")
        ->toContain("{{ \$event['type'] }}")
        ->not->toContain("{{ \$event['action'] }}");

    expect($details)
        ->toContain('<span>Change</span>')
        ->toContain('<span>Details</span>')
        ->toContain('<dt>Area</dt>')
        ->toContain('<dt>Type</dt>');

    expect($commitDetails)
        ->toContain("{{ \$activity['change'] }}")
        ->toContain("{{ \$activity['details'] }}")
        ->not->toContain("{{ \$activity['action'] }}");
});
