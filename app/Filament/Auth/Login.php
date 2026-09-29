<?php

namespace App\Filament\Auth;

use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Notifications\Notification;
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

    protected function getRateLimitedNotification(TooManyRequestsException $exception): ?Notification
    {
        $message = 'Too many attempts. Try again in '.$exception->secondsUntilAvailable.' seconds.';

        $this->addError('data.email', $message);
        $this->addError('code', $message);

        return null;
    }
}
