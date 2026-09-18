<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    public function getHeading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return 'Two-factor authentication';
        }

        return 'Administration';
    }

    public function getSubheading(): string|Htmlable|null
    {
        if (filled($this->userUndertakingMultiFactorAuthentication)) {
            return 'Enter the code from your authenticator app, or use a recovery code.';
        }

        return 'Sign in to manage the website.';
    }
}
