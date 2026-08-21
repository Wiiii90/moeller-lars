<?php

namespace App\Domain\Content;

final class SocialLinks
{
    /** @var array<string, string> */
    private const PLATFORMS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'youtube' => 'YouTube',
        'bluesky' => 'Bluesky',
        'mastodon' => 'Mastodon',
    ];

    /** @return array<string, string> */
    public static function options(): array
    {
        return self::PLATFORMS;
    }

    public static function supports(string $platform): bool
    {
        return array_key_exists($platform, self::PLATFORMS);
    }

    public static function label(string $platform): string
    {
        return self::PLATFORMS[$platform] ?? ucfirst($platform);
    }

    /**
     * @param array<int, mixed>|null $links
     * @return list<array{platform:string,url:string,visible:bool}>
     */
    public static function visible(?array $links): array
    {
        $visible = [];

        foreach ($links ?? [] as $link) {
            if (! is_array($link)) {
                continue;
            }

            $platform = $link['platform'] ?? null;
            $url = $link['url'] ?? null;
            if (! is_string($platform) || ! self::supports($platform) || ! is_string($url) || ($link['visible'] ?? true) !== true) {
                continue;
            }

            $visible[] = [
                'platform' => $platform,
                'url' => $url,
                'visible' => true,
            ];
        }

        return $visible;
    }

    public static function displayValue(string $url): string
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');
        if ($path !== '') {
            return $path;
        }

        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : $url;
    }
}
