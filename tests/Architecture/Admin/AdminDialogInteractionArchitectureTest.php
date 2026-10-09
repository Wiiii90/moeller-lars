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

it('keeps dialog headings and header actions on one shared first-row axis', function (): void {
    $root = dirname(__DIR__, 3);
    $contract = file_get_contents($root.'/resources/css/admin/dialog-contract.css');

    expect($contract)
        ->toContain('--admin-dialog-header-reserve')
        ->toContain('.admin-dialog--header-actions .fi-modal-header > div:last-child')
        ->toContain('.admin-dialog--header-actions .fi-modal-heading')
        ->toContain('min-height: var(--admin-dialog-header-action-size);')
        ->toContain('align-items: center;')
        ->toContain('.admin-dialog--header-actions:has(.fi-modal-footer-actions > :first-child)')
        ->toContain('.admin-dialog--header-actions:has(.fi-modal-footer-actions > :nth-child(4))')
        ->not->toContain('padding-inline-end: 9rem;')
        ->not->toContain('padding-inline-end: 6.75rem;')
        ->not->toContain('.admin-dialog--confirmation .fi-modal-header {')
        ->not->toContain('.admin-dialog--confirmation .fi-modal-heading {')
        ->not->toContain('.admin-dialog--confirmation .fi-modal-description {');
});

it('applies canonical chrome to every icon action in the dialog header rail', function (): void {
    $root = dirname(__DIR__, 3);
    $contract = file_get_contents($root.'/resources/css/admin/dialog-contract.css');

    expect($contract)
        ->toContain('.admin-dialog--header-actions .fi-modal-footer-actions .fi-icon-btn,')
        ->toContain('.admin-dialog--header-actions .fi-modal-footer-actions .fi-icon-btn:hover,')
        ->toContain('.admin-dialog--header-actions .fi-modal-footer-actions .fi-icon-btn:focus-visible,')
        ->toContain('.admin-dialog--header-actions .fi-modal-footer-actions .fi-icon-btn svg,');
});
