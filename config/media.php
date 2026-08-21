<?php

return [
    'disk' => env('MEDIA_DISK', 'local'),

    // Runtime/operator contract. Blank means unconfigured; a non-empty invalid
    // value is handled fail-closed by MediaCapacityService.
    'quota_bytes' => env('MEDIA_STORAGE_QUOTA_BYTES'),
];
