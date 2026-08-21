<?php

namespace App\Domain\Media;

final class MediaTypePolicy
{
    /** @var list<string> */
    public const IMAGE_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    /** @var list<string> */
    public const VIDEO_MIME_TYPES = [
        'video/mp4',
        'video/webm',
    ];

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
    ];

    /** @return list<string> */
    public static function acceptedMimeTypes(): array
    {
        return array_keys(self::EXTENSIONS);
    }

    public static function extensionFor(string $mimeType): ?string
    {
        return self::EXTENSIONS[$mimeType] ?? null;
    }

    public static function isImage(string $mimeType): bool
    {
        return in_array($mimeType, self::IMAGE_MIME_TYPES, true);
    }

    public static function isVideo(string $mimeType): bool
    {
        return in_array($mimeType, self::VIDEO_MIME_TYPES, true);
    }

    public static function kind(string $mimeType): string
    {
        if (self::isImage($mimeType)) {
            return 'image';
        }

        if (self::isVideo($mimeType)) {
            return 'video';
        }

        return 'other';
    }

    public static function label(string $mimeType): string
    {
        return match ($mimeType) {
            'image/jpeg' => 'JPEG image',
            'image/png' => 'PNG image',
            'image/webp' => 'WebP image',
            'video/mp4' => 'MP4 video',
            'video/webm' => 'WebM video',
            default => $mimeType,
        };
    }

    public static function maxBytesFor(string $mimeType): int
    {
        return self::isVideo($mimeType) ? self::videoMaxBytes() : self::imageMaxBytes();
    }

    public static function maxUploadBytes(): int
    {
        return max(self::imageMaxBytes(), self::videoMaxBytes());
    }

    public static function imageMaxBytes(): int
    {
        return max(1, (int) config('media.upload.image_max_bytes', 20 * 1024 * 1024));
    }

    public static function videoMaxBytes(): int
    {
        return max(1, (int) config('media.upload.video_max_bytes', 100 * 1024 * 1024));
    }
}
