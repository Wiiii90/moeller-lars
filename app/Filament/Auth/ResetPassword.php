<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Illuminate\Contracts\Support\Htmlable;

class ResetPassword extends BaseResetPassword
{
    public function getHeading(): string | Htmlable | null
    {
        return 'Choose a new password';
    }
}
