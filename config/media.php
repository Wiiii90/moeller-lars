<?php

$positiveBytes = static function (mixed $value, int $default): int {
    if (is_int($value) && $value > 0) {
        return $value;
    }

    if (is_string($value) && ctype_digit($value) && (int) $value > 0) {
        return (int) $value;
    }

    return $default;
};

return [
    'disk' => env('MEDIA_DISK', 'local'),

    // Runtime/operator contract. Blank means unconfigured; a non-empty invalid
    // value is handled fail-closed by MediaCapacityService.
    'quota_bytes' => env('MEDIA_STORAGE_QUOTA_BYTES'),
    'upload' => [
        // Byte limits are operator-configurable. Quota admission remains a separate authoritative check.
        'image_max_bytes' => $positiveBytes(env('MEDIA_IMAGE_MAX_BYTES'), 20 * 1024 * 1024),
        'video_max_bytes' => $positiveBytes(env('MEDIA_VIDEO_MAX_BYTES'), 100 * 1024 * 1024),
    ],
];
