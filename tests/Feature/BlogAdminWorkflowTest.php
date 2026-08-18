<?php

use App\Domain\Blog\BlogEditorialService;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function blogWorkflowPost(string $title, int $position, string $state = 'draft'): BlogPost
{
    return BlogPost::create([
        'title' => $title,
        'slug' => strtolower(str_replace(' ', '-', $title)),
        'body' => 'Editorial body',
        'state' => $state,
        'position' => $position,
        'published_at' => $state === 'published' ? now() : null,
        'scheduled_at' => null,
    ]);
}

it('creates private drafts and assigns listing order without exposing raw state controls', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    blogWorkflowPost('Existing post', 7);

    $post = app(BlogEditorialService::class)->createDraft([
        'title' => 'New draft',
        'slug' => 'new-draft',
        'body' => null,
        'excerpt' => null,
        'cover_media_asset_id' => null,
    ]);

    expect($post->state)->toBe('draft')
        ->and($post->position)->toBe(8)
        ->and($post->published_at)->toBeNull()
        ->and($post->scheduled_at)->toBeNull()
        ->and(AuditEvent::query()->where('action', 'blog_post.created')->where('entity_id', $post->id)->exists())->toBeTrue();
});

it('publishes, schedules, cancels, unpublishes and archives with explicit audit events', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $post = blogWorkflowPost('Lifecycle post', 0);
    $service = app(BlogEditorialService::class);

    expect($service->schedule($post, now()->addDay())->state)->toBe('scheduled')
        ->and($service->restoreDraft($post)->state)->toBe('draft')
        ->and($service->publish($post)->state)->toBe('published')
        ->and($post->fresh()->published_at)->not->toBeNull()
        ->and($service->unpublish($post)->state)->toBe('unpublished')
        ->and($service->archive($post)->state)->toBe('archived')
        ->and($service->restoreDraft($post)->state)->toBe('draft');

    expect(AuditEvent::query()->where('entity_type', 'blog_post')->pluck('action')->all())
        ->toContain(
            'blog_post.scheduled',
            'blog_post.restored_to_draft',
            'blog_post.published',
            'blog_post.unpublished',
            'blog_post.archived',
        );
});

it('rejects scheduling in the past', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $post = blogWorkflowPost('Past schedule', 0);

    expect(fn () => app(BlogEditorialService::class)->schedule($post, now()->subMinute()))
        ->toThrow(ValidationException::class);
});

it('requires archived posts to return to draft before publishing or scheduling', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $post = blogWorkflowPost('Archived post', 0, 'archived');
    $service = app(BlogEditorialService::class);

    expect(fn () => $service->publish($post))->toThrow(ValidationException::class)
        ->and(fn () => $service->schedule($post, now()->addDay()))->toThrow(ValidationException::class)
        ->and($post->fresh()->state)->toBe('archived')
        ->and(AuditEvent::query()->whereIn('action', ['blog_post.published', 'blog_post.scheduled'])->count())->toBe(0);
});

it('does not schedule an already published post directly', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $post = blogWorkflowPost('Published post', 0, 'published');

    expect(fn () => app(BlogEditorialService::class)->schedule($post, now()->addDay()))
        ->toThrow(ValidationException::class)
        ->and($post->fresh()->state)->toBe('published');
});

it('reorders public posts without violating the partial unique position index', function () {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $first = blogWorkflowPost('First public', 0, 'published');
    $second = blogWorkflowPost('Second public', 1, 'published');
    $third = blogWorkflowPost('Third public', 2, 'published');

    expect(app(BlogEditorialService::class)->move($third, 'up'))->toBeTrue()
        ->and(BlogPost::query()->orderBy('position')->pluck('id')->all())
        ->toBe([$first->id, $third->id, $second->id])
        ->and(BlogPost::query()->orderBy('position')->pluck('position')->all())
        ->toBe([0, 1, 2])
        ->and(AuditEvent::query()->where('action', 'blog_post.reordered')->count())->toBe(2);
});

it('requires an admin actor for blog lifecycle mutations', function () {
    $post = blogWorkflowPost('Protected blog post', 0);
    $service = app(BlogEditorialService::class);

    expect(fn () => $service->publish($post))->toThrow(AuthorizationException::class);

    $this->actingAs(User::factory()->create(), 'web');
    expect(fn () => $service->archive($post))->toThrow(AuthorizationException::class)
        ->and(fn () => $service->move($post, 'up'))->toThrow(AuthorizationException::class)
        ->and($post->fresh()->state)->toBe('draft');
});
