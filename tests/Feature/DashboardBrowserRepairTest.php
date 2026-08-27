<?php

use App\Domain\Admin\DashboardFeed;
use App\Filament\Pages\Dashboard;
use App\Models\ContactMessage;
use Carbon\Carbon;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('projects announcements changelog and contact into the shared feed without tutorials', function (): void {
    config()->set('dashboard-feed.items', [
        [
            'id' => 'announcement',
            'type' => 'announcement',
            'date' => '2026-08-26',
            'title' => 'Studio notice',
            'body' => 'A short announcement body.',
        ],
        [
            'id' => 'change',
            'type' => 'changelog',
            'date' => '2026-08-25',
            'title' => 'Dashboard changed',
            'body' => 'The dashboard feed now uses the shared table.',
        ],
        [
            'id' => 'tutorial',
            'type' => 'tutorial',
            'date' => '2026-08-27',
            'title' => 'This must not appear',
            'body' => 'Tutorial copy.',
        ],
    ]);

    ContactMessage::query()->create([
        'sender_name' => 'Ada Lovelace',
        'sender_email' => 'ada@example.test',
        'message' => 'Please send the new catalogue.',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    $feed = app(DashboardFeed::class);

    expect(DashboardFeed::types())->toBe([
        'all' => 'All',
        'announcement' => 'Announcements',
        'changelog' => 'Changelog',
        'contact' => 'Contact',
    ]);

    $all = $feed->paginate();
    expect($all['total'])->toBe(3)
        ->and(collect($all['items'])->pluck('type')->all())->not->toContain('tutorial')
        ->and($feed->paginate('', 'announcement')['total'])->toBe(1)
        ->and($feed->paginate('', 'changelog')['total'])->toBe(1)
        ->and($feed->paginate('', 'contact')['total'])->toBe(1)
        ->and($feed->paginate('catalogue')['total'])->toBe(1)
        ->and($feed->paginate('ada@example.test')['total'])->toBe(1)
        ->and($feed->paginate('dashboard changed')['total'])->toBe(1);
});

it('paginates more than three contact pages without duplicates or omissions', function (): void {
    config()->set('dashboard-feed.items', []);
    $timestamp = Carbon::parse('2026-08-27 12:00:00');
    $expectedIds = [];

    foreach (range(1, 87) as $index) {
        $message = ContactMessage::query()->create([
            'sender_name' => 'Visitor '.$index,
            'sender_email' => "visitor{$index}@example.test",
            'message' => 'Message '.$index,
            'mail_delivery_status' => ContactMessage::DELIVERY_UNAVAILABLE,
        ]);
        DB::table('contact_messages')->where('id', $message->getKey())->update([
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $expectedIds[] = (int) $message->getKey();
    }

    rsort($expectedIds);
    $feed = app(DashboardFeed::class);
    $pages = [];

    foreach (range(1, 4) as $page) {
        $pages[$page] = $feed->paginate('', 'contact', $page, 25);
    }

    $visibleIds = collect($pages)
        ->flatMap(static fn (array $pagination): array => collect($pagination['items'])->pluck('contact_id')->all())
        ->values()
        ->all();

    expect($pages[1]['total'])->toBe(87)
        ->and($pages[1]['pages'])->toBe(4)
        ->and([$pages[1]['start'], $pages[1]['end']])->toBe([1, 25])
        ->and([$pages[2]['start'], $pages[2]['end']])->toBe([26, 50])
        ->and([$pages[3]['start'], $pages[3]['end']])->toBe([51, 75])
        ->and([$pages[4]['start'], $pages[4]['end']])->toBe([76, 87])
        ->and($visibleIds)->toBe($expectedIds)
        ->and(array_values(array_unique($visibleIds)))->toHaveCount(87);
});

it('searches contact sender name email and message in the database with correct pagination metadata', function (): void {
    config()->set('dashboard-feed.items', []);

    foreach (range(1, 80) as $index) {
        ContactMessage::query()->create([
            'sender_name' => $index <= 30 ? 'NameNeedle '.$index : 'Visitor '.$index,
            'sender_email' => $index >= 31 && $index <= 55 ? "mailneedle{$index}@example.test" : "visitor{$index}@example.test",
            'message' => $index >= 56 ? 'BodyNeedle message '.$index : 'Ordinary message '.$index,
            'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
        ]);
    }

    $feed = app(DashboardFeed::class);
    $namePageOne = $feed->paginate('nameneedle', 'contact', 1, 25);
    $namePageTwo = $feed->paginate('nameneedle', 'contact', 2, 25);
    $email = $feed->paginate('mailneedle', 'contact', 1, 25);
    $message = $feed->paginate('bodyneedle', 'contact', 1, 25);

    expect($namePageOne['total'])->toBe(30)
        ->and([$namePageOne['start'], $namePageOne['end']])->toBe([1, 25])
        ->and([$namePageTwo['start'], $namePageTwo['end']])->toBe([26, 30])
        ->and($email['total'])->toBe(25)
        ->and([$email['start'], $email['end']])->toBe([1, 25])
        ->and($message['total'])->toBe(25)
        ->and([$message['start'], $message['end']])->toBe([1, 25]);
});

it('does not query contact messages for announcement or changelog projections', function (): void {
    config()->set('dashboard-feed.items', [
        ['id' => 'notice', 'type' => 'announcement', 'date' => '2026-08-27', 'title' => 'Notice', 'body' => 'Announcement body'],
        ['id' => 'change', 'type' => 'changelog', 'date' => '2026-08-26', 'title' => 'Change', 'body' => 'Changelog body'],
    ]);

    foreach (range(1, 40) as $index) {
        ContactMessage::query()->create([
            'sender_name' => 'Visitor '.$index,
            'sender_email' => "visitor{$index}@example.test",
            'message' => 'Message '.$index,
            'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
        ]);
    }

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        $queries[] = strtolower($query->sql);
    });

    $feed = app(DashboardFeed::class);
    expect($feed->paginate('', 'announcement')['total'])->toBe(1)
        ->and($feed->paginate('', 'changelog')['total'])->toBe(1);

    expect(collect($queries)->filter(static fn (string $sql): bool => str_contains($sql, 'contact_messages'))->all())->toBe([]);
});

it('keeps contact hydration bounded to the requested page and the tiny static merge allowance', function (): void {
    config()->set('dashboard-feed.items', [
        ['id' => 'notice', 'type' => 'announcement', 'date' => '2026-08-27', 'title' => 'Notice', 'body' => 'Announcement body'],
        ['id' => 'change', 'type' => 'changelog', 'date' => '2026-08-26', 'title' => 'Change', 'body' => 'Changelog body'],
    ]);

    foreach (range(1, 120) as $index) {
        ContactMessage::query()->create([
            'sender_name' => 'Visitor '.$index,
            'sender_email' => "visitor{$index}@example.test",
            'message' => 'Message '.$index,
            'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
        ]);
    }

    $retrieved = 0;
    Event::listen('eloquent.retrieved: '.ContactMessage::class, function () use (&$retrieved): void {
        $retrieved++;
    });

    $feed = app(DashboardFeed::class);
    $contactPage = $feed->paginate('', 'contact', 2, 25);

    expect($contactPage['items'])->toHaveCount(25)
        ->and($retrieved)->toBe(25);

    $retrieved = 0;
    $allPage = $feed->paginate('', 'all', 2, 25);

    expect($allPage['items'])->toHaveCount(25)
        ->and($retrieved)->toBe(27);
});

it('merges static and contact items in one global chronological order', function (): void {
    config()->set('dashboard-feed.items', [
        ['id' => 'notice', 'type' => 'announcement', 'date' => '2026-08-27', 'title' => 'Notice', 'body' => 'Announcement body'],
        ['id' => 'change', 'type' => 'changelog', 'date' => '2026-08-25', 'title' => 'Change', 'body' => 'Changelog body'],
    ]);

    $contactTimes = [
        '2026-08-28 12:00:00',
        '2026-08-26 12:00:00',
        '2026-08-24 12:00:00',
    ];
    $contactKeys = [];

    foreach ($contactTimes as $index => $time) {
        $message = ContactMessage::query()->create([
            'sender_name' => 'Visitor '.$index,
            'sender_email' => "visitor{$index}@example.test",
            'message' => 'Message '.$index,
            'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
        ]);
        $at = Carbon::parse($time);
        DB::table('contact_messages')->where('id', $message->getKey())->update([
            'created_at' => $at,
            'updated_at' => $at,
        ]);
        $contactKeys[] = 'contact:'.$message->getKey();
    }

    $items = app(DashboardFeed::class)->paginate('', 'all', 1, 25)['items'];

    expect(collect($items)->pluck('key')->all())->toBe([
        $contactKeys[0],
        'static:notice',
        $contactKeys[1],
        'static:change',
        $contactKeys[2],
    ]);
});

it('resets feed page when search type or page size changes', function (): void {
    $dashboard = new Dashboard;

    $dashboard->feedPage = 4;
    $dashboard->updatedFeedSearch();
    expect($dashboard->feedPage)->toBe(1);

    $dashboard->feedPage = 3;
    $dashboard->feedType = 'contact';
    $dashboard->updatedFeedType();
    expect($dashboard->feedPage)->toBe(1)
        ->and($dashboard->feedType)->toBe('contact');

    $dashboard->feedPage = 2;
    $dashboard->feedType = 'unsupported';
    $dashboard->updatedFeedType();
    expect($dashboard->feedPage)->toBe(1)
        ->and($dashboard->feedType)->toBe('all');

    $dashboard->feedPage = 3;
    $dashboard->updatedFeedPageSize(25);
    expect($dashboard->feedPage)->toBe(1)
        ->and($dashboard->feedPageSize)->toBe(25);
});

it('marks contact read on opening and can mark it unread again', function (): void {
    $message = ContactMessage::query()->create([
        'sender_name' => 'Visitor',
        'sender_email' => 'visitor@example.test',
        'message' => 'Read state test',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    expect($message->getAttribute('read_at'))->toBeNull();

    $opened = app(DashboardFeed::class)->openEntry('contact:'.$message->getKey());

    expect($opened)->not->toBeNull()
        ->and($opened['status'])->toBe('Read')
        ->and($message->refresh()->getAttribute('read_at'))->not->toBeNull();

    app(DashboardFeed::class)->markContactUnread((int) $message->getKey());

    expect($message->refresh()->getAttribute('read_at'))->toBeNull()
        ->and(app(DashboardFeed::class)->entry('contact:'.$message->getKey())['status'])->toBe('Unread');
});

it('deletes stored contact messages cleanly', function (): void {
    $message = ContactMessage::query()->create([
        'sender_name' => 'Visitor',
        'sender_email' => 'visitor@example.test',
        'message' => 'Delete state test',
        'mail_delivery_status' => ContactMessage::DELIVERY_FAILED,
    ]);

    app(DashboardFeed::class)->deleteContact((int) $message->getKey());

    expect(ContactMessage::query()->whereKey($message->getKey())->exists())->toBeFalse()
        ->and(app(DashboardFeed::class)->entry('contact:'.$message->getKey()))->toBeNull();
});

it('locks the dashboard source to shared workspace metrics controls table pager and central theme ownership', function (): void {
    $view = file_get_contents(resource_path('views/filament/pages/dashboard.blade.php'));
    $page = file_get_contents(app_path('Filament/Pages/Dashboard.php'));
    $overview = file_get_contents(app_path('Domain/Admin/DashboardOverview.php'));
    $dashboardCss = file_get_contents(resource_path('css/admin/dashboard.css'));
    $sharedCss = file_get_contents(resource_path('css/admin/data-workspace.css'));
    $mediaCss = file_get_contents(resource_path('css/admin/media.css'));
    $journalCss = file_get_contents(resource_path('css/admin/journal.css'));
    $adminCss = file_get_contents(resource_path('css/admin.css'));
    $config = file_get_contents(config_path('dashboard-feed.php'));

    expect($view)->toContain('<x-admin.workspace title="Dashboard"')
        ->and($view)->toContain('<x-admin.metrics :columns="6"')
        ->and($view)->toContain('admin-data-controls')
        ->and($view)->toContain('<span>Type</span>')
        ->and($view)->toContain('<x-admin.table class="admin-data-table">')
        ->and($view)->toContain('<th scope="col">Type</th>')
        ->and($view)->toContain('<th scope="col">Date</th>')
        ->and($view)->toContain('<th scope="col">Title</th>')
        ->and($view)->toContain('<th scope="col">Sender</th>')
        ->and($view)->toContain('<th scope="col">Message</th>')
        ->and($view)->toContain('<th scope="col">Status</th>')
        ->and($view)->toContain('class="admin-pager"')
        ->and($view)->not->toContain('>View<', 'Notification', 'Tutorial')
        ->and($page)->toContain('extends Page')
        ->and($page)->not->toContain('ArtistDashboard')
        ->and($overview)->toContain(
            "['label' => 'Visits'",
            "['label' => 'Visitors'",
            "['label' => 'Published artworks'",
            "['label' => 'Published pages'",
            "['label' => 'Storage used'",
            "['label' => 'Recent changes'",
        )
        ->and($adminCss)->toContain("@import './admin/data-workspace.css';")
        ->and($dashboardCss)->not->toContain("@import './data-workspace.css';", '.admin-dashboard .admin-metrics', 'admin-dashboard__feed-item', 'admin-dashboard__feed-controls input')
        ->and($sharedCss)->toContain('.media-workspace__field', '.journal-workspace__field', '.media-workspace__pager', '.journal-workspace__pager')
        ->and($mediaCss)->not->toContain('.media-workspace__field input,', '.media-workspace__pager-size select', '.media-workspace .admin-action.is-danger', '.media-workspace__table-wrap {')
        ->and($journalCss)->not->toContain('.journal-workspace__field input,', '.journal-workspace__pager-size select', '.journal-workspace .admin-action.is-danger', '.journal-workspace__table-wrap {')
        ->and($config)->not->toContain("'tutorial'");
});
