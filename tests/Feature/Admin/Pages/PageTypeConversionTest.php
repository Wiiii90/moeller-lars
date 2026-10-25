<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionType;
use App\Filament\Pages\SitePages;
use App\Models\ArtworkCategory;
use App\Models\AuditEvent;
use App\Models\AdminNotification;
use App\Models\BlogPost;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

require_once __DIR__.'/PagesFixtures.php';

it('does not report success for no-op page type or Journal template commands', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('No-op Type Page', 'noop-type-page');
    $journal = $service->createJournal('No-op Template Journal', 'noop-template-journal', JournalTemplate::Blog->value);
    $eventsBefore = AuditEvent::query()->count();
    $notificationsBefore = AdminNotification::query()->count();

    Livewire::test(SitePages::class)
        ->call('convertSectionType', (int) $page->getKey(), SiteSectionType::CustomPage->value)
        ->call('changeJournalTemplate', (int) $journal->getKey(), JournalTemplate::Blog->value)
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($eventsBefore)
        ->and(AdminNotification::query()->count())->toBe($notificationsBefore)
        ->and($page->fresh()->type)->toBe(SiteSectionType::CustomPage->value)
        ->and($journal->fresh()->template)->toBe(JournalTemplate::Blog->value);
});


it('initializes target configuration for safe type conversions transactionally', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('Convertible Page', 'convertible-page-repair');

    $gallery = $service->convertType($page, SiteSectionType::Gallery->value);
    $galleryId = (int) $gallery->artwork_category_id;
    expect($gallery->type)->toBe(SiteSectionType::Gallery->value)
        ->and($galleryId)->toBeGreaterThan(0)
        ->and(ArtworkCategory::query()->whereKey($galleryId)->exists())->toBeTrue()
        ->and($gallery->customPageSetting()->exists())->toBeFalse();

    $journal = $service->convertType($gallery, SiteSectionType::Journal->value);
    expect($journal->type)->toBe(SiteSectionType::Journal->value)
        ->and($journal->template)->toBe(JournalTemplate::Blog->value)
        ->and($journal->journalSetting()->exists())->toBeTrue()
        ->and(ArtworkCategory::query()->whereKey($galleryId)->exists())->toBeFalse();

    $group = $service->convertType($journal, SiteSectionType::NavigationNode->value);
    expect($group->type)->toBe(SiteSectionType::NavigationNode->value)
        ->and($group->slug)->toBeNull()
        ->and($group->journalSetting()->exists())->toBeFalse();

    $custom = $service->convertType($group, SiteSectionType::CustomPage->value);
    expect($custom->type)->toBe(SiteSectionType::CustomPage->value)
        ->and($custom->slug)->not->toBeNull()
        ->and($custom->customPageSetting()->exists())->toBeTrue();
});

it('rolls back type and configuration changes when conversion auditing fails', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $section = app(SiteSectionEditorialService::class)->createCustomPage('Atomic conversion', 'atomic-conversion');
    $before = AuditEvent::query()->count();
    $audit = Mockery::mock(app(\App\Domain\Admin\AdminAuditService::class));
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(SiteSectionEditorialService::class);
    expect(fn () => app(SiteSectionEditorialService::class)->convertType($section, SiteSectionType::Journal->value))
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect($section->fresh()->type)->toBe(SiteSectionType::CustomPage->value)
        ->and($section->customPageSetting()->exists())->toBeTrue()->and($section->journalSetting()->exists())->toBeFalse()
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});


it('blocks destructive type conversion when custom page content exists', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);
    $page = $service->createCustomPage('Content Page', 'content-page-repair');
    $settings = $page->customPageSetting()->firstOrFail();
    $settings->setAttribute('blocks', [[
        'type' => 'divider',
        'published' => true,
        'variant' => 'thin',
    ]]);
    $settings->save();

    expect(fn () => $service->convertType($page, SiteSectionType::NavigationNode->value))
        ->toThrow(ValidationException::class, 'contains components');
    expect($page->refresh()->type)->toBe(SiteSectionType::CustomPage->value)
        ->and($page->customPageSetting()->exists())->toBeTrue();
});


it('retains Journal entries across template switches and rejects destructive type conversion', function (): void {
    $this->actingAs(pagesTestAdmin(), 'web');
    $service = app(SiteSectionEditorialService::class);

    $safe = $service->createJournal('Safe Journal', 'safe-journal-repair', JournalTemplate::Blog->value);
    $service->updateJournalTemplate($safe, JournalTemplate::Exhibitions->value);
    expect($safe->refresh()->template)->toBe(JournalTemplate::Exhibitions->value);

    $journal = $service->createJournal('Journal With Entry', 'journal-with-entry-repair', JournalTemplate::Blog->value);
    $post = BlogPost::query()->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'journal-entry-repair',
        'title' => 'Existing entry',
        'body' => null,
        'state' => 'draft',
        'position' => 10,
    ]);

    $postBefore = $post->fresh()->attributesToArray();
    $service->updateJournalTemplate($journal, JournalTemplate::Exhibitions->value);
    expect($journal->fresh()->template)->toBe(JournalTemplate::Exhibitions->value)
        ->and($post->fresh()->attributesToArray())->toBe($postBefore);
    $service->updateJournalTemplate($journal->fresh(), JournalTemplate::Blog->value);
    expect(fn () => $service->convertType($journal->fresh(), SiteSectionType::NavigationNode->value))
        ->toThrow(ValidationException::class, 'contains entries');

    expect($journal->refresh()->template)->toBe(JournalTemplate::Blog->value)
        ->and($journal->type)->toBe(SiteSectionType::Journal->value)
        ->and($post->fresh()->attributesToArray())->toBe($postBefore);
});
