<?php

namespace App\Filament\Support;

use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use LogicException;
use SensitiveParameter;

final class AccountMenuAction
{
    public static function configure(Action $action, AppAuthentication $appAuthentication): Action
    {
        return AdminDialog::command(
            $action
                ->label('Account')
                ->icon(AdminIcon::Account->value)
                ->url(null)
                ->sort(-10)
                ->modalHeading('Account & security')
                ->fillForm(fn (): array => [
                    'name' => (string) self::user()->getAttribute('name'),
                    'email' => (string) self::user()->getAttribute('email'),
                ])
                ->schema(fn (): array => [
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => 'Account'])
                        ->columnSpanFull(),
                    Group::make([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label('Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->rule(fn () => Rule::unique('users', 'email')->ignore(self::user()->getKey())),
                    ])
                        ->columns(2)
                        ->columnSpanFull(),
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => 'Security'])
                        ->columnSpanFull(),
                    Group::make([
                        AdminPasswordField::password(
                            TextInput::make('password')->label('New password'),
                            'account',
                        )
                            ->dehydrated(fn (#[SensitiveParameter] mixed $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (#[SensitiveParameter] string $state): string => Hash::make($state)),
                        AdminPasswordField::confirmation(
                            TextInput::make('passwordConfirmation')->label('Confirm new password'),
                            'account',
                        )
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                        TextInput::make('currentPassword')
                            ->label('Current password')
                            ->password()
                            ->autocomplete('current-password')
                            ->revealable(Filament::arePasswordsRevealable())
                            ->required(fn (Get $get): bool => self::requiresCurrentPassword($get))
                            ->rule(
                                'current_password:'.Filament::getAuthGuard(),
                                fn (Get $get): bool => self::requiresCurrentPassword($get),
                            )
                            ->dehydrated(false)
                            ->columnSpanFull(),
                    ])
                        ->columns(2)
                        ->columnSpanFull(),
                    ...$appAuthentication->getManagementSchemaComponents(),
                ])
                ->beforeFormValidated(function (Action $action): void {
                    $key = 'admin-account-update:'.self::user()->getAuthIdentifier();

                    if (! RateLimiter::tooManyAttempts($key, 5)) {
                        RateLimiter::hit($key, 60);

                        return;
                    }

                    Notification::make()
                        ->danger()
                        ->title('Too many attempts')
                        ->body('Try again in a minute.')
                        ->send();

                    $action->halt();
                })
                ->action(function (array $data): void {
                    $user = self::user();
                    $passwordChanged = array_key_exists('password', $data);

                    $user->update($data);

                    if ($passwordChanged && request()->hasSession()) {
                        request()->session()->put([
                            'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
                        ]);
                    }

                    Notification::make()
                        ->success()
                        ->title('Account updated')
                        ->send();
                }),
            'Save account',
            AdminDialogSize::Default,
        );
    }

    private static function requiresCurrentPassword(Get $get): bool
    {
        return filled($get('password'))
            || ($get('email') !== self::user()->getAttribute('email'));
    }

    private static function user(): User
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            throw new LogicException('The account dialog requires an authenticated application user.');
        }

        return $user;
    }
}
