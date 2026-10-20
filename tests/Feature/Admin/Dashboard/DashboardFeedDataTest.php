<?php

use App\Domain\Admin\DashboardFeed;
use App\Models\ContactMessage;
use App\Models\AdminNotification;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

it('paginates notifications with other feed sources while applying recipient and status filters', function (): void {
    $actor = User::factory()->admin()->create(['dashboard_notification_retention' => 100]);
    $other = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    config()->set('dashboard-feed.items', [
        ['id' => 'notice', 'type' => 'announcement', 'date' => '2026-09-01', 'title' => 'Static notice', 'body' => 'Notice'],
    ]);
    $expectedKeys = ['static:notice'];
    foreach (range(1, 30) as $index) {
        $notification = AdminNotification::query()->create([
            'user_id' => $actor->id, 'source_id' => 'page:'.$index, 'type' => 'system',
            'status' => $index % 2 === 0 ? 'danger' : 'success', 'title' => 'Own notification '.$index,
        ]);
        $expectedKeys[] = 'notification:'.$notification->id;
    }
    AdminNotification::query()->create([
        'user_id' => $other->id, 'source_id' => 'page:foreign', 'type' => 'system',
        'status' => 'danger', 'title' => 'Foreign notification',
    ]);
    $feed = app(DashboardFeed::class);
    $pageOne = $feed->paginate('', 'all', 1, 25);
    $pageTwo = $feed->paginate('', 'all', 2, 25);
    $keys = array_column([...$pageOne['items'], ...$pageTwo['items']], 'key');
    expect($pageOne['total'])->toBe(31)
        ->and($pageTwo['page'])->toBe(2)
        ->and(array_unique($keys))->toHaveCount(31)
        ->and($keys)->toEqualCanonicalizing($expectedKeys);
    $actor->forceFill(['dashboard_notification_filter' => 'danger'])->save();
    expect($feed->paginate('', 'notification')['total'])->toBe(15)
        ->and($feed->paginate('', 'all')['total'])->toBe(16)
        ->and($feed->paginate('Own notification 2', 'notification')['total'])->toBe(6);
});
