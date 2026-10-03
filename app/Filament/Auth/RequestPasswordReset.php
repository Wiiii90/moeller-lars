<?php

namespace App\Filament\Auth;

use App\Domain\Admin\AdminNotifier;
use App\Notifications\AdminPasswordResetNotification;
use DanHarrin\LivewireRateLimiting\Exceptions\TooManyRequestsException;
use Filament\Auth\Pages\PasswordReset\RequestPasswordReset as BaseRequestPasswordReset;
use Filament\Facades\Filament;
use Filament\Models\Contracts\FilamentUser;
use Illuminate\Auth\Events\PasswordResetLinkSent;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\Password;
use LogicException;
use SensitiveParameter;

class RequestPasswordReset extends BaseRequestPasswordReset
{
    public function request(): void
    {
        try {
            $this->rateLimit(2);
        } catch (TooManyRequestsException $exception) {
            app(AdminNotifier::class)->notification(
                title: 'Too many attempts',
                body: 'Try again in '.$exception->secondsUntilAvailable.' seconds.',
                status: 'danger',
            );

            return;
        }

        $data = $this->form->getState();

        Password::broker(Filament::getAuthPasswordBroker())->sendResetLink(
            $this->getCredentialsFromFormData($data),
            function (CanResetPassword $user, #[SensitiveParameter] string $token): void {
                if (
                    ($user instanceof FilamentUser) &&
                    (! $user->canAccessPanel(Filament::getCurrentOrDefaultPanel()))
                ) {
                    return;
                }

                if (! method_exists($user, 'notify')) {
                    $userClass = $user::class;

                    throw new LogicException("Model [{$userClass}] does not have a [notify()] method.");
                }

                $user->notify(new AdminPasswordResetNotification(
                    Filament::getResetPasswordUrl($token, $user),
                ));

                if (class_exists(PasswordResetLinkSent::class)) {
                    event(new PasswordResetLinkSent($user));
                }
            },
        );

        app(AdminNotifier::class)->notification(
            title: 'Check your inbox',
            body: 'If an administrator account exists for that email address, a password reset link has been sent.',
            status: 'success',
        );
        $this->form->fill();
    }

    public function getHeading(): string|Htmlable|null
    {
        return 'Reset password';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(array $data): array
    {
        return [
            'email' => $data['email'],
            'is_admin' => true,
        ];
    }
}
