<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Domain\Content\SocialLinks;
use App\Filament\Support\AdminColorControl;
use App\Filament\Support\AdminHelp;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
final class General extends Page
{

    private const PERSISTED_FIELDS = [
        'favicon_media_asset_id',
        'background_mode',
        'background_color',
        'background_gradient_start',
        'background_gradient_end',
        'background_gradient_angle',
        'public_page_width',
        'public_content_padding',
        'public_email',
        'contact_recipient_email',
        'social_links',
        'default_media_copyright_notice',
        'legal_disclaimer',
        'legal_imprint',
        'legal_privacy',
        'legal_usage_rights',
    ];

    protected static string|BackedEnum|null $navigationIcon = AdminIcon::General;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'General';

    protected static ?string $title = 'General';

    protected static ?string $slug = 'general';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.general';

    private ?PublicContentSetting $settingsRecord = null;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public string $previewDevice = 'desktop';

    // Edit Action identity is the unique social platform, not a mutable row index.
    // Autosaved reordering must not redirect later field edits to another profile.
    public ?string $editingSocialPlatform = null;

    public function mount(): void
    {
        $data = $this->generalSettingsRecord()->only(self::PERSISTED_FIELDS);
        $mode = $data['background_mode'] ?? null;
        if ($mode === null || $mode === '' || $mode === PublicAppearance::MODE_DEFAULT) {
            $data['background_mode'] = PublicAppearance::MODE_SOLID;
            $data['background_color'] = PublicAppearance::DEFAULT_PAGE_COLOR;
        }

        $data['public_page_width'] = PublicAppearance::normalizePageWidth($data['public_page_width'] ?? null);
        $data['public_content_padding'] = PublicAppearance::normalizeContentPadding($data['public_content_padding'] ?? null);

        $this->data = $data;
        $this->syncAppearanceControlState();
        $this->form->fill($this->data);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function setPreviewDevice(string $device): void
    {
        if (! in_array($device, ['desktop', 'mobile'], true)) {
            return;
        }

        $this->previewDevice = $device;
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Group::make([
                    View::make('filament.schemas.components.general-status-metrics')
                        ->columnSpanFull(),

                    Group::make([
                        Group::make([
                            View::make('filament.schemas.components.admin-section-heading')
                                ->viewData([
                                    'label' => 'Appearance',
                                    'class' => 'general-stage-kicker',
                                ])
                                ->columnSpanFull(),

                            MediaAssetSelect::make(
                                'favicon_media_asset_id',
                                'faviconMediaAsset',
                                'Site icon',
                                imagesOnly: true,
                                includeDimensions: false,
                            )
                                ->placeholder('Choose from Storage')
                                ->selectablePlaceholder(false)
                                ->extraFieldWrapperAttributes(['class' => 'admin-favicon-control general-site-icon-control'])
                                ->nullable()
                                ->live()
                                ->suffixAction(
                                    Action::make('removeFavicon')
                                        ->label('Remove site icon')
                                        ->icon(AdminIcon::Remove->mini())
                                        ->iconButton()
                                        ->color('gray')
                                        ->extraAttributes(['class' => 'general-site-icon-remove'])
                                        ->visible(fn (callable $get): bool => filled($get('favicon_media_asset_id')))
                                        ->action(function ($livewire): void {
                                            if ($livewire instanceof self) {
                                                $livewire->removeFavicon();
                                            }
                                        }),
                                )
                                ->afterStateUpdated(self::persist('favicon_media_asset_id')),

                            Group::make([
                                Select::make('background_mode')
                                    ->label('Background')
                                    ->options([
                                        PublicAppearance::MODE_SOLID => 'Solid',
                                        PublicAppearance::MODE_GRADIENT => 'Linear gradient',
                                    ])
                                    ->placeholder(null)
                                    ->selectablePlaceholder(false)
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->extraFieldWrapperAttributes(['class' => 'general-background-mode-control'])
                                    ->afterStateUpdated(function ($livewire): void {
                                        if ($livewire instanceof self) {
                                            $livewire->persistChangedField('background_mode');
                                            if (($livewire->data['background_mode'] ?? null) === PublicAppearance::MODE_SOLID) {
                                                $livewire->persistChangedField('background_color');
                                            }
                                            $livewire->syncAppearanceControlState();
                                        }
                                    }),

                                AdminColorControl::make('background_primary_color', 'Primary color')
                                    ->extraFieldWrapperAttributes(['class' => 'general-color-control general-primary-color-control'])
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(function ($livewire, mixed $state): void {
                                        if ($livewire instanceof self) {
                                            $livewire->persistAppearanceColor('primary', $state);
                                        }
                                    }),
                                TextInput::make('background_gradient_angle')
                                    ->label('Angle')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(0)
                                    ->maxValue(360)
                                    ->step(1)
                                    ->suffix('°')
                                    ->placeholder((string) PublicAppearance::DEFAULT_GRADIENT_ANGLE)
                                    ->nullable()
                                    ->lazy()
                                    ->extraFieldWrapperAttributes(['class' => 'general-gradient-angle-control'])
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('background_gradient_angle'))
                                    ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_GRADIENT),
                                AdminColorControl::make('background_secondary_color', 'Secondary color')
                                    ->extraFieldWrapperAttributes(['class' => 'general-color-control general-secondary-color-control'])
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(function ($livewire, mixed $state): void {
                                        if ($livewire instanceof self) {
                                            $livewire->persistAppearanceColor('secondary', $state);
                                        }
                                    })
                                    ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_GRADIENT),
                            ])
                                ->columns(2)
                                ->extraAttributes(['class' => 'general-background-row'])
                                ->columnSpanFull(),

                            Group::make([
                                TextInput::make('public_page_width')
                                    ->label('Page width')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(PublicAppearance::MIN_PAGE_WIDTH)
                                    ->maxValue(PublicAppearance::MAX_PAGE_WIDTH)
                                    ->step(1)
                                    ->suffix('px')
                                    ->required()
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('public_page_width')),
                                TextInput::make('public_content_padding')
                                    ->label('Content padding')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(PublicAppearance::MIN_CONTENT_PADDING)
                                    ->maxValue(PublicAppearance::MAX_CONTENT_PADDING)
                                    ->step(1)
                                    ->suffix('px')
                                    ->required()
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('public_content_padding')),
                            ])
                                ->columns(2)
                                ->extraAttributes(['class' => 'general-layout-row'])
                                ->columnSpanFull(),

                            Hidden::make('background_color'),
                            Hidden::make('background_gradient_start'),
                            Hidden::make('background_gradient_end'),
                        ])
                            ->columns(1)
                            ->extraAttributes(['class' => 'general-appearance-stage__controls admin-form-controls'])
                            ->columnSpanFull(),

                        View::make('filament.schemas.components.general-live-preview')
                            ->viewData(fn ($livewire): array => ['generalPage' => $livewire])
                            ->columnSpanFull(),
                    ])
                        ->columns(3)
                        ->extraAttributes(fn ($livewire): array => [
                            'class' => 'general-appearance-stage admin-visual-stage',
                            'data-preview-device' => $livewire instanceof self ? $livewire->previewDevice : 'desktop',
                            'aria-label' => 'General settings and live public preview',
                        ])
                        ->columnSpanFull(),

                    Group::make([
                        View::make('filament.schemas.components.general-social-links')
                            ->viewData(fn ($livewire): array => ['generalPage' => $livewire])
                            ->columnSpanFull(),
                    ])
                        ->columns(1)
                        ->extraAttributes(['class' => 'admin-visual-stage-followup'])
                        ->columnSpanFull(),
                ])
                    ->columns(1)
                    ->extraAttributes(['class' => 'admin-visual-stage-block'])
                    ->columnSpanFull(),

                Group::make([
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData([
                            'label' => 'Contact',
                            'class' => 'general-form-section-heading',
                        ])
                        ->columnSpanFull(),

                    Group::make([
                        TextInput::make('public_email')
                            ->label(self::publicEmailLabel())
                            ->email()
                            ->maxLength(254)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('public_email')),
                        TextInput::make('contact_recipient_email')
                            ->label(self::contactRecipientLabel())
                            ->email()
                            ->maxLength(254)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('contact_recipient_email')),
                    ])
                        ->columns(2)
                        ->extraAttributes(['class' => 'general-two-column-settings general-contact-settings'])
                        ->columnSpanFull(),
                ])
                    ->columns(1)
                    ->extraAttributes(['class' => 'general-settings-section'])
                    ->columnSpanFull(),

                Group::make([
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData([
                            'label' => 'Legal',
                            'class' => 'general-form-section-heading',
                        ])
                        ->columnSpanFull(),

                    Group::make([
                        TextInput::make('default_media_copyright_notice')
                            ->label(self::defaultCopyrightLabel())
                            ->maxLength(500)
                            ->nullable()
                            ->lazy()
                            ->columnSpanFull()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('default_media_copyright_notice')),
                        Textarea::make('legal_imprint')
                            ->label(self::imprintLabel())
                            ->rows(8)
                            ->maxLength(20000)
                            ->nullable()
                            ->lazy()
                            ->afterStateUpdated(self::persist('legal_imprint')),
                        Textarea::make('legal_privacy')
                            ->label(self::privacyLabel())
                            ->rows(8)
                            ->maxLength(20000)
                            ->nullable()
                            ->lazy()
                            ->afterStateUpdated(self::persist('legal_privacy')),
                        Textarea::make('legal_usage_rights')
                            ->label(self::usageRightsLabel())
                            ->rows(8)
                            ->maxLength(20000)
                            ->nullable()
                            ->lazy()
                            ->afterStateUpdated(self::persist('legal_usage_rights')),
                        Textarea::make('legal_disclaimer')
                            ->label(self::externalLinksNoticeLabel())
                            ->rows(8)
                            ->maxLength(20000)
                            ->nullable()
                            ->lazy()
                            ->afterStateUpdated(self::persist('legal_disclaimer')),
                    ])
                        ->columns(2)
                        ->extraAttributes(['class' => 'general-two-column-settings general-legal-settings'])
                        ->columnSpanFull(),
                ])
                    ->columns(1)
                    ->extraAttributes(['class' => 'general-settings-section'])
                    ->columnSpanFull(),
            ])
            ->record($this->generalSettingsRecord())
            ->statePath('data');
    }

    public function removeFavicon(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $this->data['favicon_media_asset_id'] = null;
        $this->persistChangedField('favicon_media_asset_id');
    }

    public function persistAppearanceColor(string $slot, mixed $value): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $mode = $this->data['background_mode'] ?? PublicAppearance::MODE_SOLID;
        $field = match ($slot) {
            'primary' => $mode === PublicAppearance::MODE_GRADIENT ? 'background_gradient_start' : 'background_color',
            'secondary' => $mode === PublicAppearance::MODE_GRADIENT ? 'background_gradient_end' : null,
            default => null,
        };

        if ($field === null) {
            return;
        }

        if ($mode === PublicAppearance::MODE_SOLID) {
            $this->persistChangedField('background_mode');
        }

        $alias = $slot === 'primary' ? 'background_primary_color' : 'background_secondary_color';
        $this->resetErrorBag('data.'.$alias);
        $this->data[$field] = $value;
        $this->persistChangedField($field);

        foreach ($this->getErrorBag()->get('data.'.$field) as $message) {
            $this->addError('data.'.$alias, $message);
        }

        $this->syncAppearanceControlState();
    }

    public function syncAppearanceControlState(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $mode = $this->data['background_mode'] ?? PublicAppearance::MODE_SOLID;
        $this->data['background_primary_color'] = $mode === PublicAppearance::MODE_GRADIENT
            ? ($this->data['background_gradient_start'] ?? null)
            : ($this->data['background_color'] ?? PublicAppearance::DEFAULT_PAGE_COLOR);
        $this->data['background_secondary_color'] = $this->data['background_gradient_end'] ?? null;
    }

    public function addSocialLinkAction(): Action
    {
        return AdminDialog::create(
            Action::make('addSocialLink')
                ->label('Add social link')
                ->modalHeading('Add profile')
                ->fillForm(fn (): array => [
                    'platform' => '',
                    'url' => '',
                    'position' => count($this->socialLinks()) + 1,
                ])
                ->schema([
                    Select::make('platform')
                        ->label('Platform')
                        ->options(SocialLinks::options())
                        ->native()
                        ->required(),
                    TextInput::make('url')
                        ->label('Profile URL')
                        ->url()
                        ->maxLength(2048)
                        ->required(),
                    TextInput::make('position')
                        ->label('Position')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                ]),
            'Add profile',
            AdminDialogSize::Small,
        )->action(function (array $data): void {
            $links = $this->socialLinks();
            $platform = (string) ($data['platform'] ?? '');
            $url = (string) ($data['url'] ?? '');

            foreach ($links as $link) {
                if (($link['platform'] ?? null) === $platform) {
                    throw ValidationException::withMessages([
                        'platform' => 'Each social platform can only be configured once.',
                    ]);
                }
            }

            $position = max(1, min((int) ($data['position'] ?? count($links) + 1), count($links) + 1));
            array_splice($links, $position - 1, 0, [[
                'platform' => $platform,
                'url' => $url,
            ]]);

            $this->saveSocialLinks($links);
        });
    }

    /**
     * Autosave every semantic change using the canonical Edit dialog. The
     * persisted platform is the editor's stable identity across reordering;
     * the Action's original row index is only used to mount a fresh form.
     */
    public function editSocialLinkAction(): Action
    {
        return AdminDialog::edit(
            Action::make('editSocialLink')
                ->label('Edit')
                ->modalHeading('Edit profile')
                ->extraModalFooterActions(fn (array $arguments): array => $this->socialEditHeaderActions($arguments))
                ->fillForm(function (array $arguments): array {
                    $index = $this->socialLinkIndexForAction($arguments);
                    $links = $this->socialLinks();
                    $link = $index === null ? [] : ($links[$index] ?? []);
                    $this->editingSocialPlatform = $index === null ? null : (string) ($link['platform'] ?? '');

                    return [
                        'platform' => (string) ($link['platform'] ?? ''),
                        'url' => (string) ($link['url'] ?? ''),
                        'position' => $index === null ? 1 : $index + 1,
                    ];
                })
                ->schema([
                    Select::make('platform')
                        ->label('Platform')
                        ->options(SocialLinks::options())
                        ->native()
                        ->required(),
                    TextInput::make('url')
                        ->label('Profile URL')
                        ->url()
                        ->maxLength(2048)
                        ->required(),
                    TextInput::make('position')
                        ->label('Position')
                        ->numeric()
                        ->integer()
                        ->minValue(1)
                        ->required(),
                ]),
            AdminDialogSize::Small,
        )->action(function (array $data): void {
            $links = $this->socialLinks();
            $index = collect($links)->search(
                fn (array $link): bool => ($link['platform'] ?? null) === $this->editingSocialPlatform,
            );
            if ($index === false) {
                throw ValidationException::withMessages([
                    'platform' => 'This social profile no longer exists. Reopen the editor.',
                ]);
            }
            $platform = (string) ($data['platform'] ?? '');
            $url = (string) ($data['url'] ?? '');

            foreach ($links as $otherIndex => $link) {
                if ($otherIndex !== $index && ($link['platform'] ?? null) === $platform) {
                    throw ValidationException::withMessages([
                        'platform' => 'Each social platform can only be configured once.',
                    ]);
                }
            }

            $links[$index] = [
                'platform' => $platform,
                'url' => $url,
            ];
            $position = max(1, min((int) ($data['position'] ?? $index + 1), count($links)));
            $moved = array_splice($links, $index, 1);
            array_splice($links, $position - 1, 0, $moved);

            $this->saveSocialLinks($links);
            $this->editingSocialPlatform = $platform;
        });
    }

    /** @param array<string,mixed> $arguments
     * @return list<Action>
     */
    private function socialEditHeaderActions(array $arguments): array
    {
        $index = $this->socialLinkIndexForAction($arguments);
        if ($index === null) {
            return [];
        }

        $count = count($this->socialLinks());
        $actions = [];
        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $canMove = $direction === 'up' ? $index > 0 : $index < $count - 1;
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($direction): void {
                $current = collect($this->socialLinks())->search(
                    fn (array $link): bool => ($link['platform'] ?? null) === $this->editingSocialPlatform,
                );
                if ($current === false) {
                    throw ValidationException::withMessages(['platform' => 'This social profile changed. Reopen the editor.']);
                }
                $this->moveSocialLink((int) $current, $direction);
                $next = (int) $current + ($direction === 'up' ? -1 : 1);
                $this->replaceMountedAction('editSocialLink', ['index' => $next]);
            })->disabled(! $canMove);
        }
        $actions[] = AdminDialog::headerAction('dialogDeleteSocial', AdminRowAction::Delete, function (): void {
            $current = collect($this->socialLinks())->search(
                fn (array $link): bool => ($link['platform'] ?? null) === $this->editingSocialPlatform,
            );
            if ($current === false) {
                throw ValidationException::withMessages(['platform' => 'This social profile changed. Reopen the editor.']);
            }
            $this->deleteSocialLink((int) $current);
        })->cancelParentActions('editSocialLink');

        return $actions;
    }

    public function addSocialLink(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $links = is_array($this->data['social_links'] ?? null) ? array_values($this->data['social_links']) : [];
        $links[] = ['platform' => '', 'url' => ''];
        $this->data['social_links'] = $links;
    }

    public function updateSocialLink(int $index, string $field, mixed $value): void
    {
        if (! in_array($field, ['platform', 'url'], true) || ! isset($this->data['social_links'][$index]) || ! is_array($this->data['social_links'][$index])) {
            return;
        }

        $this->data['social_links'][$index][$field] = $value;
        $this->persistChangedField('social_links');
    }

    public function moveSocialLink(int $index, string $direction): void
    {
        $links = is_array($this->data['social_links'] ?? null) ? array_values($this->data['social_links']) : [];
        $target = $direction === 'up' ? $index - 1 : ($direction === 'down' ? $index + 1 : $index);

        if (! isset($links[$index], $links[$target]) || $target === $index) {
            return;
        }

        [$links[$index], $links[$target]] = [$links[$target], $links[$index]];
        $this->data['social_links'] = array_values($links);
        $this->persistChangedField('social_links');
    }

    public function sortSocialLink(int|string $index, int $position): void
    {
        $links = is_array($this->data['social_links'] ?? null) ? array_values($this->data['social_links']) : [];
        $from = filter_var($index, FILTER_VALIDATE_INT);
        if ($from === false || ! isset($links[$from])) {
            return;
        }

        $position = max(0, min($position, count($links) - 1));
        if ($from === $position) {
            return;
        }

        $moved = array_splice($links, $from, 1);
        array_splice($links, $position, 0, $moved);
        $this->data['social_links'] = $links;
        $this->persistChangedField('social_links');
    }

    public function deleteSocialLink(int $index): void
    {
        $links = is_array($this->data['social_links'] ?? null) ? array_values($this->data['social_links']) : [];
        if (! isset($links[$index])) {
            return;
        }

        array_splice($links, $index, 1);
        $this->data['social_links'] = $links;
        $this->persistChangedField('social_links');
    }

    public function persistChangedField(string $field): void
    {
        if (! in_array($field, self::PERSISTED_FIELDS, true) || ! is_array($this->data) || ! array_key_exists($field, $this->data)) {
            return;
        }

        $this->clearPersistenceErrors($field);
        $record = PublicContentSetting::general();
        $candidate = $this->normalizePersistenceValue($field, $this->data[$field]);
        $persisted = $this->normalizePersistenceValue($field, $record->getAttribute($field));

        if ($candidate === $persisted) {
            return;
        }

        try {
            app(AdminSettingsService::class)->updatePublicContent($record, [$field => $candidate]);
            $this->settingsRecord = null;
            if (in_array($field, ['background_color', 'background_gradient_start', 'background_gradient_end'], true)) {
                $this->data[$field] = $candidate;
            }
            if (in_array($field, [
                'favicon_media_asset_id',
                'background_mode',
                'background_color',
                'background_gradient_start',
                'background_gradient_end',
                'background_gradient_angle',
                'public_page_width',
                'public_content_padding',
            ], true)) {
                $this->dispatch('general-appearance-updated');
            }

            app(AdminNotifier::class)->notification(
                'Changes saved',
                $this->persistenceFeedbackLabel($field).' updated.',
                'success',
            );
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $errorKey = str_starts_with($key, 'data.') ? $key : 'data.'.$key;
                foreach ($messages as $message) {
                    $this->addError($errorKey, $message);
                }
            }

            $message = collect($exception->errors())->flatten()->first();
            app(AdminNotifier::class)->notification(
                'Change not saved',
                is_string($message) ? $message : 'This setting could not be saved.',
                'danger',
            );
        } catch (Throwable $exception) {
            report($exception);
            $message = 'This setting could not be saved. Please try again.';
            $this->addError('data.'.$field, $message);
            app(AdminNotifier::class)->notification('Change not saved', $message, 'danger');
        }
    }

    /** @return array<int, array{index: int, link: array<string, mixed>}> */
    public function socialRows(): array
    {
        $rows = [];
        foreach ($this->socialLinks() as $index => $link) {
            $rows[] = ['index' => $index, 'link' => $link];
        }

        return $rows;
    }

    private static function persist(string $field): Closure
    {
        return static function ($livewire) use ($field): void {
            if ($livewire instanceof self) {
                $livewire->persistChangedField($field);
            }
        };
    }

    private static function publicEmailLabel(): HtmlString
    {
        return self::helpLabel(
            'Public email',
            'About public email',
            'Used by published Public email contact components. Leave this empty if no public email address should be shown.',
        );
    }

    private static function contactRecipientLabel(): HtmlString
    {
        return self::helpLabel(
            'Contact form recipient',
            'About contact form delivery',
            'Contact-form messages are delivered privately to this address. It can be different from the public email.',
        );
    }

    private static function defaultCopyrightLabel(): HtmlString
    {
        return self::helpLabel(
            'Default copyright notice',
            'About the default copyright notice',
            'Used as the inherited media copyright notice. Individual assets may override it or use no notice. This setting does not add a copyright line to artwork detail pages.',
        );
    }

    private static function imprintLabel(): HtmlString
    {
        return self::helpLabel(
            'Imprint',
            'About the imprint',
            'Provider identification and contact details for the website operator. Use accurate information supplied by the responsible person.',
        );
    }

    private static function privacyLabel(): HtmlString
    {
        return self::helpLabel(
            'Privacy Policy',
            'About the privacy policy',
            'Explain the actual processing of visitor data, including hosting, server logs, contact messages and analytics where applicable.',
        );
    }

    private static function usageRightsLabel(): HtmlString
    {
        return self::helpLabel(
            'Copyright & AI Use',
            'About copyright and AI use',
            'Explain artwork and photographic rights, reproduction and licensing requests, and the reservation of rights for AI training and data mining.',
        );
    }

    private static function externalLinksNoticeLabel(): HtmlString
    {
        return self::helpLabel(
            'External Links Notice',
            'About the external links notice',
            'Optional notice about linked third-party websites. Displayed in the External Links Notice section of the Legal Disclaimer component.',
        );
    }

    private static function helpLabel(string $label, string $title, string $body): HtmlString
    {
        return new HtmlString(
            '<span class="admin-form-label-with-help">'.$label.
            AdminHelp::make($title, $body)->toHtml().
            '</span>',
        );
    }

    private function clearPersistenceErrors(string $field): void
    {
        $prefix = 'data.'.$field;
        foreach ($this->getErrorBag()->keys() as $key) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                $this->resetErrorBag($key);
            }
        }
    }

    private function persistenceFeedbackLabel(string $field): string
    {
        return match ($field) {
            'favicon_media_asset_id' => 'Site icon',
            'contact_recipient_email' => 'Contact form recipient',
            'public_email' => 'Public email',
            'show_public_email' => 'Public email visibility',
            'social_links' => 'Social links',
            'legal_disclaimer' => 'External Links Notice',
            'legal_imprint' => 'Imprint',
            'legal_privacy' => 'Privacy Policy',
            'legal_usage_rights' => 'Copyright & AI Use',
            'default_media_copyright_notice' => 'Default copyright notice',
            'background_mode' => 'Background',
            'background_color' => 'Background color',
            'background_gradient_start' => 'Background gradient start color',
            'background_gradient_end' => 'Background gradient end color',
            'background_gradient_angle' => 'Background gradient angle',
            'public_page_width' => 'Public page width',
            'public_content_padding' => 'Public content padding',
            'navigation_nesting_enabled' => 'Navigation nesting',
            default => str($field)->replace('_', ' ')->headline()->toString(),
        };
    }

    private function normalizePersistenceValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'favicon_media_asset_id' => is_numeric($value) ? (int) $value : null,
            'background_mode' => $value === PublicAppearance::MODE_DEFAULT || $value === '' ? null : $value,
            'background_color', 'background_gradient_start', 'background_gradient_end' => $this->normalizeColorCandidate($value),
            'background_gradient_angle' => is_numeric($value) && (string) (int) $value === trim((string) $value) ? (int) $value : ($value === '' ? null : $value),
            'public_page_width', 'public_content_padding' => is_numeric($value) && (string) (int) $value === trim((string) $value) ? (int) $value : $value,
            'social_links' => $this->normalizeSocialLinks($value),
            'public_email', 'contact_recipient_email' => $value === '' ? null : $value,
            'default_media_copyright_notice' => is_string($value) ? (($trimmed = trim($value)) === '' ? null : $trimmed) : $value,
            'legal_disclaimer', 'legal_imprint', 'legal_privacy', 'legal_usage_rights' => is_string($value) ? (($trimmed = trim($value)) === '' ? null : $trimmed) : $value,
            default => $value,
        };
    }

    private function normalizeColorCandidate(mixed $value): mixed
    {
        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);
        if ($value === '') {
            return null;
        }

        $candidate = strtoupper(str_starts_with($value, '#') ? $value : '#'.$value);

        return preg_match('/^#[0-9A-F]{6}$/', $candidate) === 1 ? $candidate : $value;
    }

    private function normalizeSocialLinks(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        return array_values(array_map(static function (mixed $link): mixed {
            if (! is_array($link)) {
                return $link;
            }

            return [
                'platform' => $link['platform'] ?? null,
                'url' => $link['url'] ?? null,
            ];
        }, $value));
    }

    /** @return array<int, array<string, mixed>> */
    private function socialLinks(): array
    {
        return is_array($this->data['social_links'] ?? null)
            ? array_values(array_filter($this->data['social_links'], 'is_array'))
            : [];
    }

    /** @param array<int, array<string, mixed>> $links */
    private function saveSocialLinks(array $links): void
    {
        $links = array_values($links);

        try {
            $updated = app(AdminSettingsService::class)->updatePublicContent(
                PublicContentSetting::general(),
                ['social_links' => $links],
            );
            $this->settingsRecord = null;

            if ($updated->wasChanged()) {
                app(AdminNotifier::class)->notification(
                    title: 'Social links updated',
                    status: 'success',
                );
            }
        } catch (ValidationException $exception) {
            $mapped = [];
            foreach ($exception->errors() as $key => $messages) {
                if (str_ends_with($key, '.platform')) {
                    $mapped['platform'] = $messages;
                } elseif (str_ends_with($key, '.url')) {
                    $mapped['url'] = $messages;
                }
            }

            throw ValidationException::withMessages(
                $mapped !== [] ? $mapped : ['url' => 'This social profile could not be saved.'],
            );
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages([
                'url' => 'This social profile could not be saved. Please try again.',
            ]);
        }

        $this->data['social_links'] = $links;
    }

    private function socialLinkIndexForAction(array $arguments): ?int
    {
        $index = filter_var($arguments['index'] ?? null, FILTER_VALIDATE_INT);
        $links = $this->socialLinks();

        return $index !== false && isset($links[$index]) ? (int) $index : null;
    }

    private function generalSettingsRecord(): PublicContentSetting
    {
        return $this->settingsRecord ??= PublicContentSetting::general();
    }

    /** @return array<string, string> */
    private static function commitOnEnterAttributes(): array
    {
        return ['x-on:keydown.enter.prevent' => '$event.target.blur()'];
    }
}
