<?php

use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

it('deletes an empty Journal blocks Journals with entries and allows recreation', function (): void {
    $sections = app(SiteSectionEditorialService::class);
    $empty = $sections->createJournal('Disposable Blog', 'disposable-blog', JournalTemplate::Blog->value);
    $sections->updatePlacement($empty, 'published', true, null);

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
