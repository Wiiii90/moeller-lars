<?php

use App\Models\PublicContentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function publicContactSettings(): PublicContentSetting
{
    return PublicContentSetting::query()->sole();
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

it('accepts a valid enabled submission with a delivering mailer configured', function () {
    config([
        'contact.recipient' => 'artist@example.test',
        'mail.default' => 'smtp',
    ]);
    Mail::fake();
    publicContactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'website' => 'https://example.test',
        'comment' => 'Hello Lars',
        'company' => '',
    ])->assertRedirect('/contact')->assertSessionHas('contact_success');
});

it('rejects unsafe websites and honeypot submissions', function () {
    config(['contact.recipient' => 'artist@example.test']);
    publicContactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor', 'email' => 'visitor@example.test', 'website' => 'javascript:alert(1)', 'comment' => 'Hello', 'company' => '',
    ])->assertSessionHasErrors('website');

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor', 'email' => 'visitor@example.test', 'comment' => 'Hello', 'company' => 'spam',
    ])->assertSessionHasErrors('company');
});

it('reports missing mail configuration as failure instead of success', function () {
    config(['contact.recipient' => null]);
    publicContactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor', 'email' => 'visitor@example.test', 'comment' => 'Hello', 'company' => '',
    ])->assertSessionHasErrors('contact')->assertSessionMissing('contact_success');
});
