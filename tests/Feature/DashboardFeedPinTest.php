<?php

use App\Domain\Admin\DashboardFeedPins;
use App\Models\ContactMessage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('keeps pinned dashboard entries in an independent reorderable priority list', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    config()->set('dashboard-feed.items', [
        [
            'id' => 'release-note',
            'type' => 'changelog',
            'date' => '2026-09-01',
            'title' => 'Release note',
            'body' => 'A static feed entry that can also be pinned.',
        ],
    ]);

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

    $entries = $pins->entries();

    expect(array_column($entries, 'key'))->toBe([
        $secondKey,
        'static:release-note',
        $firstKey,
    ])->and(array_column($entries, 'pin_position'))->toBe([1, 2, 3]);

    $pins->move($firstKey, 0);
    $entries = $pins->entries();

    expect(array_column($entries, 'key'))->toBe([
        $firstKey,
        $secondKey,
        'static:release-note',
    ])->and(array_column($entries, 'pin_position'))->toBe([1, 2, 3]);

    $pins->toggle($secondKey);
    $entries = $pins->entries();

    expect(array_column($entries, 'key'))->toBe([
        $firstKey,
        'static:release-note',
    ])->and(array_column($entries, 'pin_position'))->toBe([1, 2]);
});

it('applies dashboard feed filters to the pinned priority list', function (): void {
    $user = User::factory()->create();
    $this->actingAs($user);

    config()->set('dashboard-feed.items', [
        [
            'id' => 'announcement',
            'type' => 'announcement',
            'date' => '2026-09-02',
            'title' => 'Important announcement',
            'body' => 'Pinned static announcement.',
        ],
    ]);

    $contact = ContactMessage::query()->create([
        'sender_name' => 'Gallery Visitor',
        'sender_email' => 'visitor@example.test',
        'message' => 'Please send the catalogue.',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    $pins = app(DashboardFeedPins::class);
    $pins->toggle('static:announcement');
    $pins->toggle('contact:'.$contact->getKey());

    expect(array_column($pins->entries('', 'announcement'), 'key'))
        ->toBe(['static:announcement'])
        ->and(array_column($pins->entries('catalogue', 'all'), 'key'))
        ->toBe(['contact:'.$contact->getKey()]);
});
