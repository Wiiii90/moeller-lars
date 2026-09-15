<?php

use App\Filament\Pages\Dashboard;
use App\Models\ContactMessage;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('reuses one Dashboard feed entry projection within the component instance', function (): void {
    $firstMessage = ContactMessage::query()->create([
        'sender_name' => 'First visitor',
        'sender_email' => 'first@example.test',
        'message' => 'First message',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);
    $secondMessage = ContactMessage::query()->create([
        'sender_name' => 'Second visitor',
        'sender_email' => 'second@example.test',
        'message' => 'Second message',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    $contactSelects = [];
    DB::listen(function (QueryExecuted $query) use (&$contactSelects): void {
        $sql = strtolower(ltrim($query->sql));
        if (str_starts_with($sql, 'select') && str_contains($sql, 'contact_messages')) {
            $contactSelects[] = $query->sql;
        }
    });

    $dashboard = new Dashboard;
    $feedEntry = new ReflectionMethod($dashboard, 'feedEntry');
    $firstKey = 'contact:'.$firstMessage->getKey();
    $secondKey = 'contact:'.$secondMessage->getKey();

    $first = $feedEntry->invoke($dashboard, ['key' => $firstKey]);
    $repeat = $feedEntry->invoke($dashboard, ['key' => $firstKey]);
    $second = $feedEntry->invoke($dashboard, ['key' => $secondKey]);

    expect($first)->toBe($repeat)
        ->and($first['key'])->toBe($firstKey)
        ->and($second['key'])->toBe($secondKey)
        ->and($contactSelects)->toHaveCount(2);
});

it('resolves each selected Dashboard feed entry once when computing capabilities', function (): void {
    $firstMessage = ContactMessage::query()->create([
        'sender_name' => 'First selected visitor',
        'sender_email' => 'first-selected@example.test',
        'message' => 'First selected message',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);
    $secondMessage = ContactMessage::query()->create([
        'sender_name' => 'Second selected visitor',
        'sender_email' => 'second-selected@example.test',
        'message' => 'Second selected message',
        'mail_delivery_status' => ContactMessage::DELIVERY_DELIVERED,
    ]);

    $contactSelects = [];
    DB::listen(function (QueryExecuted $query) use (&$contactSelects): void {
        $sql = strtolower(ltrim($query->sql));
        if (str_starts_with($sql, 'select') && str_contains($sql, 'contact_messages')) {
            $contactSelects[] = $query->sql;
        }
    });

    $dashboard = new Dashboard;
    $dashboard->selectedFeedKeys = [
        'contact:'.$firstMessage->getKey(),
        'contact:'.$secondMessage->getKey(),
    ];
    $selectionCapabilities = new ReflectionMethod($dashboard, 'selectionCapabilities');

    $capabilities = $selectionCapabilities->invoke($dashboard);

    expect($capabilities)->toBe([
        'has_selection' => true,
        'all_mutable' => true,
    ])->and($contactSelects)->toHaveCount(2);
});
