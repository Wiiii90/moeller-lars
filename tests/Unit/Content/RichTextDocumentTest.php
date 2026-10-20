<?php

use App\Domain\Content\RichTextDocument;
use App\Domain\Content\RichTextMediaReference;

it('keeps visual HTML and upgrades canonical legacy image references for the editor', function (): void {
    expect(RichTextDocument::forEditor('<p>Hello</p>'))->toBe('<p>Hello</p>');
    $html = RichTextDocument::forEditor("**Hello**\n\n![Alt](media:17)");
    expect($html)->toContain('<strong>Hello</strong>')
        ->toContain('data-id="17"')
        ->and(RichTextMediaReference::ids($html))->toBe([17]);
});

it('removes only the targeted canonical image from visual rich text', function (): void {
    $body = '<p>Intro</p><p><img data-id="3" alt="Old"></p><p><img data-id="4" alt="Retained"></p>';
    $clean = RichTextMediaReference::remove($body, 3);
    expect($clean)->not->toContain('data-id="3"')
        ->and($clean)->toContain('data-id="4"')
        ->and($clean)->toContain('Intro');
});

it('recognizes media references in both text generations', function (): void {
    expect(RichTextMediaReference::ids('![Old](media:3) <img data-id="4">'))->toBe([3, 4]);
});
