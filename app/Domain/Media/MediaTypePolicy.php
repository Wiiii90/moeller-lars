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

    /** @var list<string> */
    public const AUDIO_MIME_TYPES = [
        'audio/mpeg',
        'audio/mp4',
        'audio/ogg',
        'audio/wav',
    ];

    /** @var array<string, string> */
    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'video/mp4' => 'mp4',
        'video/webm' => 'webm',
        'audio/mpeg' => 'mp3',
        'audio/mp4' => 'm4a',
        'audio/ogg' => 'ogg',
        'audio/wav' => 'wav',
    ];

    /** @return list<string> */
    public static function acceptedMimeTypes(): array
    {
        return array_keys(self::EXTENSIONS);
    }

    /** @return list<string> */
    public static function uploadAcceptedMimeTypes(): array
    {
        return array_values(array_unique([
            ...self::acceptedMimeTypes(),
            'audio/x-m4a',
            'audio/x-wav',
            'audio/vnd.wave',
            'application/ogg',
        ]));
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

    public static function isAudio(string $mimeType): bool
    {
        return in_array($mimeType, self::AUDIO_MIME_TYPES, true);
    }

    public static function kind(string $mimeType): string
    {
        if (self::isImage($mimeType)) {
            return 'image';
        }

        if (self::isVideo($mimeType)) {
            return 'video';
        }

        if (self::isAudio($mimeType)) {
            return 'audio';
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
            'audio/mpeg' => 'MP3 audio',
            'audio/mp4' => 'M4A / AAC audio',
            'audio/ogg' => 'Ogg audio',
            'audio/wav' => 'WAV audio',
            default => $mimeType,
        };
    }

    public static function maxBytesFor(string $mimeType): int
    {
        if (self::isVideo($mimeType)) {
            return self::videoMaxBytes();
        }

        if (self::isAudio($mimeType)) {
            return self::audioMaxBytes();
        }

        return self::imageMaxBytes();
    }

    public static function maxUploadBytes(): int
    {
        return max(self::imageMaxBytes(), self::videoMaxBytes(), self::audioMaxBytes());
    }

    public static function imageMaxBytes(): int
    {
        return max(1, (int) config('media.upload.image_max_bytes', 20 * 1024 * 1024));
    }

    public static function videoMaxBytes(): int
    {
        return max(1, (int) config('media.upload.video_max_bytes', 100 * 1024 * 1024));
    }

    public static function audioMaxBytes(): int
    {
        return max(1, (int) config('media.upload.audio_max_bytes', 100 * 1024 * 1024));
    }
}
