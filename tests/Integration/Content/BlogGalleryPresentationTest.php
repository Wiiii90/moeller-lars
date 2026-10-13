<?php

use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationVersionService;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

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
        'gallery_presentation' => 'grid',
    ]);
    expect($post->fresh()->galleryPresentation())->toBe('grid');

    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'slideshow',
    ]);

    expect($post->fresh()->galleryPresentation())->toBe('slideshow');

    expect(fn () => $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'unknown',
    ]))->toThrow(ValidationException::class);

    // Invalid edits leave the last authored presentation intact.
    expect($post->fresh()->galleryPresentation())->toBe('slideshow');
});

it('keeps an authored Blog gallery presentation in Working until Commit and restores it when changes are discarded', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');
    $section = app(SiteSectionEditorialService::class)
        ->createJournal('Publication gallery', 'publication-gallery', JournalTemplate::Blog->value);

    $editor = app(BlogEditorialService::class);
    $post = $editor->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Publication image sequence',
        'slug' => 'publication-image-sequence',
        'body' => 'Gallery presentation needs an explicit publication.',
    ]);
    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'grid',
    ]);

    $publication = app(PublicationService::class);
    expect($publication->commit($actor))->not->toBeNull();

    $committedPresentation = static fn (): ?string => DB::table('committed.blog_posts')
        ->where('id', $post->getKey())->value('gallery_presentation');

    expect($committedPresentation())->toBe('grid')
        ->and($publication->hasPendingChanges())->toBeFalse();

    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'slideshow',
    ]);

    expect($post->fresh()->galleryPresentation())->toBe('slideshow')
        ->and($committedPresentation())->toBe('grid')
        ->and($publication->hasPendingChanges())->toBeTrue();

    app(PublicationVersionService::class)->resetStagedChanges($actor);

    expect($post->fresh()->galleryPresentation())->toBe('grid')
        ->and($committedPresentation())->toBe('grid')
        ->and($publication->hasPendingChanges())->toBeFalse();

    $editor->update($post, [
        ...$post->fresh()->attributesToArray(),
        'gallery_presentation' => 'mosaic',
    ]);
    expect($publication->commit($actor))->not->toBeNull();

    expect($committedPresentation())->toBe('mosaic')
        ->and($publication->hasPendingChanges())->toBeFalse();
});
