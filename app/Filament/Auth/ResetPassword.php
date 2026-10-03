<?php

namespace App\Filament\Auth;

use App\Domain\Admin\AdminNotifier;
use App\Filament\Support\AdminPasswordField;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Http\Responses\Contracts\PasswordResetResponse;
use Filament\Auth\Pages\PasswordReset\ResetPassword as BaseResetPassword;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Models\Contracts\FilamentUser;
use Filament\Schemas\Components\Component;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class ResetPassword extends BaseResetPassword
{
    public function resetPassword(): ?PasswordResetResponse
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            $this->rateLimitedFeedback($exception);

            return null;
        }

        if ($this->isResetPasswordRateLimitedWithoutFrameworkFeedback($this->email)) {
            return null;
        }

        $data = $this->form->getState();
        $data['email'] = $this->email;
        $data['token'] = $this->token;
        $hasPanelAccess = true;

        $status = Password::broker(Filament::getAuthPasswordBroker())->reset(
            $this->getCredentialsFromFormData($data),
            function (CanResetPassword | Model | Authenticatable $user) use ($data, &$hasPanelAccess): void {
                if (
                    ($user instanceof FilamentUser) &&
                    (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))
                ) {
                    $hasPanelAccess = false;

                    return;
                }

                $user->forceFill([
                    $user->getAuthPasswordName() => Hash::make($data['password']),
                    $user->getRememberTokenName() => Str::random(60),
                ])->save();

                event(new PasswordReset($user));
            },
        );

        if ($hasPanelAccess === false) {
            $status = Password::INVALID_USER;
        }

        if ($status === Password::PASSWORD_RESET) {
            app(AdminNotifier::class)->notification(
                title: __($status),
                status: 'success',
            );

            return app(PasswordResetResponse::class);
        }

        app(AdminNotifier::class)->notification(
            title: __($status),
            status: 'danger',
        );

        return null;
    }

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

    private function isResetPasswordRateLimitedWithoutFrameworkFeedback(?string $email): bool
    {
        if (blank($email)) {
            return false;
        }

        $rateLimitingKey = 'filament-reset-password:'.sha1($email);

        if (RateLimiter::tooManyAttempts($rateLimitingKey, maxAttempts: 2)) {
            $this->rateLimitedFeedback(new TooManyRequestsException(
                static::class,
                'resetPassword',
                request()->ip(),
                RateLimiter::availableIn($rateLimitingKey),
            ));

            return true;
        }

        RateLimiter::hit($rateLimitingKey);

        return false;
    }

    private function rateLimitedFeedback(TooManyRequestsException $exception): void
    {
        app(AdminNotifier::class)->notification(
            title: 'Too many attempts',
            body: 'Try again in '.$exception->secondsUntilAvailable.' seconds.',
            status: 'danger',
        );
    }
}
