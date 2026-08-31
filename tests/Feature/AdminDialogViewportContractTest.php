<?php

it('keeps native Filament dialogs attached to the browser viewport with internal scrolling', function (): void {
    $dialogs = file_get_contents(resource_path('css/admin/dialogs.css'));
    $media = file_get_contents(resource_path('css/admin/media.css'));

    expect($media)->toBeString()
        ->and($media)->toContain("@import './dialogs.css';")
        ->and($dialogs)->toBeString()
        ->and($dialogs)->toContain('.fi-main {')
        ->and($dialogs)->toContain('transform: none;')
        ->and($dialogs)->toContain('.fi-modal-close-overlay')
        ->and($dialogs)->toContain('position: fixed;')
        ->and($dialogs)->toContain('.fi-modal-window')
        ->and($dialogs)->toContain('max-height: calc(100dvh - 2rem);')
        ->and($dialogs)->toContain(".fi-modal-header,\n.fi-modal-footer")
        ->and($dialogs)->toContain('.fi-modal-content')
        ->and($dialogs)->toContain('overflow-y: auto;')
        ->and($dialogs)->toContain('overscroll-behavior: contain;');
});
