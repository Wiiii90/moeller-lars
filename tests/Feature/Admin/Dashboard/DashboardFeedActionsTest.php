<?php

use App\Domain\Admin\DashboardFeed;
use App\Domain\Admin\DashboardFeedPins;
use App\Filament\Pages\Dashboard;
use App\Models\AdminNotification;
use App\Models\AuditEvent;
use App\Models\ContactMessage;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    config()->set('dashboard-feed.items', []);
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

function dashboardContact(string $name): ContactMessage
{
    return ContactMessage::query()->create([
        'sender_name' => $name, 'sender_email' => strtolower($name).'@example.test',
        'message' => 'Contact from '.$name,
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);
}

it('rejects mounted Dashboard requests after administrative access is lost', function (bool $guest): void {
    $actor = Auth::user();
    $message = dashboardContact('Protected');
    $component = Livewire::test(Dashboard::class)->set('selectedFeedKeys', ['contact:'.$message->id]);
    if ($guest) {
        Auth::guard('web')->logout();
    } else {
        $actor->forceFill(['is_admin' => false])->save();
    }
    $component->call('bulkDelete')->assertForbidden();
    expect($message->fresh())->not->toBeNull()
        ->and(AdminNotification::query()->count())->toBe(0);
})->with(['guest' => true, 'revoked administrator' => false]);

it('keeps another administrators notifications private across feed actions', function (): void {
    $other = User::factory()->admin()->create();
    $notification = AdminNotification::query()->create([
        'user_id' => $other->id, 'source_id' => 'private:other', 'type' => 'system',
        'status' => 'success', 'title' => 'Private other administrator',
    ]);
    $key = 'notification:'.$notification->id;
    Livewire::test(Dashboard::class)->assertDontSee($notification->title)
        ->call('markFeedRead', $key)->call('markFeedUnread', $key)
        ->call('toggleFeedPin', $key)->call('deleteFeedEntry', $key)
        ->set('selectedFeedKeys', [$key])->call('bulkDelete')
        ->assertNotDispatched('admin-header-notification');
    expect($notification->fresh()->read_at)->toBeNull()
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(DB::table('dashboard_feed_pins')->count())->toBe(0);
});

it('pins selects and changes a mixed inbox without generating self notifications', function (): void {
    config()->set('dashboard-feed.items', []);
    $contact = dashboardContact('Mixed');
    $notification = AdminNotification::query()->create([
        'user_id' => Auth::id(), 'source_id' => 'mixed:notification', 'type' => 'system',
        'status' => 'info', 'title' => 'Mixed inbox notification',
    ]);
    $keys = ['contact:'.$contact->id, 'notification:'.$notification->id];
    $auditsBefore = AuditEvent::query()->count();
    $component = Livewire::test(Dashboard::class)
        ->set('selectedFeedKeys', $keys)->call('bulkPin')->assertSet('selectedFeedKeys', [])
        ->set('selectedFeedKeys', $keys)->call('bulkPin');
    expect(DB::table('dashboard_feed_pins')->count())->toBe(2);
    $component->set('selectedFeedKeys', $keys)->call('bulkMarkRead')->assertSet('selectedFeedKeys', []);
    expect($contact->fresh()->read_at)->not->toBeNull()
        ->and($notification->fresh()->read_at)->not->toBeNull();
    $component->set('selectedFeedKeys', $keys)->call('bulkMarkUnread');
    expect($contact->fresh()->read_at)->toBeNull()
        ->and($notification->fresh()->read_at)->toBeNull();
    $component->set('selectedFeedKeys', $keys)->call('bulkUnpin')->assertSet('selectedFeedKeys', []);
    expect(DB::table('dashboard_feed_pins')->count())->toBe(0);
    $component->set('selectedFeedKeys', ['notification:'.$notification->id])->call('bulkDelete')
        ->assertNotDispatched('admin-header-notification');
    $component->set('selectedFeedKeys', ['notification:'.$notification->id])->call('bulkDelete')
        ->assertNotDispatched('admin-header-notification');
    expect($notification->fresh())->toBeNull()
        ->and($contact->fresh())->not->toBeNull()
        ->and(AdminNotification::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore);
});

it('preserves the inbox and pins when single or batch deletion fails', function (bool $bulk): void {
    $contact = dashboardContact('Rollback');
    $notification = AdminNotification::query()->create([
        'user_id' => Auth::id(), 'source_id' => 'failure:delete', 'type' => 'system',
        'status' => 'info', 'title' => 'Pinned notification',
    ]);
    $key = 'notification:'.$notification->id;
    app(DashboardFeedPins::class)->toggle($key);
    app(DashboardFeedPins::class)->toggle('contact:'.$contact->id);
    $dispatcher = AdminNotification::getEventDispatcher();
    AdminNotification::setEventDispatcher(clone $dispatcher);
    AdminNotification::deleting(static function (): void { throw new RuntimeException('Delete unavailable'); });
    try {
        $component = Livewire::test(Dashboard::class);
        expect(fn () => $bulk
            ? $component->set('selectedFeedKeys', ['contact:'.$contact->id, $key])->call('bulkDelete')
            : $component->call('deleteFeedEntry', $key))
            ->toThrow(RuntimeException::class, 'Delete unavailable');
        expect($notification->fresh())->not->toBeNull()
            ->and($contact->fresh())->not->toBeNull()
            ->and(DB::table('dashboard_feed_pins')->count())->toBe(2);
    } finally {
        AdminNotification::setEventDispatcher($dispatcher);
    }
})->with(['single' => false, 'batch' => true]);

it('keeps read unread and repeated inbox no ops silent', function (): void {
    $contact = dashboardContact('Silent');
    $auditsBefore = AuditEvent::query()->count();
    $component = Livewire::test(Dashboard::class)
        ->call('markContactUnread', $contact->id)->assertNotDispatched('admin-header-notification')
        ->call('openFeedEntry', 'contact:'.$contact->id)->assertMountedActionModalSee('Contact from Silent');
    expect($contact->fresh()->read_at)->not->toBeNull();
    $component->call('markContactUnread', $contact->id)->assertNotDispatched('admin-header-notification');
    expect($contact->fresh()->read_at)->toBeNull();
    $component->call('markContactUnread', $contact->id)->assertNotDispatched('admin-header-notification');
    expect(AdminNotification::query()->count())->toBe(0)
        ->and(AuditEvent::query()->count())->toBe($auditsBefore);
});

it('renders current feed data and resets selection when changing pages and filters', function (): void {
    config()->set('dashboard-feed.items', []);
    foreach (range(1, 26) as $index) { dashboardContact('Visitor'.$index); }
    $component = Livewire::test(Dashboard::class)->set('feedType', 'contact')->set('feedPageSize', 25)
        ->assertSee('visitor26@example.test')->assertDontSee('visitor1@example.test')
        ->call('toggleSelectAll');
    expect($component->get('selectedFeedKeys'))->toHaveCount(25);
    $component->call('nextFeedPage')->assertSet('feedPage', 2)->assertSet('selectedFeedKeys', [])
        ->assertSee('visitor1@example.test')->assertDontSee('visitor26@example.test')
        ->set('feedSearch', 'Visitor26')->assertSet('feedPage', 1)->assertSee('visitor26@example.test')
        ->assertDontSee('visitor1@example.test');
    $contact = ContactMessage::query()->where('sender_name', 'Visitor26')->sole();
    $contact->update(['message' => 'Updated persisted content']);
    $component->call('openFeedEntry', 'contact:'.$contact->id)
        ->assertMountedActionModalSee('Updated persisted content');
});

it('rolls back all selected read states when one inbox update fails', function (bool $unread): void {
    $contact = dashboardContact('ReadRollback');
    $notification = AdminNotification::query()->create([
        'user_id' => Auth::id(), 'source_id' => 'failure:read', 'type' => 'system',
        'status' => 'info', 'title' => 'Read rollback',
    ]);
    if ($unread) {
        $contact->markRead();
        $notification->markRead();
    }
    $before = $contact->fresh()->read_at?->toIso8601String();
    $notificationBefore = $notification->fresh()->read_at?->toIso8601String();
    $dispatcher = AdminNotification::getEventDispatcher();
    AdminNotification::setEventDispatcher(clone $dispatcher);
    AdminNotification::updating(static function (): void { throw new RuntimeException('Read update unavailable'); });
    try {
        $component = Livewire::test(Dashboard::class)->set('selectedFeedKeys', ['contact:'.$contact->id, 'notification:'.$notification->id]);
        expect(fn () => $component->call($unread ? 'bulkMarkUnread' : 'bulkMarkRead'))
            ->toThrow(RuntimeException::class, 'Read update unavailable');
        expect($contact->fresh()->read_at?->toIso8601String())->toBe($before)
            ->and($notification->fresh()->read_at?->toIso8601String())->toBe($notificationBefore)
            ->and(AdminNotification::query()->count())->toBe(1);
    } finally {
        AdminNotification::setEventDispatcher($dispatcher);
    }
})->with(['mark read' => false, 'mark unread' => true]);

it('notifies one completed contact deletion and stays silent on repeated deletion', function (): void {
    $contact = dashboardContact('DeleteOnce');
    app(DashboardFeedPins::class)->toggle('contact:'.$contact->id);
    $component = Livewire::test(Dashboard::class)->call('deleteContactMessage', $contact->id)
        ->assertDontSee('deleteonce@example.test')->assertDispatched('admin-header-notification');
    expect($contact->fresh())->toBeNull()
        ->and(DB::table('dashboard_feed_pins')->count())->toBe(0)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($component->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $component->call('deleteContactMessage', $contact->id)->assertNotDispatched('admin-header-notification');
    expect(AdminNotification::query()->count())->toBe(1);
});
