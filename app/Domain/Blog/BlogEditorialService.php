<?php

namespace App\Domain\Blog;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BlogEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SafeRichTextRenderer $richText,
        private readonly JournalEntryOrderService $order,
    ) {}

    public function create(array $data): BlogPost
    {
        $actor = $this->audit->requireActor();
        $validated = $this->validate($data);

        return DB::transaction(fn (): BlogPost => $this->createValidated($validated, $actor));
    }

    public function createDraft(array $data): BlogPost
    {
        $actor = $this->audit->requireActor();
        $sectionId = $this->journalSectionId($data['site_section_id'] ?? null);

        return DB::transaction(function () use ($data, $sectionId, $actor): BlogPost {
            $data['site_section_id'] = $sectionId;
            $data['state'] = 'draft';
            $data['position'] = $this->order->nextPosition(new BlogPost, $sectionId);
            $data['published_at'] = null;
            $data['scheduled_at'] = null;

            return $this->createValidated($this->validate($data), $actor);
        });
    }

    public function update(BlogPost $post, array $data): BlogPost
    {
        $actor = $this->audit->requireActor();
        $data['site_section_id'] = $post->getAttribute('site_section_id');
        $validated = $this->validate($data, (int) $post->getKey());

        return DB::transaction(function () use ($post, $validated, $actor): BlogPost {
            /** @var BlogPost $fresh */
            $fresh = BlogPost::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();

            if (($fresh->getAttribute('published_at') !== null || $fresh->getAttribute('scheduled_at') !== null)
                && $validated['slug'] !== $fresh->getAttribute('slug')) {
                throw ValidationException::withMessages(['slug' => 'A post slug cannot change after publication or scheduling.']);
            }

            if ((int) $validated['site_section_id'] !== (int) $fresh->getAttribute('site_section_id')) {
                throw ValidationException::withMessages(['site_section_id' => 'Move posts between Journals through an explicit editorial workflow.']);
            }

            $fresh->fill($validated);
            $this->prepareLifecycle($fresh);
            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'blog_post.updated', 'blog_post', $fresh->getKey());
            }

            return $fresh;
        });
    }

    public function publish(BlogPost $post): BlogPost
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($post, $actor): BlogPost {
            $fresh = $this->locked($post);
            $state = (string) $fresh->getAttribute('state');

            if ($state === 'published') {
                return $fresh;
            }
            if (! in_array($state, ['draft', 'scheduled', 'unpublished'], true)) {
                throw ValidationException::withMessages(['state' => 'Restore this post to draft before publishing it again.']);
            }

            $fresh->setAttribute('state', 'published');
            $fresh->setAttribute('scheduled_at', null);
            $this->prepareLifecycle($fresh);
            $fresh->save();
            $this->audit->record($actor, 'blog_post.published', 'blog_post', $fresh->getKey());

            return $fresh->fresh();
        });
    }

    public function schedule(BlogPost $post, mixed $scheduledAt): BlogPost
    {
        $actor = $this->audit->requireActor();
        $date = $this->dateTime($scheduledAt, 'scheduled_at');
        if (! $date instanceof CarbonImmutable || ! $date->isFuture()) {
            throw ValidationException::withMessages(['scheduled_at' => 'Scheduled publication must be in the future.']);
        }

        return DB::transaction(function () use ($post, $date, $actor): BlogPost {
            $fresh = $this->locked($post);
            $state = (string) $fresh->getAttribute('state');
            if (! in_array($state, ['draft', 'scheduled', 'unpublished'], true)) {
                throw ValidationException::withMessages(['state' => 'Only draft, scheduled or unpublished posts can be scheduled.']);
            }

            $fresh->setAttribute('state', 'scheduled');
            $fresh->setAttribute('scheduled_at', $date);
            $this->prepareLifecycle($fresh);
            $fresh->save();
            $this->audit->record($actor, 'blog_post.scheduled', 'blog_post', $fresh->getKey());

            return $fresh->fresh();
        });
    }

    public function unpublish(BlogPost $post): BlogPost
    {
        return $this->transition($post, 'unpublished', 'unpublished', onlyFrom: 'published');
    }

    public function archive(BlogPost $post): BlogPost
    {
        return $this->transition($post, 'archived', 'archived');
    }

    public function restoreDraft(BlogPost $post): BlogPost
    {
        return $this->transition($post, 'draft', 'restored_to_draft', onlyFrom: ['scheduled', 'unpublished', 'archived']);
    }

    public function canMove(BlogPost $post, string $direction): bool
    {
        return $this->order->canMove($post, $direction);
    }

    public function move(BlogPost $post, string $direction): bool
    {
        return $this->order->move($post, $direction);
    }

    /** @return Builder<BlogPost> */
    public static function publicQuery(): Builder
    {
        $now = now();

        return BlogPost::query()->where(function (Builder $visibility) use ($now): void {
            $visibility->where(function (Builder $published) use ($now): void {
                $published->where('state', 'published')->where('published_at', '<=', $now);
            })->orWhere(function (Builder $scheduled) use ($now): void {
                $scheduled->where('state', 'scheduled')->where('scheduled_at', '<=', $now);
            });
        });
    }

    /** @param array<string, mixed> $validated */
    private function createValidated(array $validated, User $actor): BlogPost
    {
        $post = new BlogPost;
        $post->fill([
            ...$validated,
            'legacy_id' => null,
            'legacy_source' => null,
            'migration_batch_id' => null,
            'migrated_at' => null,
        ]);
        $this->prepareLifecycle($post);
        $post->save();
        $this->audit->record($actor, 'blog_post.created', 'blog_post', $post->getKey(), [
            'site_section_id' => (int) $post->getAttribute('site_section_id'),
        ]);

        return $post;
    }

    private function transition(BlogPost $post, string $state, string $action, string|array|null $onlyFrom = null): BlogPost
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($post, $state, $action, $onlyFrom, $actor): BlogPost {
            $fresh = $this->locked($post);
            $current = (string) $fresh->getAttribute('state');
            $allowed = $onlyFrom === null ? null : (array) $onlyFrom;

            if ($current === $state || ($allowed !== null && ! in_array($current, $allowed, true))) {
                return $fresh;
            }

            $fresh->setAttribute('state', $state);
            if ($state !== 'scheduled') {
                $fresh->setAttribute('scheduled_at', null);
            }
            $fresh->save();
            $this->audit->record($actor, 'blog_post.'.$action, 'blog_post', $fresh->getKey());

            return $fresh->fresh();
        });
    }

    private function locked(BlogPost $post): BlogPost
    {
        /** @var BlogPost $fresh */
        $fresh = BlogPost::query()->whereKey($post->getKey())->lockForUpdate()->firstOrFail();

        return $fresh;
    }

    private function prepareLifecycle(BlogPost $post): void
    {
        $this->journalSectionId($post->getAttribute('site_section_id'));
        $state = (string) $post->getAttribute('state');
        $body = $post->getAttribute('body');

        if (in_array($state, ['published', 'scheduled'], true)) {
            if (! is_string($body) || trim($body) === '') {
                throw ValidationException::withMessages(['body' => 'Published or scheduled posts require body content.']);
            }
            $this->richText->assertValid($body);
            $this->assertCoverReady($post);
        }

        if ($state === 'published' && $post->getAttribute('published_at') === null) {
            $post->setAttribute('published_at', now());
        }

        if ($state === 'scheduled' && ! $post->getAttribute('scheduled_at') instanceof CarbonInterface) {
            throw ValidationException::withMessages(['scheduled_at' => 'Scheduled posts require a publication time.']);
        }
    }

    private function assertCoverReady(BlogPost $post): void
    {
        $coverId = $post->getAttribute('cover_media_asset_id');
        if ($coverId === null) {
            return;
        }

        $asset = MediaAsset::query()->find($coverId);
        if (! $asset instanceof MediaAsset || $asset->getAttribute('state') !== 'available') {
            throw ValidationException::withMessages(['cover_media_asset_id' => 'The cover media asset is unavailable.']);
        }

        $alt = $asset->getAttribute('alt_text');
        if (! is_string($alt) || trim($alt) === '') {
            throw ValidationException::withMessages(['cover_media_asset_id' => 'Public cover media requires canonical ALT text.']);
        }

        $variants = $asset->variants()
            ->where('variant_kind', PublicMedia::THUMBNAIL_KIND)
            ->where('transform_profile', PublicMedia::PUBLIC_TRANSFORM_PROFILE)
            ->where('state', 'available')
            ->count();
        if ($variants !== 1) {
            throw ValidationException::withMessages(['cover_media_asset_id' => 'Public cover media requires exactly one public thumbnail.']);
        }
    }

    /** @return array<string, mixed> */
    private function validate(array $data, ?int $ignoreId = null): array
    {
        foreach (['site_section_id', 'title', 'slug', 'state', 'position'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw ValidationException::withMessages([$required => 'Required Blog Journal form data is missing.']);
            }
        }

        $sectionId = $this->journalSectionId($data['site_section_id']);
        $title = is_string($data['title']) ? trim($data['title']) : '';
        if ($title === '' || mb_strlen($title) > 240) {
            throw ValidationException::withMessages(['title' => 'The blog title is invalid.']);
        }

        $slug = is_string($data['slug']) ? trim($data['slug']) : '';
        if ($slug === '' || mb_strlen($slug) > 220 || ! preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
            throw ValidationException::withMessages(['slug' => 'The blog slug is invalid.']);
        }
        $slugQuery = BlogPost::query()->where('slug', $slug);
        if ($ignoreId !== null) {
            $slugQuery->whereKeyNot($ignoreId);
        }
        if ($slugQuery->exists()) {
            throw ValidationException::withMessages(['slug' => 'The blog slug is already in use.']);
        }

        $state = $data['state'];
        if (! is_string($state) || ! in_array($state, ['draft', 'scheduled', 'published', 'unpublished', 'archived'], true)) {
            throw ValidationException::withMessages(['state' => 'The blog state is invalid.']);
        }

        $position = filter_var($data['position'], FILTER_VALIDATE_INT);
        if ($position === false || $position < 0) {
            throw ValidationException::withMessages(['position' => 'The blog position is invalid.']);
        }

        $body = $data['body'] ?? null;
        if ($body !== null && ! is_string($body)) {
            throw ValidationException::withMessages(['body' => 'The blog body must be text.']);
        }
        if (is_string($body)) {
            $this->richText->assertValid($body);
        }

        $excerpt = $data['excerpt'] ?? null;
        if ($excerpt !== null && (! is_string($excerpt) || mb_strlen($excerpt) > 1000)) {
            throw ValidationException::withMessages(['excerpt' => 'The blog excerpt is invalid.']);
        }

        $coverId = $data['cover_media_asset_id'] ?? null;
        if ($coverId !== null && filter_var($coverId, FILTER_VALIDATE_INT) === false) {
            throw ValidationException::withMessages(['cover_media_asset_id' => 'The cover media asset is invalid.']);
        }

        return [
            'site_section_id' => $sectionId,
            'title' => $title,
            'slug' => $slug,
            'body' => $body,
            'state' => $state,
            'position' => (int) $position,
            'excerpt' => $excerpt,
            'cover_media_asset_id' => $coverId === null ? null : (int) $coverId,
            'published_at' => $this->dateTime($data['published_at'] ?? null, 'published_at'),
            'scheduled_at' => $this->dateTime($data['scheduled_at'] ?? null, 'scheduled_at'),
        ];
    }

    private function journalSectionId(mixed $value): int
    {
        $sectionId = filter_var($value, FILTER_VALIDATE_INT);
        if ($sectionId === false || $sectionId <= 0) {
            throw ValidationException::withMessages(['site_section_id' => 'Choose a Blog Journal page.']);
        }

        $exists = SiteSection::query()
            ->whereKey($sectionId)
            ->where('type', SiteSection::TYPE_JOURNAL)
            ->where('template', SiteSection::JOURNAL_TEMPLATE_BLOG)
            ->exists();
        if (! $exists) {
            throw ValidationException::withMessages(['site_section_id' => 'The selected page is not a Blog Journal.']);
        }

        return (int) $sectionId;
    }

    private function dateTime(mixed $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value);
        }
        if (! is_string($value)) {
            throw ValidationException::withMessages([$field => 'The publication time is invalid.']);
        }

        try {
            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            throw ValidationException::withMessages([$field => 'The publication time is invalid.']);
        }
    }
}
