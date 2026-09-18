<?php

namespace App\Filament\Auth;

use App\Filament\Support\AdminPasswordField;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;

class ResetPassword extends BaseResetPassword
{
    public function getHeading(): string|Htmlable|null
    {
        return 'Choose a new password';
    }

    protected function getPasswordFormComponent(): Component
    {
        return AdminPasswordField::password(
            TextInput::make('password')
                ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password.label'))
                ->required()
                ->validationAttribute(__('filament-panels::auth/pages/password-reset/reset-password.form.password.validation_attribute')),
            'password-reset',
        );
    }

    protected function getPasswordConfirmationFormComponent(): Component
    {
        return AdminPasswordField::confirmation(
            TextInput::make('passwordConfirmation')
                ->label(__('filament-panels::auth/pages/password-reset/reset-password.form.password_confirmation.label'))
                ->required()
                ->dehydrated(false),
            'password-reset',
        );
    }
}
