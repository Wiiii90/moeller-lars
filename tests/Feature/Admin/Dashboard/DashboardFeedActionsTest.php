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
