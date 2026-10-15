<?php

use App\Domain\Contact\ContactMessageRetentionService;
use App\Models\ContactMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function retainedContactMessage(string $name, CarbonImmutable $createdAt): ContactMessage
{
    $message = ContactMessage::query()->create([
        'sender_name' => $name,
        'sender_email' => strtolower(str_replace(' ', '-', $name)).'@example.test',
        'message' => 'A private contact message',
    ]);

    $message->forceFill([
        'created_at' => $createdAt,
        'updated_at' => $createdAt,
    ])->saveQuietly();

    return $message;
}

it('previews old messages without deleting personal data', function (): void {
    $old = retainedContactMessage('Old sender', CarbonImmutable::now()->subDays(181));

    $this->artisan('contact:purge')->assertExitCode(0);

    expect(ContactMessage::query()->find($old->getKey()))->not->toBeNull();
});

it('deletes only records strictly older than 180 days, irrespective of delivery status', function (): void {
    $now = CarbonImmutable::parse('2026-10-15 12:00:00');
    $old = retainedContactMessage('Old', $now->subDays(181));
    $failed = retainedContactMessage('Failed', $now->subDays(200));
    $failed->markMailFailed();
    $boundary = retainedContactMessage('Boundary', $now->subDays(180));
    $recent = retainedContactMessage('Recent', $now->subDays(179));

    $service = app(ContactMessageRetentionService::class);
    expect($service->eligibleCount($now->subDays(180)))->toBe(2)
        ->and($service->purge($now->subDays(180)))->toBe(2)
        ->and($service->purge($now->subDays(180)))->toBe(0)
        ->and(ContactMessage::query()->pluck('id')->all())
        ->toEqualCanonicalizing([$boundary->getKey(), $recent->getKey()]);
});

it('supports a deliberate apply command while retaining recent messages', function (): void {
    $expired = retainedContactMessage('Expired', CarbonImmutable::now()->subDays(181));
    $recent = retainedContactMessage('Recent sender', CarbonImmutable::now()->subDays(2));

    $this->artisan('contact:purge --apply')->assertExitCode(0);

    expect(ContactMessage::query()->find($expired->getKey()))->toBeNull()
        ->and(ContactMessage::query()->find($recent->getKey()))->not->toBeNull();
});
