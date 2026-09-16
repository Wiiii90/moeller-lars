<?php

use App\Domain\Content\HistoricalCustomPageBlockCanonicalizer;
use App\Models\CustomPageSetting;

it('keeps legacy custom-page aliases outside normal runtime interpretation', function (): void {
    expect(CustomPageSetting::listItemPublished(['visible' => false]))->toBeTrue();

    $settings = new CustomPageSetting;

    expect($settings->contactChildren([
        'type' => 'contact',
        'show_email' => false,
        'show_form' => false,
        'social_platforms' => ['instagram'],
    ]))->toBe([]);
});

it('canonicalizes historical list and contact payloads at the migration boundary', function (): void {
    $blocks = HistoricalCustomPageBlockCanonicalizer::canonicalize([
        [
            'type' => 'list',
            'published' => true,
            'items' => [
                ['title' => 'Visible', 'visible' => true],
                ['title' => 'Hidden', 'visible' => false],
                ['title' => 'Canonical wins', 'published' => false, 'visible' => true],
            ],
        ],
        [
            'type' => 'contact',
            'published' => true,
            'show_email' => false,
            'show_form' => true,
            'form_state' => 'under_construction',
            'status_text' => '  Back soon  ',
            'social_platforms' => ['instagram', 'instagram', 'facebook', 5],
        ],
    ]);

    expect($blocks[0]['items'])->toBe([
        ['title' => 'Visible', 'published' => true],
        ['title' => 'Hidden', 'published' => false],
        ['title' => 'Canonical wins', 'published' => false],
    ]);

    expect($blocks[1])->toBe([
        'type' => 'contact',
        'published' => true,
        'children' => [
            ['type' => 'public_email', 'published' => false],
            ['type' => 'social_links', 'published' => true, 'social_platforms' => ['instagram', 'facebook']],
            [
                'type' => 'contact_form',
                'published' => true,
                'form_state' => 'under_construction',
                'status_text' => 'Back soon',
            ],
        ],
    ]);
});

it('preserves already-canonical contact children while stripping obsolete top-level fields', function (): void {
    $children = [
        ['type' => 'contact_form', 'published' => false, 'form_state' => 'enabled', 'status_text' => null],
    ];

    $blocks = HistoricalCustomPageBlockCanonicalizer::canonicalize([
        [
            'type' => 'contact',
            'published' => true,
            'children' => $children,
            'show_form' => true,
            'form_state' => 'under_construction',
        ],
    ]);

    expect($blocks)->toBe([
        [
            'type' => 'contact',
            'published' => true,
            'children' => $children,
        ],
    ]);
});
