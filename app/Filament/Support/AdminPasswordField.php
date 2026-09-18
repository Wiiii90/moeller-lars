<?php

namespace App\Filament\Support;

use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class AdminPasswordField
{
    public static function password(
        TextInput $input,
        string $scope,
        string $confirmationField = 'passwordConfirmation',
    ): TextInput {
        $scopeJson = json_encode($scope, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_THROW_ON_ERROR);
        $actionSuffix = Str::studly($scope);

        return $input
            ->password()
            ->autocomplete('new-password')
            ->revealable(Filament::arePasswordsRevealable())
            ->rule(Password::default())
            ->same($confirmationField)
            ->extraInputAttributes(['data-admin-password-input' => $scope], merge: true)
            ->suffixActions([
                Action::make('generatePassword'.$actionSuffix)
                    ->label('Generate secure password')
                    ->icon(AdminIcon::GeneratePassword->value)
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Generate secure password')
                    ->alpineClickHandler("window.AdminPasswordTools.generate({$scopeJson}, \$event.currentTarget)"),
                Action::make('copyPassword'.$actionSuffix)
                    ->label('Copy password')
                    ->icon(AdminIcon::Copy->value)
                    ->iconButton()
                    ->color('gray')
                    ->tooltip('Copy password')
                    ->alpineClickHandler("window.AdminPasswordTools.copy({$scopeJson}, \$event.currentTarget)"),
            ]);
    }

    public static function confirmation(TextInput $input, string $scope): TextInput
    {
        return $input
            ->password()
            ->autocomplete('new-password')
            ->revealable(Filament::arePasswordsRevealable())
            ->extraInputAttributes(['data-admin-password-confirmation' => $scope], merge: true);
    }
}
