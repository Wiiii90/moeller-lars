<?php

namespace App\Domain\Blog;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Domain\Media\PublicMedia;
use App\Models\BlogPost;
use App\Models\MediaAsset;
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
    ) {}

    public function create(array $data): BlogPost
    {
        $actor = $this->audit->requireActor();
        $validated = $this->validate($data);

        return DB::transaction(function () use ($validated, $actor): BlogPost {
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
            $this->audit->record($actor, 'blog_post.created', 'blog_post', $post->getKey());

            return $post;
        });
    }

    public function update(BlogPost $post, array $data): BlogPost
    {
        $actor = $this->audit->requireActor();
        $validated = $this->validate($data, (int) $post->getKey());

        return DB::transaction(function () use ($post, $validated, $actor): BlogPost {
            DB::table('blog_posts')->where('id', $post->getKey())->lockForUpdate()->first();
            /** @var BlogPost $fresh */
            $fresh = BlogPost::query()->findOrFail($post->getKey());

            if (($fresh->getAttribute('published_at') !== null || $fresh->getAttribute('scheduled_at') !== null)
                && $validated['slug'] !== $fresh->getAttribute('slug')) {
                throw ValidationException::withMessages(['slug' => 'A post slug cannot change after publication.']);
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

    private function prepareLifecycle(BlogPost $post): void
    {
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

        if ($state === 'scheduled') {
            $scheduledAt = $post->getAttribute('scheduled_at');
            if (! $scheduledAt instanceof CarbonInterface) {
                throw ValidationException::withMessages(['scheduled_at' => 'Scheduled posts require a publication time.']);
            }
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
        foreach (['title', 'slug', 'state', 'position'] as $required) {
            if (! array_key_exists($required, $data)) {
                throw ValidationException::withMessages([$required => 'Required blog form data is missing.']);
            }
        }

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
