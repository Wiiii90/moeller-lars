<?php

use App\Domain\Admin\DashboardFeed;
use App\Filament\Pages\Dashboard;
use App\Models\ContactMessage;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
});

it('filters the Dashboard feed through Livewire and clears stale selections', function (): void {
    config()->set('dashboard-feed.items', []);

    $needle = ContactMessage::query()->create([
        'sender_name' => 'Distinctive Needle',
        'sender_email' => 'needle@example.test',
        'message' => 'Find this contact',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);
    $other = ContactMessage::query()->create([
        'sender_name' => 'Other Visitor',
        'sender_email' => 'other@example.test',
        'message' => 'Another contact',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    $component = Livewire::test(Dashboard::class)
        ->set('feedType', 'contact')
        ->call('toggleSelectAll');

    expect($component->get('selectedFeedKeys'))->toEqualCanonicalizing([
        'contact:'.$needle->getKey(),
        'contact:'.$other->getKey(),
    ]);

    $component->set('feedSearch', 'Distinctive Needle');
    expect($component->get('selectedFeedKeys'))->toBe([]);

    $component->call('toggleSelectAll');
    expect($component->get('selectedFeedKeys'))->toBe(['contact:'.$needle->getKey()]);

    $component->set('feedType', 'unsupported');
    expect($component->get('feedType'))->toBe('all')
        ->and($component->get('selectedFeedKeys'))->toBe([]);
});


it('marks contact read on opening and can mark it unread again', function (): void {
    $message = ContactMessage::query()->create([
        'sender_name' => 'Visitor',
        'sender_email' => 'visitor@example.test',
        'message' => 'Read state test',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    expect($message->getAttribute('read_at'))->toBeNull();

    $key = 'contact:'.$message->getKey();
    $component = Livewire::test(Dashboard::class)
        ->call('markFeedRead', $key)
        ->assertHasNoErrors();

    expect($message->refresh()->getAttribute('read_at'))->not->toBeNull()
        ->and(app(DashboardFeed::class)->entry($key)['status'])->toBe('Read');

    $component->call('markFeedUnread', $key)->assertHasNoErrors();

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

    Livewire::test(Dashboard::class)
        ->call('deleteFeedEntry', 'contact:'.$message->getKey())
        ->assertHasNoErrors();

    expect(ContactMessage::query()->whereKey($message->getKey())->exists())->toBeFalse()
        ->and(app(DashboardFeed::class)->entry('contact:'.$message->getKey()))->toBeNull();
});

it('refuses to mutate non-actionable announcements through Dashboard feed actions', function (): void {
    config()->set('dashboard-feed.items', [
        [
            'id' => 'notice',
            'type' => 'announcement',
            'date' => '2026-10-12',
            'title' => 'Studio information',
            'body' => 'Read-only studio notice',
        ],
    ]);
    $message = ContactMessage::query()->create([
        'sender_name' => 'Unrelated visitor',
        'sender_email' => 'unrelated@example.test',
        'message' => 'Must not be changed',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    Livewire::test(Dashboard::class)
        ->call('markFeedRead', 'static:notice')
        ->call('markFeedUnread', 'static:notice')
        ->call('deleteFeedEntry', 'static:notice')
        ->assertHasNoErrors();

    expect(app(DashboardFeed::class)->entry('static:notice'))->not->toBeNull()
        ->and($message->fresh()->getAttribute('read_at'))->toBeNull()
        ->and(ContactMessage::query()->whereKey($message->getKey())->exists())->toBeTrue();
});
