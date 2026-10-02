<?php

it('keeps dialog header action hitboxes bounded and independently clickable', function (): void {
    $root = dirname(__DIR__, 3);
    $interactions = file_get_contents($root.'/resources/css/admin/dialog-interactions.css');

    expect($interactions)
        ->toContain('width: max-content !important;')
        ->toContain('pointer-events: none;')
        ->toContain('pointer-events: auto;')
        ->toContain('max-width: var(--admin-dialog-header-action-size) !important;')
        ->toContain('max-height: var(--admin-dialog-header-action-size) !important;')
        ->toContain('.fi-modal-footer-actions > * {')
        ->toContain('.fi-modal-footer-actions .fi-icon-btn,')
        ->not->toContain('--admin-dialog-header-action-gap: .625rem;')
        ->toContain('z-index: 12;')
        ->toContain('.admin-dialog--header-actions .fi-modal-close-btn:focus-visible');
});

it('keeps persistent help triggers below the Filament modal layer', function (): void {
    $root = dirname(__DIR__, 3);
    $forms = file_get_contents($root.'/resources/css/admin/forms.css');

    expect($forms)
        ->toContain('.admin-help {')
        ->toContain('z-index: 20;')
        ->not->toContain('z-index: 120;');
});
