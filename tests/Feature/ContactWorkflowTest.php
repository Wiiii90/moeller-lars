<?php

use App\Mail\WebsiteContactMessage;
use App\Models\CustomPageSetting;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function enablePublishedContactForm(): void
{
    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_CUSTOM,
        'template' => null,
        'title' => 'Contact test page',
        'navigation_label' => 'Contact test page',
        'slug' => 'contact-test-page',
        'state' => 'published',
        'position' => 900,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $settings = new CustomPageSetting;
    $settings->setAttribute('site_section_id', $section->id);
    $settings->setAttribute('blocks', [[
        'type' => 'contact',
        'show_form' => true,
        'show_email' => true,
        'social_platforms' => [],
        'form_state' => 'enabled',
    ]]);
    $settings->save();
}

function contactPayload(array $overrides = []): array
{
    return array_merge([
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'message' => 'Hello Lars',
        'company' => '',
    ], $overrides);
}

it('delivers to the private General recipient with the configured sender and visitor Reply-To', function (): void {
    config([
        'contact.recipient' => 'fallback@example.test',
        'mail.default' => 'smtp',
        'mail.from.address' => 'website@moeller-lars.de',
        'mail.from.name' => 'Lars Möller Website',
    ]);
    Mail::fake();
    enablePublishedContactForm();
    PublicContentSetting::general()->update([
        'contact_recipient_email' => 'private@example.test',
    ]);

    $this->post('/contact', contactPayload())
        ->assertRedirect()
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

it('falls back to the runtime recipient when General has no private recipient', function (): void {
    config([
        'contact.recipient' => 'fallback@example.test',
        'mail.default' => 'smtp',
        'mail.from.address' => 'website@moeller-lars.de',
        'mail.from.name' => 'Website',
    ]);
    Mail::fake();
    enablePublishedContactForm();
    PublicContentSetting::general()->update(['contact_recipient_email' => null]);

    $this->post('/contact', contactPayload())->assertSessionHas('contact_success');

    Mail::assertSent(WebsiteContactMessage::class, fn (WebsiteContactMessage $mail): bool => $mail->hasTo('fallback@example.test'));
});

it('validates required fields and rejects the honeypot', function (): void {
    config(['contact.recipient' => 'artist@example.test']);
    enablePublishedContactForm();

    $this->post('/contact', contactPayload([
        'name' => '',
        'email' => 'not-an-email',
        'message' => '',
    ]))->assertSessionHasErrors(['name', 'email', 'message']);

    $this->post('/contact', contactPayload(['company' => 'spam']))
        ->assertSessionHasErrors('company');
});

it('rate limits repeated contact submissions', function (): void {
    config([
        'contact.recipient' => 'artist@example.test',
        'mail.default' => 'smtp',
        'mail.from.address' => 'website@moeller-lars.de',
    ]);
    Mail::fake();
    enablePublishedContactForm();

    foreach (range(1, 5) as $attempt) {
        $this->post('/contact', contactPayload(['message' => "Attempt {$attempt}"]))
            ->assertRedirect();
    }

    $this->post('/contact', contactPayload(['message' => 'Attempt 6']))
        ->assertTooManyRequests();
});

it('fails closed when delivery configuration is incomplete', function (): void {
    config([
        'contact.recipient' => null,
        'mail.from.address' => null,
    ]);
    enablePublishedContactForm();
    PublicContentSetting::general()->update(['contact_recipient_email' => null]);

    $this->post('/contact', contactPayload())
        ->assertSessionHasErrors('contact')
        ->assertSessionMissing('contact_success');
});

it('requires an enabled Contact component on a published Custom Page', function (): void {
    Mail::fake();

    $this->post('/contact', contactPayload())->assertNotFound();

    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_CUSTOM,
        'template' => null,
        'title' => 'Private Contact',
        'navigation_label' => 'Private Contact',
        'slug' => 'private-contact',
        'state' => 'hidden',
        'position' => 901,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    $settings = new CustomPageSetting;
    $settings->setAttribute('site_section_id', $section->id);
    $settings->setAttribute('blocks', [[
        'type' => 'contact',
        'show_form' => true,
        'show_email' => false,
        'social_platforms' => [],
        'form_state' => 'enabled',
    ]]);
    $settings->save();

    $this->post('/contact', contactPayload())->assertNotFound();

    $section->update(['state' => 'published']);
    $settings->update(['blocks' => [[
        'type' => 'contact',
        'show_form' => true,
        'show_email' => false,
        'social_platforms' => [],
        'form_state' => 'hidden',
    ]]]);

    $this->post('/contact', contactPayload())->assertNotFound();
});
