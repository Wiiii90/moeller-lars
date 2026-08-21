<?php

namespace App\Domain\Content;

use App\Models\SiteSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;

final class SitePreviewContext
{
    public const REQUEST_ATTRIBUTE = 'artist_site_preview';

    public function active(): bool
    {
        return request()->attributes->getBoolean(self::REQUEST_ATTRIBUTE);
    }

    public function activate(Request $request): void
    {
        $request->attributes->set(self::REQUEST_ATTRIBUTE, true);
    }

    public function sectionIsAvailable(string $type): bool
    {
        /** @var Builder<SiteSection> $query */
        $query = SiteSection::query()->where('type', $type);
        if (! $this->active()) {
            $query->where('state', 'published');
        }

        return $query->exists();
    }

    public function constrainSectionQuery(Builder $query): Builder
    {
        if (! $this->active()) {
            $query->where('state', 'published');
        }

        return $query;
    }

    public function url(string $publicUrl): string
    {
        if (! $this->active()) {
            return $publicUrl;
        }

        return $this->previewUrlFromPublicUrl($publicUrl);
    }

    public function previewUrlFor(SiteSection $section): ?string
    {
        $publicUrl = $section->publicUrl();

        return $publicUrl === null ? null : $this->previewUrlFromPublicUrl($publicUrl);
    }

    public function previewSiteUrl(): string
    {
        return url('/preview');
    }

    public function homeUrl(): string
    {
        return $this->active() ? $this->previewSiteUrl() : route('home');
    }

    private function previewUrlFromPublicUrl(string $publicUrl): string
    {
        $path = parse_url($publicUrl, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : '/';
        if ($path === '/preview' || str_starts_with($path, '/preview/')) {
            return $publicUrl;
        }

        $previewPath = '/preview'.($path === '/' ? '' : $path);
        $query = parse_url($publicUrl, PHP_URL_QUERY);

        return url($previewPath).(is_string($query) && $query !== '' ? '?'.$query : '');
    }
}
