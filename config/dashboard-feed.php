<?php

// Code-owned release communication: the Markdown files are the canonical bodies.
return [
    'items' => [
        [
            'id' => 'announcement-pre-release',
            'type' => 'announcement',
            'date' => '2026-10-15',
            'title' => 'Welcome to your new website administration',
            'body_file' => 'announcements/pre-release.md',
            'related_key' => 'static:changelog-v0.3.0',
            'related_label' => 'Read the v0.3.0 changelog',
        ],
        [
            'id' => 'changelog-v0.3.0',
            'type' => 'changelog',
            'date' => '2026-09-18',
            'title' => 'v0.3.0 — Admin and editorial foundation',
            'body_file' => 'versions/v0.3.0.md',
        ],
        [
            'id' => 'changelog-v0.2.0',
            'type' => 'changelog',
            'date' => '2026-08-20',
            'title' => 'v0.2.0 — Galleries, viewer and media management',
            'body_file' => 'versions/v0.2.0.md',
        ],
        [
            'id' => 'changelog-v0.1.0',
            'type' => 'changelog',
            'date' => '2026-08-16',
            'title' => 'v0.1.0 — Application and media foundations',
            'body_file' => 'versions/v0.1.0.md',
        ],
    ],
];
