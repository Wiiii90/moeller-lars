<?php

namespace App\Filament\Support;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;

/**
 * Component conversion is a separate editorial commit, not an ordinary
 * autosaving field change. Both Home and Custom Page use this control.
 */
final class ComponentTypeChangeControl
{
    /** @param array<string, string> $options
     * @return array{0: Select, 1: Actions}
     */
    public static function make(
        string $field,
        array $options,
        string $original,
        Closure $losesContent,
        Closure $commit,
    ): array {
        $select = Select::make($field)
            ->label('Component')
            ->options($options)
            ->required()
            ->live()
            // Only stage the selected kind. Keep the modal's generic
            // wire:change autosave from treating this as a content edit.
            ->extraFieldWrapperAttributes(['x-on:change.stop' => '']);

        $action = Action::make('applyComponentType')
            ->label('Apply type')
            ->icon('heroicon-o-check')
            ->color('primary')
            ->visible(fn (Get $get): bool => ($get($field) !== $original)
                && array_key_exists((string) $get($field), $options))
            ->requiresConfirmation(fn (Get $get): bool => $losesContent((string) $get($field)))
            ->modalHeading('Replace component?')
            ->modalDescription('The existing component may lose content. Unapplied edits in this dialog will be discarded.')
            ->action(function (Get $get) use ($field, $original, $options, $commit): void {
                $target = $get($field);
                if (! is_string($target) || $target === $original || ! array_key_exists($target, $options)) {
                    return;
                }

                $commit($target);
            });

        return [
            $select,
            Actions::make([$action])
                ->columnSpanFull(),
        ];
    }
}
