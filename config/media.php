<?php

$quotaBytes = env('MEDIA_STORAGE_QUOTA_BYTES');

return [
    'disk' => env('MEDIA_DISK', 'local'),
    'quota_bytes' => is_string($quotaBytes) && ctype_digit($quotaBytes) && (int) $quotaBytes > 0
        ? (int) $quotaBytes
        : null,
];
