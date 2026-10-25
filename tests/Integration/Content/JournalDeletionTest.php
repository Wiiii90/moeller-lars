<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\AuditEvent;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('deletes an empty Journal blocks Journals with entries and allows recreation', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $empty = $sections->createJournal('Disposable Blog', 'disposable-blog', JournalTemplate::Blog->value);
    $sections->updatePlacement($empty, 'published', null);

    $sections->deleteConfigurableSection($empty);

    expect(SiteSection::query()->whereKey($empty->getKey())->exists())->toBeFalse();

    $replacement = $sections->createJournal('Blog', 'blog-recreated', JournalTemplate::Blog->value);
    expect($replacement->getAttribute('template'))->toBe(JournalTemplate::Blog->value);

    BlogPost::query()->create([
        'site_section_id' => $replacement->getKey(),
        'title' => 'Protected entry',
        'slug' => 'protected-entry',
        'body' => 'Body',
        'state' => 'draft',
        'position' => 0,
    ]);

    expect(fn () => $sections->deleteConfigurableSection($replacement))
        ->toThrow(ValidationException::class, 'This Journal cannot be deleted while it still contains entries.');
});


it('preserves an Exhibitions Journal and its entries when deletion is forbidden', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $journal = $sections->createJournal('Protected Exhibitions', 'protected-exhibitions', JournalTemplate::Exhibitions->value);
    $entry = Exhibition::query()->create([
        'site_section_id' => $journal->getKey(),
        'slug' => 'protected-exhibition',
        'title' => 'Exhibition still in use',
        'state' => 'draft',
        'position' => 0,
    ]);
    $auditsBefore = AuditEvent::query()->count();

    expect(fn () => $sections->deleteConfigurableSection($journal))
        ->toThrow(ValidationException::class, 'This Journal cannot be deleted while it still contains entries.');

    expect(SiteSection::query()->whereKey($journal->getKey())->exists())->toBeTrue()
        ->and(Exhibition::query()->whereKey($entry->getKey())->exists())->toBeTrue()
        ->and(AuditEvent::query()->count())->toBe($auditsBefore);
});
