<?php

namespace App\Filament\Support;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\RegionalFormats;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Select;
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
                ->label(__('Account'))
                ->icon(AdminIcon::Account->value)
                ->url(null)
                // Preserve the original position after the theme switcher.
                ->sort(0)
                ->modalHeading(fn (): string => __('Account & security'))
                ->fillForm(fn (): array => [
                    'name' => (string) self::user()->getAttribute('name'),
                    'email' => (string) self::user()->getAttribute('email'),
                    'admin_region' => (string) self::user()->getAttribute('admin_region'),
                    'admin_language' => (string) (self::user()->getAttribute('admin_language') ?: 'en'),
                ])
                ->schema(fn (): array => [
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => __('Account')])
                        ->columnSpanFull(),
                    Group::make([
                        TextInput::make('name')
                            ->label(__('Name'))
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->label(__('Email'))
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->rule(fn () => Rule::unique('users', 'email')->ignore(self::user()->getKey())),
                    ])
                        ->columns(2)
                        ->columnSpanFull(),
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => __('Preferences')])
                        ->columnSpanFull(),
                    Select::make('admin_language')
                        ->label(__('Language'))
                        ->options(['en' => 'English', 'de' => 'Deutsch'])
                        ->required()
                        ->in(['en', 'de'])
                        ->columnSpanFull(),
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => __('Regional Formats')])
                        ->columnSpanFull(),
                    Select::make('admin_region')
                        ->label(__('Dates and numbers'))
                        ->options(RegionalFormats::OPTIONS)
                        ->required()
                        ->in(array_keys(RegionalFormats::OPTIONS))
                        ->columnSpanFull(),
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => __('Security')])
                        ->columnSpanFull(),
                    Group::make([
                        AdminPasswordField::password(
                            TextInput::make('password')->label(__('New password')),
                            'account',
                        )
                            ->dehydrated(fn (#[SensitiveParameter] mixed $state): bool => filled($state))
                            ->dehydrateStateUsing(fn (#[SensitiveParameter] string $state): string => Hash::make($state)),
                        AdminPasswordField::confirmation(
                            TextInput::make('passwordConfirmation')->label(__('Confirm new password')),
                            'account',
                        )
                            ->required(fn (Get $get): bool => filled($get('password')))
                            ->dehydrated(false),
                        TextInput::make('currentPassword')
                            ->label(__('Current password'))
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

                    app(AdminNotifier::class)->notification(
                        title: __('Too many attempts'),
                        body: __('Try again in a minute.'),
                        status: 'danger',
                    );

                    $action->halt();
                })
                ->action(function (array $data, \Livewire\Component $livewire): void {
                    $user = self::user();
                    $passwordChanged = array_key_exists('password', $data);
                    $previousLanguage = self::language();

                    $user->update($data);
                    $newLanguage = self::language();
                    self::refreshLanguage($previousLanguage, $newLanguage, $livewire);

                    if ($passwordChanged && request()->hasSession()) {
                        request()->session()->put([
                            'password_hash_'.Filament::getAuthGuard() => $user->getAuthPassword(),
                        ]);
                    }

                    app(AdminNotifier::class)->notification(
                        title: __('Account updated'),
                        status: 'success',
                    );

                }),
            fn (): string => __('Save account'),
            AdminDialogSize::Default,
        );
    }

    /**
     * Expose the language choice directly from the user badge, without
     * requiring the full account/security editor. Both paths persist the same
     * per-user preference and reuse the panel's normal locale middleware.
     */
    public static function languageAction(): Action
    {
        return AdminDialog::command(
            Action::make('changeAdminLanguage')
                ->label(fn (): string => __('Language'))
                ->icon(AdminIcon::Language->value)
                // New item after Account; do not move any existing menu entry.
                ->sort(1)
                ->modalHeading(fn (): string => __('Language'))
                ->fillForm(fn (): array => ['admin_language' => self::language()])
                ->schema(fn (): array => [
                    Select::make('admin_language')
                        ->label(__('Language'))
                        ->options(['en' => 'English', 'de' => 'Deutsch'])
                        ->required()
                        ->in(['en', 'de']),
                ])
                ->action(function (array $data, \Livewire\Component $livewire): void {
                    $user = self::user();
                    $next = (string) ($data['admin_language'] ?? '');
                    $previous = self::language();

                    if ($previous === $next) {
                        return;
                    }

                    $user->update(['admin_language' => $next]);
                    self::refreshLanguage($previous, $next, $livewire);
                    app(AdminNotifier::class)->notification(
                        title: __('Account updated'),
                        status: 'success',
                    );
                }),
            fn (): string => __('Save language'),
            AdminDialogSize::Small,
        );
    }

    private static function language(): string
    {
        return (string) (self::user()->getAttribute('admin_language') ?: 'en');
    }

    private static function refreshLanguage(string $previous, string $next, \Livewire\Component $livewire): void
    {
        if ($next === $previous) {
            return;
        }

        app()->setLocale($next);

        // Existing Alpine/Livewire trees contain old-language strings. Use a
        // full navigation, not Filament's SPA partial navigation.
        $livewire->redirect(request()->header('Referer') ?: url('/admin'), navigate: false);
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
