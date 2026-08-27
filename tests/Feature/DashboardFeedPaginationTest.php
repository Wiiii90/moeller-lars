<?php

use App\Domain\Admin\DashboardFeed;
use App\Models\ContactMessage;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps all-feed pagination lossless across static and contact sources', function (): void {
    config()->set('dashboard-feed.items', [
        [
            'id' => 'newer-static',
            'type' => 'announcement',
            'date' => '2026-08-28',
            'title' => 'Newer announcement',
            'body' => 'This sorts before the contact window.',
        ],
        [
            'id' => 'older-static',
            'type' => 'changelog',
            'date' => '2026-08-24',
            'title' => 'Older changelog',
            'body' => 'This sorts after the contact window.',
        ],
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

    rsort($contactIds);
    $expectedKeys = [
        'static:newer-static',
        ...array_map(static fn (int $id): string => 'contact:'.$id, $contactIds),
        'static:older-static',
    ];

    $feed = app(DashboardFeed::class);
    $pages = [];
    foreach (range(1, 4) as $page) {
        $pages[$page] = $feed->paginate('', 'all', $page, 25);
    }

    $actualKeys = collect($pages)
        ->flatMap(static fn (array $pagination): array => collect($pagination['items'])->pluck('key')->all())
        ->values()
        ->all();

    expect($pages[1]['total'])->toBe(85)
        ->and($pages[1]['pages'])->toBe(4)
        ->and([$pages[1]['start'], $pages[1]['end']])->toBe([1, 25])
        ->and([$pages[2]['start'], $pages[2]['end']])->toBe([26, 50])
        ->and([$pages[3]['start'], $pages[3]['end']])->toBe([51, 75])
        ->and([$pages[4]['start'], $pages[4]['end']])->toBe([76, 85])
        ->and($actualKeys)->toBe($expectedKeys)
        ->and(array_values(array_unique($actualKeys)))->toHaveCount(85);
});
