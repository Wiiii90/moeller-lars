<?php

namespace App\Filament\Support\Controls;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;

final class AdminControl
{
    private const WRAPPER_CLASS = 'admin-control-field admin-form-controls';

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

    /** @template T of \Filament\Forms\Components\Field
     *  @param T $field
     *  @return T
     */
    public static function decorate($field)
    {
        return $field->extraFieldWrapperAttributes([
            'class' => self::WRAPPER_CLASS,
            'data-admin-control' => $field->getName(),
        ]);
    }

    /** @template T of \Filament\Forms\Components\Field
     *  @param T $field
     *  @return T
     */
    private static function label($field, ?string $label)
    {
        return $label === null ? $field : $field->label($label);
    }
}
