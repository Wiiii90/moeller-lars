<?php

use App\Domain\Admin\AdminActivityFeed;
use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Publication\PublicationSchemaGuard;
use App\Domain\Publication\PublicationService;
use App\Mail\WebsiteContactMessage;
use App\Models\AuditEvent;
use App\Models\ContactMessage;
use App\Models\CustomPageSetting;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\PublicationEventState;
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

it('keeps net-zero audit generations out of a later checkpoint and marks them not pending', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $settings = PublicContentSetting::general();
    $original = $settings->getAttribute('legal_disclaimer');
    $original = is_string($original) ? $original : null;
    $editor = app(AdminSettingsService::class);

    $editor->updatePublicContent($settings, ['legal_disclaimer' => 'Temporary '.fake()->uuid()]);
    $first = AuditEvent::query()->where('action', 'public_content_setting.updated')->latest('id')->firstOrFail();

    $editor->updatePublicContent($settings->fresh(), ['legal_disclaimer' => $original]);
    $second = AuditEvent::query()->where('action', 'public_content_setting.updated')->latest('id')->firstOrFail();

    expect(app(PublicationService::class)->hasPendingChanges())->toBeFalse()
        ->and(PublicationEventState::query()->where('audit_event_id', $first->getKey())->value('status'))->toBe(PublicationEventState::STATUS_NOT_PENDING)
        ->and(PublicationEventState::query()->where('audit_event_id', $second->getKey())->value('status'))->toBe(PublicationEventState::STATUS_NOT_PENDING);

    $activity = collect(app(AdminActivityFeed::class)->recent(20));
    expect($activity->firstWhere('id', (int) $first->getKey())['publication_status'])->toBe(PublicationEventState::STATUS_NOT_PENDING)
        ->and($activity->firstWhere('id', (int) $second->getKey())['publication_status'])->toBe(PublicationEventState::STATUS_NOT_PENDING);

    $nonPublication = app(AdminAuditService::class)->record($actor, 'blog_setting.updated', 'blog_setting', 1);
    $editor->updatePublicContent($settings->fresh(), ['legal_disclaimer' => 'Actually pending '.fake()->uuid()]);
    $third = AuditEvent::query()->where('action', 'public_content_setting.updated')->latest('id')->firstOrFail();

    expect(app(PublicationService::class)->hasPendingChanges())->toBeTrue()
        ->and(PublicationEventState::query()->where('audit_event_id', $third->getKey())->value('status'))->toBe(PublicationEventState::STATUS_PENDING);

    $checkpoint = app(PublicationService::class)->commit($actor, 'Only the active generation');

    expect($checkpoint)->toBeInstanceOf(PublicationCheckpoint::class)
        ->and(PublicationCheckpointEvent::query()->where('audit_event_id', $first->getKey())->exists())->toBeFalse()
        ->and(PublicationCheckpointEvent::query()->where('audit_event_id', $second->getKey())->exists())->toBeFalse()
        ->and(PublicationCheckpointEvent::query()->where('audit_event_id', $nonPublication->getKey())->exists())->toBeFalse()
        ->and((int) PublicationCheckpointEvent::query()->where('audit_event_id', $third->getKey())->value('publication_checkpoint_id'))->toBe((int) $checkpoint->getKey());
});

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
        'show_form' => true,
        'show_email' => false,
        'social_platforms' => [],
        'form_state' => 'enabled',
    ]]);
    $page->save();

    PublicContentSetting::general()->update(['contact_recipient_email' => 'committed@example.test']);
    expect(app(PublicationService::class)->commit($actor, 'Commit contact state'))->toBeInstanceOf(PublicationCheckpoint::class);

    $page->setAttribute('blocks', [[
        'type' => 'contact',
        'show_form' => false,
        'show_email' => false,
        'social_platforms' => [],
        'form_state' => 'enabled',
    ]]);
    $page->save();
    PublicContentSetting::general()->update(['contact_recipient_email' => 'working@example.test']);

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
        ->andReturnUsing(function (): void {
            expect(DB::transactionLevel())->toBe(0);
        });

    Mail::shouldReceive('to')
        ->once()
        ->with('committed@example.test')
        ->andReturn($pendingMail);

    $this->post('/contact', publicationReviewContactPayload())
        ->assertRedirect()
        ->assertSessionHas('contact_success', 'Your message was received.');

    expect($contactWriteLevel)->toBe(0)
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
