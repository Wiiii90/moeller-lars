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

    // Only the platform's dedicated offline Validation maintenance container sets
    // this flag. It is never enabled on the normal web or migration services.
    'validation_offline_cutover' => env('MOELLER_LARS_VALIDATION_OFFLINE_CUTOVER', false),

    // Required by the *offline* IPTC/XMP staging writer, never public requests.
    // The executable must be ExifTool >=12.67; Debian Bookworm's 12.57 is too old.
    'exiftool_binary' => env('MEDIA_EXIFTOOL_BINARY', 'exiftool'),

    // New publication/rights-artifact table lookups are disabled by default.
    // Enable only after verified migration, offline preparation and delivery.
    // Two-stage rollout: prepare Working representations offline, then
    // independently authorize public delivery after full acceptance.
    'rights_preparation_enabled' => env('MEDIA_RIGHTS_PREPARATION_ENABLED', false),
    'rights_delivery_enabled' => env('MEDIA_RIGHTS_DELIVERY_ENABLED', false),

    // Runtime/operator contract. Blank means unconfigured; a non-empty invalid
    // value is handled fail-closed by MediaCapacityService.
    'quota_bytes' => env('MEDIA_STORAGE_QUOTA_BYTES'),
    'upload' => [
        // Byte limits are operator-configurable. Quota admission remains a separate authoritative check.
        'image_max_bytes' => $positiveBytes(env('MEDIA_IMAGE_MAX_BYTES'), 64 * 1024 * 1024),
        'video_max_bytes' => $positiveBytes(env('MEDIA_VIDEO_MAX_BYTES'), 512 * 1024 * 1024),
        'audio_max_bytes' => $positiveBytes(env('MEDIA_AUDIO_MAX_BYTES'), 100 * 1024 * 1024),
    ],
];
