<?php

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\SiteSectionEditorialService;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

function workflowBlogJournal(string $slug): SiteSection
{
    return app(SiteSectionEditorialService::class)->createJournal(ucfirst(str_replace('-', ' ', $slug)), $slug, SiteSection::JOURNAL_TEMPLATE_BLOG);
}

function workflowBlogPost(SiteSection $journal, string $title, int $position, string $state = 'draft'): BlogPost
{
    return BlogPost::create([
        'site_section_id' => $journal->id,
        'title' => $title,
        'slug' => strtolower(str_replace(' ', '-', $title)),
        'body' => 'Editorial body',
        'state' => $state,
        'position' => $position,
        'published_at' => $state === 'published' ? now() : null,
        'scheduled_at' => null,
    ]);
}

it('creates drafts inside the selected Blog Journal and audits the action', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $journal = workflowBlogJournal('workflow-drafts');
    workflowBlogPost($journal, 'Existing post', 7);

    $post = app(BlogEditorialService::class)->createDraft([
        'site_section_id' => $journal->id,
        'title' => 'New draft',
        'slug' => 'new-draft',
        'body' => null,
        'excerpt' => null,
        'cover_media_asset_id' => null,
    ]);

    expect($post->state)->toBe('draft')
        ->and((int) $post->site_section_id)->toBe($journal->id)
        ->and($post->position)->toBe(8)
        ->and(AuditEvent::query()->where('action', 'blog_post.created')->where('entity_id', $post->id)->exists())->toBeTrue();
});

it('enforces the Blog editorial lifecycle without preserving legacy page state', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $journal = workflowBlogJournal('workflow-lifecycle');
    $post = workflowBlogPost($journal, 'Lifecycle post', 0);
    $service = app(BlogEditorialService::class);

    expect($service->schedule($post, now()->addDay())->state)->toBe('scheduled')
        ->and($service->restoreDraft($post)->state)->toBe('draft')
        ->and($service->publish($post)->state)->toBe('published')
        ->and($service->unpublish($post)->state)->toBe('unpublished')
        ->and($service->archive($post)->state)->toBe('archived');

    expect(fn () => $service->publish($post))->toThrow(ValidationException::class);
    expect($service->restoreDraft($post)->state)->toBe('draft');
    expect(fn () => $service->schedule($post, now()->subMinute()))->toThrow(ValidationException::class);
});

it('reorders posts only inside their owning Journal', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $firstJournal = workflowBlogJournal('workflow-order-a');
    $secondJournal = workflowBlogJournal('workflow-order-b');
    $first = workflowBlogPost($firstJournal, 'First public', 0, 'published');
    $second = workflowBlogPost($firstJournal, 'Second public', 1, 'published');
    $other = workflowBlogPost($secondJournal, 'Other journal', 0, 'published');

    expect(app(BlogEditorialService::class)->move($second, 'up'))->toBeTrue()
        ->and(BlogPost::query()->where('site_section_id', $firstJournal->id)->orderBy('position')->pluck('id')->all())->toBe([$second->id, $first->id])
        ->and((int) $other->fresh()->position)->toBe(0)
        ->and((int) $other->fresh()->site_section_id)->toBe($secondJournal->id);
});

it('requires an admin actor for Blog lifecycle mutations', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    $journal = workflowBlogJournal('workflow-auth');
    $post = workflowBlogPost($journal, 'Protected post', 0);
    auth()->logout();

    expect(fn () => app(BlogEditorialService::class)->publish($post))->toThrow(AuthorizationException::class);
});
