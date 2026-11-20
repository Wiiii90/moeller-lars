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
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Component;
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
                ->sort(0)
                ->modalHeading(fn (): string => __('Account & security'))
                ->fillForm(fn (): array => [
                    'name' => (string) self::user()->getAttribute('name'),
                    'email' => (string) self::user()->getAttribute('email'),
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
                ->action(function (array $data): void {
                    $user = self::user();
                    $user->fill($data);
                    if (! $user->isDirty()) {
                        return;
                    }

                    $passwordChanged = $user->isDirty('password');
                    $user->save();

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

    public static function settingsAction(): Action
    {
        return AdminDialog::edit(
            Action::make('changeAdminSettings')
                ->label(fn (): string => __('Settings'))
                ->icon(AdminIcon::Settings->value)
                ->sort(1)
                ->modalHeading(fn (): string => __('Settings'))
                ->fillForm(fn (): array => [
                    'admin_language' => (string) (self::user()->getAttribute('admin_language') ?: 'en'),
                    'admin_region' => RegionalFormats::region(self::user()->getAttribute('admin_region')),
                    'admin_number_format' => RegionalFormats::numberRegion(self::user()->getAttribute('admin_number_format')),
                    'admin_time_format' => RegionalFormats::timeFormat(self::user()->getAttribute('admin_time_format')),
                    'admin_time_zone' => RegionalFormats::timeZone(self::user()->getAttribute('admin_time_zone')),
                ])
                ->schema(fn (): array => [
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => __('Preferences')])
                        ->columnSpanFull(),
                    Select::make('admin_language')
                        ->label(__('Language'))
                        ->options(['en' => 'English', 'de' => 'Deutsch'])
                        ->in(['en', 'de'])
                        ->columnSpanFull(),
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => __('Regional Formats')])
                        ->columnSpanFull(),
                    Group::make([
                        Select::make('admin_region')
                            ->label(__('Dates'))
                            ->options(RegionalFormats::OPTIONS)
                            ->in(array_keys(RegionalFormats::OPTIONS)),
                        Select::make('admin_number_format')
                            ->label(__('Numbers'))
                            ->options(RegionalFormats::NUMBER_OPTIONS)
                            ->in(array_keys(RegionalFormats::NUMBER_OPTIONS)),
                        Select::make('admin_time_format')
                            ->label(__('Time'))
                            ->options(fn (): array => array_map(static fn (string $value): string => __($value), RegionalFormats::TIME_OPTIONS))
                            ->in(array_keys(RegionalFormats::TIME_OPTIONS)),
                        Select::make('admin_time_zone')
                            ->label(__('Time zone'))
                            ->options(RegionalFormats::timeZoneOptions())
                            ->searchable()
                            ->optionsLimit(count(RegionalFormats::timeZoneOptions()))
                            ->in(array_keys(RegionalFormats::timeZoneOptions())),
                    ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data, Component $livewire): void {
                    $user = self::user();
                    $user->fill($data);
                    if (! $user->isDirty()) {
                        return;
                    }

                    $languageChanged = $user->isDirty('admin_language');
                    $user->save();

                    app(AdminNotifier::class)->notification(
                        title: __('Settings updated'),
                        status: 'success',
                    );

                    if ($languageChanged) {
                        // Locale is rendered into the existing Filament/Livewire
                        // tree. Only a language change needs a complete reload.
                        app()->setLocale((string) $user->getAttribute('admin_language'));
                        $livewire->redirect(request()->header('Referer') ?: url('/admin'), navigate: false);
                    }
                }),
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
