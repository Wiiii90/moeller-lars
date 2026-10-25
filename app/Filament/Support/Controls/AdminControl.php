<?php

namespace App\Filament\Support\Controls;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\OneTimeCodeInput;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class AdminControl
{
    public const SEARCH_DEBOUNCE_MS = 300;

    private const WRAPPER_CLASS = 'admin-control-field admin-form-controls';

    public static function register(): void
    {
        TextInput::configureUsing(fn (TextInput $field) => self::decorate($field));
        Textarea::configureUsing(fn (Textarea $field) => self::decorate($field));
        Select::configureUsing(fn (Select $field) => self::decorate($field));
        Checkbox::configureUsing(fn (Checkbox $field) => self::decorate($field));
        Toggle::configureUsing(fn (Toggle $field) => self::decorateToggle($field));
        DatePicker::configureUsing(fn (DatePicker $field) => self::decorate($field));
        DateTimePicker::configureUsing(fn (DateTimePicker $field) => self::decorate($field));
        FileUpload::configureUsing(fn (FileUpload $field) => self::decorate($field));
        MarkdownEditor::configureUsing(fn (MarkdownEditor $field) => self::decorate($field));
        OneTimeCodeInput::configureUsing(fn (OneTimeCodeInput $field) => self::decorate($field));
        ColorPicker::configureUsing(fn (ColorPicker $field) => self::decorate($field));
    }

    public static function text(string $name, ?string $label = null): TextInput
    {
        return self::label(self::decorate(TextInput::make($name)), $label);
    }

    public static function email(string $name, ?string $label = null): TextInput
    {
        return self::text($name, $label)->email();
    }

    public static function url(string $name, ?string $label = null): TextInput
    {
        return self::text($name, $label)->url();
    }

    public static function number(string $name, ?string $label = null): TextInput
    {
        return self::text($name, $label)->numeric();
    }

    public static function textarea(string $name, ?string $label = null): Textarea
    {
        return self::label(self::decorate(Textarea::make($name)), $label);
    }

    public static function select(string $name, ?string $label = null): Select
    {
        return self::label(self::decorate(Select::make($name)), $label);
    }

    public static function checkbox(string $name, ?string $label = null): Checkbox
    {
        return self::label(self::decorate(Checkbox::make($name)), $label);
    }

    public static function toggle(string $name, ?string $label = null): Toggle
    {
        return self::label(self::decorate(Toggle::make($name)), $label);
    }

    public static function date(string $name, ?string $label = null): DatePicker
    {
        return self::label(self::decorate(DatePicker::make($name)), $label);
    }

    public static function dateTime(string $name, ?string $label = null): DateTimePicker
    {
        return self::label(self::decorate(DateTimePicker::make($name)), $label);
    }

    public static function file(string $name, ?string $label = null): FileUpload
    {
        return self::label(self::decorate(FileUpload::make($name)), $label);
    }

    /**
     * Filament 5 renders Toggle as an Alpine switch button, not an input.
     * Its click updates the entangled state but does not generate a native
     * change event. The shared Edit dialog deliberately persists on change,
     * so bridge the completed switch click into that existing event path.
     * The capture listener runs before Filament's own click, and nextTick
     * fires only after Alpine has applied the new state.
     */
    private static function decorateToggle(Toggle $field): Toggle
    {
        return self::decorate($field)->extraAlpineAttributes([
            'x-on:click.capture' => <<<'JS'
                if ($el.closest('.admin-dialog--edit')) {
                    $nextTick(() => $el.dispatchEvent(new Event('change', { bubbles: true })))
                }
                JS,
        ]);
    }

    /** @template T of \Filament\Forms\Components\Field
     * @param  T  $field
     * @return T
     */
    public static function decorate($field)
    {
        if ($field instanceof Select) {
            // Required selects may still render Filament's neutral placeholder
            // before a create value exists, but it is never a selectable state.
            // Edit dialogs are filled from persisted state, so they cannot fall
            // back to a selectable "Select an option" branch.
            $field->selectablePlaceholder(fn (): bool => ! $field->isRequired());
            self::bridgeEditStateChange($field);
        } elseif ($field instanceof DateTimePicker && ! $field->isNative()) {
            self::bridgeEditStateChange($field);
        }

        return $field->extraFieldWrapperAttributes([
            'class' => self::WRAPPER_CLASS,
            'data-admin-control' => $field->getName(),
        ], merge: true);
    }

    private static function bridgeEditStateChange(Select|DateTimePicker $field): void
    {
        $field->extraAlpineAttributes([
            'x-init' => <<<'JS'
                if (typeof state !== 'undefined') {
                    $watch('state', (value, previous) => {
                        if (value === previous || ! $el.closest('.admin-dialog--edit')) return
                        $nextTick(() => $el.dispatchEvent(new Event('change', { bubbles: true })))
                    })
                }
                JS,
        ], merge: true);
    }

    /** @template T of \Filament\Forms\Components\Field
     * @param  T  $field
     * @return T
     */
    private static function label($field, ?string $label)
    {
        return $label === null ? $field : $field->label($label);
    }
}
