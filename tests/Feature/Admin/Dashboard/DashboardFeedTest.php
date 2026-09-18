<?php

use App\Domain\Admin\DashboardFeed;
use App\Domain\Admin\DashboardFeedPins;
use App\Models\ContactMessage;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

it('keeps all-feed pagination lossless across static and contact sources', function (): void {
    config()->set('dashboard-feed.items', [
        ['id' => 'newer-static', 'type' => 'announcement', 'date' => '2026-08-28', 'title' => 'Newer announcement', 'body' => 'This sorts before the contact window.'],
        ['id' => 'older-static', 'type' => 'changelog', 'date' => '2026-08-24', 'title' => 'Older changelog', 'body' => 'This sorts after the contact window.'],
    ]);

    $contactTimestamp = Carbon::parse('2026-08-26 12:00:00');
    $contactIds = [];

    foreach (range(1, 83) as $index) {
        $message = ContactMessage::query()->create([
            'sender_name' => 'Visitor '.$index,
            'sender_email' => "visitor{$index}@example.test",
            'message' => 'Message '.$index,
            'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
        ]);

        DB::table('contact_messages')->where('id', $message->getKey())->update([
            'created_at' => $contactTimestamp,
            'updated_at' => $contactTimestamp,
        ]);
        $contactIds[] = (int) $message->getKey();
    }

    $expectedContactKeys = array_map(static fn (int $id): string => 'contact:'.$id, $contactIds);
    sort($expectedContactKeys);

    $feed = app(DashboardFeed::class);
    $pages = [];
    foreach (range(1, 4) as $page) {
        $pages[$page] = $feed->paginate('', 'all', $page, 25);
    }

    $actualKeys = collect($pages)
        ->flatMap(static fn (array $pagination): array => collect($pagination['items'])->pluck('key')->all())
        ->values()
        ->all();
    $actualContactKeys = array_values(array_filter(
        $actualKeys,
        static fn (string $key): bool => str_starts_with($key, 'contact:'),
    ));
    sort($actualContactKeys);

    expect($pages[1]['total'])->toBe(85)
        ->and($pages[1]['pages'])->toBe(4)
        ->and([$pages[1]['start'], $pages[1]['end']])->toBe([1, 25])
        ->and([$pages[2]['start'], $pages[2]['end']])->toBe([26, 50])
        ->and([$pages[3]['start'], $pages[3]['end']])->toBe([51, 75])
        ->and([$pages[4]['start'], $pages[4]['end']])->toBe([76, 85])
        ->and($actualKeys[0])->toBe('static:newer-static')
        ->and($actualKeys[84])->toBe('static:older-static')
        ->and($actualContactKeys)->toBe($expectedContactKeys)
        ->and(array_values(array_unique($actualKeys)))->toHaveCount(85);
});

it('keeps pinned dashboard entries in an independent reorderable priority list', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    config()->set('dashboard-feed.items', [[
        'id' => 'release-note',
        'type' => 'changelog',
        'date' => '2026-09-01',
        'title' => 'Release note',
        'body' => 'A static feed entry that can also be pinned.',
    ]]);

    $first = ContactMessage::query()->create([
        'sender_name' => 'First Visitor',
        'sender_email' => 'first@example.test',
        'message' => 'First contact message',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);
    $second = ContactMessage::query()->create([
        'sender_name' => 'Second Visitor',
        'sender_email' => 'second@example.test',
        'message' => 'Second contact message',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    $pins = app(DashboardFeedPins::class);
    $firstKey = 'contact:'.$first->getKey();
    $secondKey = 'contact:'.$second->getKey();

    $pins->toggle($firstKey);
    $pins->toggle('static:release-note');
    $pins->toggle($secondKey);

    expect(array_column($pins->entries(), 'key'))->toBe([$secondKey, 'static:release-note', $firstKey])
        ->and(array_column($pins->entries(), 'pin_position'))->toBe([1, 2, 3]);

    $pins->move($firstKey, 0);
    expect(array_column($pins->entries(), 'key'))->toBe([$firstKey, $secondKey, 'static:release-note'])
        ->and(array_column($pins->entries(), 'pin_position'))->toBe([1, 2, 3]);

    $pins->toggle($secondKey);
    expect(array_column($pins->entries(), 'key'))->toBe([$firstKey, 'static:release-note'])
        ->and(array_column($pins->entries(), 'pin_position'))->toBe([1, 2]);
});

it('applies dashboard feed filters to the pinned priority list', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    config()->set('dashboard-feed.items', [[
        'id' => 'announcement',
        'type' => 'announcement',
        'date' => '2026-09-02',
        'title' => 'Important announcement',
        'body' => 'Pinned static announcement.',
    ]]);

    $contact = ContactMessage::query()->create([
        'sender_name' => 'Gallery Visitor',
        'sender_email' => 'visitor@example.test',
        'message' => 'Please send the catalogue.',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    $pins = app(DashboardFeedPins::class);
    $pins->toggle('static:announcement');
    $pins->toggle('contact:'.$contact->getKey());

    expect(array_column($pins->entries('', 'announcement'), 'key'))->toBe(['static:announcement'])
        ->and(array_column($pins->entries('catalogue', 'all'), 'key'))->toBe(['contact:'.$contact->getKey()]);
});
