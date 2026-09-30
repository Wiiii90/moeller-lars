<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Publication\PublicationSchemaGuard;
use App\Domain\Publication\PublicationService;
use App\Mail\WebsiteContactMessage;
use App\Models\ContactMessage;
use App\Models\CustomPageSetting;
use App\Models\PublicationCheckpoint;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\PendingMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

uses(RefreshDatabase::class);

function publicationReviewContactPayload(): array
{
    return [
        'name' => 'Publication review visitor',
        'email' => 'visitor@example.test',
        'message' => 'Committed contact snapshot probe',
        'company' => '',
    ];
}

it('reads Contact publication decisions in a short committed transaction and writes and mails outside it', function (): void {
    config([
        'contact.recipient' => 'fallback@example.test',
        'mail.default' => 'smtp',
        'mail.from.address' => 'website@moeller-lars.de',
        'mail.from.name' => 'Publication review',
    ]);

    $actor = User::factory()->admin()->create();
    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_CUSTOM,
        'template' => null,
        'title' => 'Committed Contact',
        'navigation_label' => 'Committed Contact',
        'slug' => 'committed-contact',
        'state' => 'published',
        'position' => 920,
        'show_in_navigation' => false,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);
    $page = new CustomPageSetting;
    $page->setAttribute('site_section_id', $section->getKey());
    $page->setAttribute('blocks', [[
        'type' => 'contact',
        'published' => true,
        'children' => [
            ['type' => 'public_email', 'published' => false],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => []],
            ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null],
        ],
    ]]);
    $page->save();

    PublicContentSetting::general()->update(['contact_recipient_email' => 'committed@example.test']);
    expect(app(PublicationService::class)->commit($actor, 'Commit contact state'))->toBeInstanceOf(PublicationCheckpoint::class);

    $page->setAttribute('blocks', [[
        'type' => 'contact',
        'published' => true,
        'children' => [
            ['type' => 'public_email', 'published' => false],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => []],
            ['type' => 'contact_form', 'published' => false, 'form_state' => 'enabled', 'status_text' => null],
        ],
    ]]);
    $page->save();
    PublicContentSetting::general()->update(['contact_recipient_email' => 'working@example.test']);

    $baselineTransactionLevel = DB::transactionLevel();
    $contactWriteLevel = null;
    DB::listen(function (QueryExecuted $query) use (&$contactWriteLevel): void {
        if (str_contains(strtolower($query->sql), 'contact_messages') && str_starts_with(strtolower(ltrim($query->sql)), 'insert')) {
            $contactWriteLevel = DB::transactionLevel();
        }
    });

    $pendingMail = Mockery::mock(PendingMail::class);
    $pendingMail->shouldReceive('send')
        ->once()
        ->with(Mockery::type(WebsiteContactMessage::class))
        ->andReturnUsing(function () use ($baselineTransactionLevel): void {
            expect(DB::transactionLevel())->toBe($baselineTransactionLevel);
        });

    Mail::shouldReceive('to')
        ->once()
        ->with('committed@example.test')
        ->andReturn($pendingMail);

    $this->post('/contact', publicationReviewContactPayload())
        ->assertRedirect()
        ->assertSessionHas('contact_success', 'Your message was received.');

    expect($contactWriteLevel)->toBe($baselineTransactionLevel)
        ->and(ContactMessage::query()->sole()->getAttribute('mail_delivery_status'))->toBe(ContactMessage::DELIVERY_DELIVERED);
});

it('fails closed before snapshot promotion when committed schema parity drifts', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    app(PublicationSchemaGuard::class)->assertParity();
    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'legal_disclaimer' => 'Schema guard pending '.fake()->uuid(),
    ]);
    $checkpointCount = PublicationCheckpoint::query()->count();

    DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN publication_guard_probe text');

    expect(fn () => app(PublicationService::class)->commit($actor, 'Must fail closed'))
        ->toThrow(RuntimeException::class, 'Publication snapshot schema drift detected for public_content_settings.')
        ->and(PublicationCheckpoint::query()->count())->toBe($checkpointCount)
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue();
});
