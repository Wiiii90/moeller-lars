<?php

use App\Mail\WebsiteContactMessage;
use App\Models\PublicContentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function publicContactSettings(): PublicContentSetting
{
    return PublicContentSetting::query()->sole();
}

function validContactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'message' => 'Hello Lars',
        'company' => '',
    ], $overrides);
}

it('keeps hidden contact unavailable and renders the explicit construction state', function () {
    publicContactSettings()->update(['contact_state' => 'hidden']);
    $this->get('/contact')->assertNotFound();

    publicContactSettings()->update([
        'contact_state' => 'under_construction',
        'contact_status_text' => 'Contact is being prepared.',
        'contact_icon' => 'info',
    ]);

    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('Contact is being prepared.')
        ->assertDontSee('<form', false);
});

it('uses the private settings recipient and the configured site sender identity', function () {
    config([
        'contact.recipient' => 'fallback@example.test',
        'mail.default' => 'smtp',
        'mail.from.address' => 'website@moeller-lars.de',
        'mail.from.name' => 'Lars Möller Website',
    ]);
    Mail::fake();
    publicContactSettings()->update([
        'contact_state' => 'enabled',
        'public_email' => 'public@example.test',
        'show_public_email' => true,
        'contact_recipient_email' => 'private@example.test',
    ]);

    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('public@example.test')
        ->assertDontSee('private@example.test');

    $this->from('/contact')->post('/contact', validContactPayload())
        ->assertRedirect('/contact')
        ->assertSessionHas('contact_success');

    Mail::assertSent(WebsiteContactMessage::class, function (WebsiteContactMessage $mail): bool {
        $envelope = $mail->envelope();
        $from = $envelope->from;
        $replyTo = $envelope->replyTo[0] ?? null;

        return $mail->hasTo('private@example.test')
            && $from instanceof Address
            && $from->address === 'website@moeller-lars.de'
            && $replyTo instanceof Address
            && $replyTo->address === 'visitor@example.test';
    });
});

it('falls back to the runtime recipient when the private recipient is empty', function () {
    config([
        'contact.recipient' => 'fallback@example.test',
        'mail.default' => 'smtp',
        'mail.from.address' => 'website@moeller-lars.de',
        'mail.from.name' => 'Website',
    ]);
    Mail::fake();
    publicContactSettings()->update([
        'contact_state' => 'enabled',
        'contact_recipient_email' => null,
    ]);

    $this->post('/contact', validContactPayload())->assertSessionHas('contact_success');

    Mail::assertSent(WebsiteContactMessage::class, fn (WebsiteContactMessage $mail): bool => $mail->hasTo('fallback@example.test'));
});

it('validates the minimal form and keeps csrf plus the honeypot internal', function () {
    config(['contact.recipient' => 'artist@example.test']);
    publicContactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', validContactPayload([
        'name' => '',
        'email' => 'not-an-email',
        'message' => '',
    ]))->assertSessionHasErrors(['name', 'email', 'message']);

    $this->from('/contact')->post('/contact', validContactPayload([
        'company' => 'spam',
    ]))->assertSessionHasErrors('company');

    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('name="_token"', false)
        ->assertDontSee('name="website"', false);
});

it('rate limits repeated contact submissions', function () {
    config([
        'contact.recipient' => 'artist@example.test',
        'mail.default' => 'smtp',
        'mail.from.address' => 'website@moeller-lars.de',
    ]);
    Mail::fake();
    publicContactSettings()->update(['contact_state' => 'enabled']);

    foreach (range(1, 5) as $attempt) {
        $this->post('/contact', validContactPayload(['message' => "Attempt {$attempt}"]))
            ->assertRedirect();
    }

    $this->post('/contact', validContactPayload(['message' => 'Attempt 6']))
        ->assertTooManyRequests();
});

it('reports missing mail configuration as failure instead of success', function () {
    config([
        'contact.recipient' => null,
        'mail.from.address' => null,
    ]);
    publicContactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', validContactPayload())
        ->assertSessionHasErrors('contact')
        ->assertSessionMissing('contact_success');
});
