<?php

return [
    // Temporary staging must accept the same 512 MiB files as the media domain.
    // Livewire's default 12 MiB limit otherwise rejects them first.
    'temporary_file_upload' => [
        'rules' => ['required', 'file', 'max:524288'], // 512 MiB in KiB
        'max_upload_time' => 20, // minutes for slower transfers
    ],

    'payload' => [
        'max_size' => 1024 * 1024,
        'max_nesting_depth' => 24,
        'max_calls' => 50,
        'max_components' => 200,
    ],
];
