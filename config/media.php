<?php

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
];
