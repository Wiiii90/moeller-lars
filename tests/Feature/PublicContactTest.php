<?php

use App\Models\CvEntry;
use App\Models\PublicContentSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function contactSettings(): PublicContentSetting
{
    return PublicContentSetting::query()->findOrFail(1);
}

it('keeps contact unavailable while hidden', function () {
    $this->get('/contact')->assertNotFound();
    $this->post('/contact', [])->assertNotFound();
});

it('shows configured under-construction state without an active form', function () {
    contactSettings()->update([
        'contact_state' => 'under_construction',
        'contact_status_text' => 'Contact is being prepared.',
        'contact_icon' => 'info',
    ]);

    $this->get('/contact')
        ->assertSuccessful()
        ->assertSee('Contact is being prepared.')
        ->assertDontSee('<form', false);
});

it('renders contact after CV content when the CV surface is public', function () {
    contactSettings()->update([
        'cv_enabled' => true,
        'contact_state' => 'under_construction',
        'contact_status_text' => 'Contact follows the CV.',
    ]);

    CvEntry::create([
        'section' => 'Biography',
        'title' => 'CV content',
        'state' => 'published',
        'position' => 0,
        'date_precision' => 'year',
        'year_text' => '2026',
    ]);

    $response = $this->get('/cv')->assertSuccessful();
    $body = $response->getContent();

    expect(strpos($body, 'CV content'))->toBeLessThan(strpos($body, 'Contact follows the CV.'));
});

it('sends validated contact mail without storing a message record', function () {
    Mail::fake();
    config(['contact.recipient' => 'artist@example.test']);
    contactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'website' => 'https://example.test',
        'comment' => 'Hello Lars',
        'company' => '',
    ])->assertRedirect('/contact')->assertSessionHas('contact_success');

    Mail::assertSentCount(1);
});

it('rejects unsafe websites and the honeypot', function () {
    config(['contact.recipient' => 'artist@example.test']);
    contactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'website' => 'javascript:alert(1)',
        'comment' => 'Hello',
        'company' => '',
    ])->assertRedirect('/contact')->assertSessionHasErrors('website');

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'comment' => 'Hello',
        'company' => 'spam',
    ])->assertRedirect('/contact')->assertSessionHasErrors('company');
});

it('reports missing delivery configuration as failure rather than success', function () {
    config(['contact.recipient' => null]);
    contactSettings()->update(['contact_state' => 'enabled']);

    $this->from('/contact')->post('/contact', [
        'name' => 'Visitor',
        'email' => 'visitor@example.test',
        'comment' => 'Hello',
        'company' => '',
    ])->assertRedirect('/contact')
        ->assertSessionHasErrors('contact')
        ->assertSessionMissing('contact_success');
});
