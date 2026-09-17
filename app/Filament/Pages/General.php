<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Domain\Content\SocialLinks;
use App\Filament\Support\AdminColorControl;
use App\Filament\Support\AdminHelp;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\Dialogs\InteractsWithAdminEditDialogAutosave;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
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
    use InteractsWithAdminEditDialogAutosave;

    private const PERSISTED_FIELDS = [
        'favicon_media_asset_id',
        'background_mode',
        'background_color',
        'background_gradient_start',
        'background_gradient_end',
        'background_gradient_angle',
        'public_email',
        'contact_recipient_email',
        'social_links',
        'default_media_copyright_notice',
        'legal_disclaimer',
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

    public function mount(): void
    {
        $data = $this->generalSettingsRecord()->only(self::PERSISTED_FIELDS);
        $mode = $data['background_mode'] ?? null;
        if ($mode === null || $mode === '') {
            $data['background_mode'] = PublicAppearance::MODE_DEFAULT;
        }

        $this->data = $data;
        $this->syncAppearanceControlState();
        $this->form->fill($this->data);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        return ['generalSettings' => $this->generalSettingsRecord()];
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
                                ->placeholder('Choose from Media Files')
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
                                    ->options(PublicAppearance::modeOptions())
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
                                    ->disabled(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_DEFAULT)
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

                        Group::make([
                            View::make('filament.schemas.components.general-layout-controls')
                                ->columnSpanFull(),
                        ])
                            ->columns(1)
                            ->extraAttributes(['class' => 'general-appearance-stage__geometry'])
                            ->columnSpanFull(),
                    ])
                        ->columns(3)
                        ->extraAttributes(fn ($livewire): array => [
                            'class' => 'general-appearance-stage admin-visual-stage admin-visual-stage--stackable',
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

                View::make('filament.schemas.components.general-separator')
                    ->columnSpanFull(),

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

                View::make('filament.schemas.components.general-separator')
                    ->columnSpanFull(),

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
                        ->extraInputAttributes(self::commitOnEnterAttributes())
                        ->afterStateUpdated(self::persist('default_media_copyright_notice')),
                    TextInput::make('legal_disclaimer')
                        ->label(self::legalDisclaimerLabel())
                        ->nullable()
                        ->lazy()
                        ->extraInputAttributes(self::commitOnEnterAttributes())
                        ->afterStateUpdated(self::persist('legal_disclaimer')),
                ])
                    ->columns(2)
                    ->extraAttributes(['class' => 'general-two-column-settings general-legal-settings'])
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

        $mode = $this->data['background_mode'] ?? PublicAppearance::MODE_DEFAULT;
        $field = match ($slot) {
            'primary' => $mode === PublicAppearance::MODE_GRADIENT ? 'background_gradient_start' : 'background_color',
            'secondary' => $mode === PublicAppearance::MODE_GRADIENT ? 'background_gradient_end' : null,
            default => null,
        };

        if ($field === null || $mode === PublicAppearance::MODE_DEFAULT) {
            return;
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

        $mode = $this->data['background_mode'] ?? PublicAppearance::MODE_DEFAULT;
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
                ->modalHeading('Add social media profile')
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
            'Add social media profile',
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

    public function editSocialLinkAction(): Action
    {
        return AdminDialog::edit(Action::make('editSocialLink')
            ->label('Edit')
            ->modalHeading('Edit social media profile')
            ->fillForm(function (array $arguments): array {
                $index = $this->socialLinkIndexForAction($arguments);
                $link = $this->socialLinkForAction($arguments);

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
            ]), AdminDialogSize::Small)->action(function (array $data, array $arguments): void {
                $index = $this->socialLinkIndexForAction($arguments);
                if ($index === null) {
                    return;
                }

                $links = $this->socialLinks();
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
            });
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
            ], true)) {
                $this->dispatch('general-appearance-updated');
            }
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $errorKey = str_starts_with($key, 'data.') ? $key : 'data.'.$key;
                foreach ($messages as $message) {
                    $this->addError($errorKey, $message);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('data.'.$field, 'This setting could not be saved. Please try again.');
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

    private static function persist(string $field): \Closure
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
            'Used as the inherited copyright notice for media assets. Individual media can override this default or explicitly use no notice.',
        );
    }

    private static function legalDisclaimerLabel(): HtmlString
    {
        return self::helpLabel(
            'Legal disclaimer',
            'About the legal disclaimer',
            'Reusable legal text rendered wherever a Custom Page includes the Legal disclaimer component.',
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

    private function normalizePersistenceValue(string $field, mixed $value): mixed
    {
        return match ($field) {
            'favicon_media_asset_id' => is_numeric($value) ? (int) $value : null,
            'background_mode' => $value === PublicAppearance::MODE_DEFAULT || $value === '' ? null : $value,
            'background_color', 'background_gradient_start', 'background_gradient_end' => $this->normalizeColorCandidate($value),
            'background_gradient_angle' => is_numeric($value) && (string) (int) $value === trim((string) $value) ? (int) $value : ($value === '' ? null : $value),
            'social_links' => $this->normalizeSocialLinks($value),
            'public_email', 'contact_recipient_email' => $value === '' ? null : $value,
            'default_media_copyright_notice' => is_string($value) ? (($trimmed = trim($value)) === '' ? null : $trimmed) : $value,
            'legal_disclaimer' => is_string($value) && trim($value) === '' ? null : $value,
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
            app(AdminSettingsService::class)->updatePublicContent(
                PublicContentSetting::general(),
                ['social_links' => $links],
            );
            $this->settingsRecord = null;
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

    /** @return array<string, mixed> */
    private function socialLinkForAction(array $arguments): array
    {
        $index = $this->socialLinkIndexForAction($arguments);
        if ($index === null) {
            return [];
        }

        $links = $this->socialLinks();

        return $links[$index] ?? [];
    }

    private function socialLinkIndexForAction(array $arguments): ?int
    {
        $candidate = $arguments['index'] ?? null;
        $index = filter_var($candidate, FILTER_VALIDATE_INT);
        if ($index === false || ! isset($this->data['social_links'][$index]) || ! is_array($this->data['social_links'][$index])) {
            return null;
        }

        return (int) $index;
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
