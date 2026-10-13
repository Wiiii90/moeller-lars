<?php

use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

it('defaults older Blog galleries to their original image sequence and saves an authored presentation', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $section = app(SiteSectionEditorialService::class)
        ->createJournal('Journal for gallery layouts', 'gallery-layout-posts', JournalTemplate::Blog->value);

    $editor = app(BlogEditorialService::class);
    $post = $editor->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Painting studies',
        'slug' => 'painting-studies',
        'body' => 'Original article content',
    ]);

    expect($post->fresh()->galleryPresentation())->toBe('sequence');

    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'mosaic',
    ]);

    expect(BlogPost::query()->findOrFail($post->getKey())->galleryPresentation())->toBe('mosaic');

    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'slideshow',
    ]);

    expect($post->fresh()->galleryPresentation())->toBe('slideshow');

    expect(fn () => $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'unknown',
    ]))->toThrow(ValidationException::class);
});
