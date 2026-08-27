<?php

use App\Domain\Admin\DashboardFeed;

it('keeps the dashboard feed repository owned and filterable', function (): void {
    config()->set('dashboard-feed.items', [
        [
            'id' => 'older-tutorial',
            'type' => 'tutorial',
            'date' => '2026-08-20',
            'title' => 'Use Activity',
            'body' => 'Review the audit trail.',
        ],
        [
            'id' => 'newer-change',
            'type' => 'changelog',
            'date' => '2026-08-27',
            'title' => 'Dashboard rebuilt',
            'body' => 'A compact overview is now available.',
        ],
    ]);

    $feed = app(DashboardFeed::class);

    expect($feed->items())->toHaveCount(2)
        ->and($feed->items()[0]['id'])->toBe('newer-change')
        ->and($feed->items('', 'tutorial'))->toHaveCount(1)
        ->and($feed->items('audit'))->toHaveCount(1)
        ->and(DashboardFeed::views())->toBe([
            'all' => 'All',
            'changelog' => 'Changelog',
            'announcement' => 'Announcements',
            'tutorial' => 'Tutorials',
        ]);
});

it('locks the dashboard browser contract to one heading six facts three teasers and a read only feed', function (): void {
    $view = file_get_contents(resource_path('views/filament/widgets/artist-dashboard.blade.php'));
    $widget = file_get_contents(app_path('Filament/Widgets/ArtistDashboard.php'));

    expect($view)->toContain('title="Dashboard"')
        ->and(substr_count($widget, "['label' =>"))->toBeGreaterThanOrEqual(6)
        ->and($view)->toContain('>Storage<', '>Activity<', '>Analytics<')
        ->and($view)->toContain('wire:model.live.debounce.300ms="feedSearch"')
        ->and($view)->toContain('wire:model.live="feedView"')
        ->and($view)->toContain('wire:click="resetFeed"')
        ->and($view)->not->toContain('wire:click="create')
        ->and($widget)->toContain('cachedSnapshotIfAvailable()')
        ->and($widget)->toContain("report('30d')")
        ->and($widget)->toContain("SiteNodeType::NavigationNode->value");
});
