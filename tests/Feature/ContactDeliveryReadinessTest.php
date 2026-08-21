<?php

use App\Domain\Contact\ContactDeliveryReadiness;
use App\Models\PublicContentSetting;

it('reports contact delivery readiness without exposing transport secrets', function (): void {
    $general = PublicContentSetting::general();
    $general->setAttribute('contact_recipient_email', 'artist@example.test');
    $general->save();

    config([
        'contact.recipient' => 'fallback@example.test',
        'mail.default' => 'website-smtp',
        'mail.from.address' => 'website@example.test',
        'mail.from.name' => 'Lars Möller Website',
        'mail.mailers.website-smtp' => ['transport' => 'smtp'],
    ]);

    $snapshot = app(ContactDeliveryReadiness::class)->snapshot($general);

    expect($snapshot)->toBe([
        'recipient_ready' => true,
        'recipient_source' => 'General',
        'sender_ready' => true,
        'mailer_ready' => true,
        'ready' => true,
    ]);
});

it('uses the server recipient fallback and fails closed when mail delivery is incomplete', function (): void {
    $general = PublicContentSetting::general();
    $general->setAttribute('contact_recipient_email', null);
    $general->save();

    config([
        'contact.recipient' => 'fallback@example.test',
        'mail.default' => 'website-smtp',
        'mail.from.address' => null,
        'mail.mailers.website-smtp' => ['transport' => 'smtp'],
    ]);

    $snapshot = app(ContactDeliveryReadiness::class)->snapshot($general);

    expect($snapshot['recipient_ready'])->toBeTrue()
        ->and($snapshot['recipient_source'])->toBe('Server fallback')
        ->and($snapshot['sender_ready'])->toBeFalse()
        ->and($snapshot['mailer_ready'])->toBeTrue()
        ->and($snapshot['ready'])->toBeFalse();
});
