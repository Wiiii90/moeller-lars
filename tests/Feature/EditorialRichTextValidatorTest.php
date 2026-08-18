<?php

use App\Domain\Admin\EditorialRichTextValidator;
use Illuminate\Validation\ValidationException;

it('accepts the constrained Markdown used by artist editors', function () {
    expect(fn () => app(EditorialRichTextValidator::class)->validate(
        "A **strong** line with [a safe link](https://example.com).\n\n- One\n- Two",
        'body',
    ))->not->toThrow(Throwable::class);
});

it('maps unsupported Markdown to the edited field', function () {
    try {
        app(EditorialRichTextValidator::class)->validate('# Unsupported heading', 'body');
        $this->fail('Expected rich-text validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('body');
    }
});

it('maps unsafe links to the edited field', function () {
    try {
        app(EditorialRichTextValidator::class)->validate('[unsafe](javascript:alert(1))', 'description');
        $this->fail('Expected rich-text link validation to fail.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('description');
    }
});
