<?php

namespace App\Filament\Auth;

use App\Domain\Admin\AdminNotifier;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Auth\MultiFactor\App\Actions\DisableAppAuthenticationAction;
use Filament\Auth\MultiFactor\App\Actions\RegenerateAppAuthenticationRecoveryCodesAction;
use Filament\Auth\MultiFactor\App\Actions\SetUpAppAuthenticationAction;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;

final class AdminAppAuthentication extends AppAuthentication
{
    /**
     * @return array<Action>
     */
    public function getActions(): array
    {
        $user = Filament::auth()->user();
        $isRecoverable = $this->isRecoverable();

        return [
            SetUpAppAuthenticationAction::make($this)
                ->action(function (array $arguments): void {
                    $user = Filament::auth()->user();
                    $encrypted = decrypt($arguments['encrypted']);

                    if ($user->getAuthIdentifier() !== $encrypted['userId']) {
                        return;
                    }

                    DB::transaction(function () use ($encrypted, $user): void {
                        $this->saveSecret($user, $encrypted['secret']);

                        if ($this->isRecoverable()) {
                            $this->saveRecoveryCodes($user, $encrypted['recoveryCodes']);
                        }
                    });

                    app(AdminNotifier::class)->feedback(
                        title: __('filament-panels::auth/multi-factor/app/actions/set-up.notifications.enabled.title'),
                        status: 'success',
                    );
                })
                ->hidden(fn (): bool => $this->isEnabled($user)),

            RegenerateAppAuthenticationRecoveryCodesAction::make($this)
                ->action(function (Action $action, HasActions $livewire): void {
                    $recoveryCodes = $this->generateRecoveryCodes();
                    $user = Filament::auth()->user();

                    $this->saveRecoveryCodes($user, $recoveryCodes);

                    $livewire->mountAction('showNewRecoveryCodes', arguments: [
                        'recoveryCodes' => $recoveryCodes,
                    ]);

                    app(AdminNotifier::class)->feedback(
                        title: __('filament-panels::auth/multi-factor/app/actions/regenerate-recovery-codes.notifications.regenerated.title'),
                        status: 'success',
                    );
                })
                ->visible(fn (): bool => $this->isEnabled($user) && $isRecoverable && $this->canRegenerateRecoveryCodes()),

            DisableAppAuthenticationAction::make($this)
                ->action(function () use ($isRecoverable): void {
                    $user = Filament::auth()->user();

                    DB::transaction(function () use ($isRecoverable, $user): void {
                        $this->saveSecret($user, null);

                        if ($isRecoverable) {
                            $this->saveRecoveryCodes($user, null);
                        }
                    });

                    app(AdminNotifier::class)->feedback(
                        title: __('filament-panels::auth/multi-factor/app/actions/disable.notifications.disabled.title'),
                        status: 'success',
                    );
                })
                ->visible(fn (): bool => $this->isEnabled($user)),
        ];
    }
}
