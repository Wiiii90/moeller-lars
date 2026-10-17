<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Domain\Content\SocialLinks;
use App\Filament\Support\AdminAngleControl;
use App\Filament\Support\AdminColorControl;
use App\Filament\Support\AdminHelp;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Toggle;
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
        'menu_background_mode',
        'menu_background_color',
        'menu_background_gradient_start',
        'menu_background_gradient_end',
        'menu_background_gradient_angle',
        'page_background_mode',
        'page_background_color',
        'page_background_gradient_start',
        'page_background_gradient_end',
        'page_background_gradient_angle',
        'background_gradient_stops',
        'menu_background_gradient_stops',
        'page_background_gradient_stops',
        'background_gradient_repeating',
        'menu_background_gradient_repeating',
        'page_background_gradient_repeating',
        'background_gradient_repeat_span',
        'menu_background_gradient_repeat_span',
        'page_background_gradient_repeat_span',
        'background_gradient_center_x',
        'menu_background_gradient_center_x',
        'page_background_gradient_center_x',
        'background_gradient_center_y',
        'menu_background_gradient_center_y',
        'page_background_gradient_center_y',
        'background_gradient_shape',
        'menu_background_gradient_shape',
        'page_background_gradient_shape',
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

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getCurrentPanel());
    }

    public function mount(): void
    {
        $data = $this->generalSettingsRecord()->only(self::PERSISTED_FIELDS);
        foreach (PublicAppearance::SURFACE_PREFIXES as $surface) {
            $mode = $data[$surface.'_mode'] ?? null;
            if ($mode === null || $mode === '' || $mode === PublicAppearance::MODE_DEFAULT) {
                $data[$surface.'_mode'] = PublicAppearance::MODE_SOLID;
                $data[$surface.'_color'] = $surface === 'background'
                    ? PublicAppearance::DEFAULT_PAGE_COLOR
                    : PublicAppearance::DEFAULT_SURFACE_COLOR;
            }
        }

        $data['public_page_width'] = PublicAppearance::normalizePageWidth($data['public_page_width'] ?? null);
        $data['public_content_padding'] = PublicAppearance::normalizeContentPadding($data['public_content_padding'] ?? null);

        foreach (PublicAppearance::SURFACE_PREFIXES as $surface) {
            $data[$surface.'_gradient_stops'] = PublicAppearance::stops($this->generalSettingsRecord(), $surface);
        }

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
                                    'class' => 'general-stage-kicker admin-visual-stage__heading',
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


                            self::appearanceSurfaceControls('background', 'Background'),
                            self::appearanceSurfaceControls('menu_background', 'Menu'),
                            self::appearanceSurfaceControls('page_background', 'Page'),

                            Hidden::make('background_color'),
                            Hidden::make('background_gradient_start'),
                            Hidden::make('background_gradient_end'),
                            Hidden::make('menu_background_color'),
                            Hidden::make('menu_background_gradient_start'),
                            Hidden::make('menu_background_gradient_end'),
                            Hidden::make('page_background_color'),
                            Hidden::make('page_background_gradient_start'),
                            Hidden::make('page_background_gradient_end'),
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
                            'class' => 'general-appearance-stage admin-visual-stage admin-visual-stage--split',
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

    /** Shared editor, reused for all three global surfaces. */
    private static function appearanceSurfaceControls(string $surface, string $label): Group
    {
        $gradient = static fn (callable $get): bool => PublicAppearance::isGradient($get($surface.'_mode'));
        $center = static fn (callable $get): bool => in_array($get($surface.'_mode'), [PublicAppearance::MODE_RADIAL, PublicAppearance::MODE_CONIC], true);

        return Group::make([
            Select::make($surface.'_mode')
                ->label($label)
                ->options([
                    PublicAppearance::MODE_SOLID => 'Solid',
                    PublicAppearance::MODE_GRADIENT => 'Linear gradient',
                    PublicAppearance::MODE_RADIAL => 'Radial gradient',
                    PublicAppearance::MODE_CONIC => 'Conic gradient',
                ])
                ->native()->required()->live()
                ->extraFieldWrapperAttributes(['class' => 'general-background-mode-control'])
                ->afterStateUpdated(fn ($livewire) => $livewire instanceof self ? $livewire->persistSurfaceMode($surface) : null),

            AdminColorControl::make($surface.'_primary_color', 'Color')
                ->lazy()
                ->extraFieldWrapperAttributes(['class' => 'general-color-control general-primary-color-control'])
                ->afterStateUpdated(fn ($livewire, $state) => $livewire instanceof self ? $livewire->persistSurfaceColor($surface, 'primary', $state) : null)
                ->visible(fn (callable $get): bool => $get($surface.'_mode') === PublicAppearance::MODE_SOLID),

            AdminAngleControl::make($surface.'_gradient_angle')
                ->live()
                ->afterStateUpdated(self::persist($surface.'_gradient_angle'))
                ->visible(fn (callable $get): bool => in_array($get($surface.'_mode'), [PublicAppearance::MODE_GRADIENT, PublicAppearance::MODE_CONIC], true)),

            Toggle::make($surface.'_gradient_repeating')
                ->label('Repeating')
                ->live()
                ->afterStateUpdated(self::persist($surface.'_gradient_repeating'))
                ->visible($gradient),

            Select::make($surface.'_gradient_shape')
                ->label('Shape')
                ->options(['circle' => 'Circle', 'ellipse' => 'Ellipse'])
                ->native()->live()
                ->afterStateUpdated(self::persist($surface.'_gradient_shape'))
                ->visible(fn (callable $get): bool => $get($surface.'_mode') === PublicAppearance::MODE_RADIAL),

            TextInput::make($surface.'_gradient_repeat_span')
                ->label('Repeat span')->integer()->minValue(16)->maxValue(360)
                ->suffix(fn (callable $get): string => $get($surface.'_mode') === PublicAppearance::MODE_CONIC ? '°' : 'px')
                ->lazy()
                ->afterStateUpdated(self::persist($surface.'_gradient_repeat_span'))
                ->visible(fn (callable $get): bool => $gradient($get) && (bool) $get($surface.'_gradient_repeating')),

            TextInput::make($surface.'_gradient_center_x')
                ->label('Center X')->integer()->minValue(0)->maxValue(100)->suffix('%')->lazy()
                ->afterStateUpdated(self::persist($surface.'_gradient_center_x'))
                ->visible($center),
            TextInput::make($surface.'_gradient_center_y')
                ->label('Center Y')->integer()->minValue(0)->maxValue(100)->suffix('%')->lazy()
                ->afterStateUpdated(self::persist($surface.'_gradient_center_y'))
                ->visible($center),

            Repeater::make($surface.'_gradient_stops')
                ->label('Color stops')
                ->schema([
                    AdminColorControl::make('color', 'Color')
                        ->default($surface === 'background' ? PublicAppearance::DEFAULT_PAGE_COLOR : PublicAppearance::DEFAULT_SURFACE_COLOR)
                        ->required()->live(onBlur: true)
                        ->afterStateUpdated(fn ($livewire) => $livewire instanceof self ? $livewire->persistSurfaceStops($surface) : null),
                    TextInput::make('position')
                        ->default(100)
                        ->label('Position')->integer()->minValue(0)->maxValue(100)->suffix('%')
                        ->required()->live(onBlur: true)
                        ->afterStateUpdated(fn ($livewire) => $livewire instanceof self ? $livewire->persistSurfaceStops($surface) : null),
                ])
                ->columns(2)->minItems(2)->maxItems(PublicAppearance::MAX_STOPS)
                ->helperText('Color stops are arranged by position (0–100%). Add up to 12 stops.')
                ->addActionLabel('Add color stop')
                ->extraFieldWrapperAttributes(['class' => 'general-gradient-stops-control'])
                ->reorderable(false)->live()
                ->afterStateUpdated(fn ($livewire) => $livewire instanceof self ? $livewire->persistSurfaceStops($surface) : null)
                ->columnSpanFull()->visible($gradient),
        ])
            ->columns(2)
            ->extraAttributes(['class' => 'general-background-row'])
            ->columnSpanFull();
    }

    // Retain the established background editor method contract.
    public function persistAppearanceColor(string $slot, mixed $value): void
    {
        $this->persistSurfaceColor('background', $slot, $value);
    }

    public function persistAppearanceMode(): void
    {
        $this->persistSurfaceMode('background');
    }

    public function persistSurfaceColor(string $surface, string $slot, mixed $value): void
    {
        if (! is_array($this->data) || ! in_array($surface, PublicAppearance::SURFACE_PREFIXES, true)) {
            return;
        }

        $mode = $this->data[$surface.'_mode'] ?? PublicAppearance::MODE_SOLID;
        $field = match ($slot) {
            'primary' => $mode === PublicAppearance::MODE_GRADIENT ? $surface.'_gradient_start' : $surface.'_color',
            'secondary' => $mode === PublicAppearance::MODE_GRADIENT ? $surface.'_gradient_end' : null,
            default => null,
        };
        if ($field === null) {
            return;
        }

        $alias = $surface.($slot === 'primary' ? '_primary_color' : '_secondary_color');
        $this->resetErrorBag('data.'.$alias);
        $this->data[$field] = $value;
        $fields = $mode === PublicAppearance::MODE_SOLID ? [$surface.'_mode', $field] : [$field];
        $this->persistChangedFields($fields);

        foreach ($this->getErrorBag()->get('data.'.$field) as $message) {
            $this->addError('data.'.$alias, $message);
        }

        $this->syncAppearanceControlState();
    }

    public function persistSurfaceStops(string $surface): void
    {
        if (in_array($surface, PublicAppearance::SURFACE_PREFIXES, true)) {
            $this->persistChangedField($surface.'_gradient_stops');
        }
    }

    public function persistSurfaceMode(string $surface): void
    {
        if (! is_array($this->data) || ! in_array($surface, PublicAppearance::SURFACE_PREFIXES, true)) {
            return;
        }

        $fields = [$surface.'_mode'];
        if (($this->data[$surface.'_mode'] ?? null) === PublicAppearance::MODE_SOLID) {
            $fields[] = $surface.'_color';
        }
        $this->persistChangedFields($fields);
        $this->syncAppearanceControlState();
    }

    public function syncAppearanceControlState(): void
    {
        if (! is_array($this->data)) {
            return;
        }

        foreach (PublicAppearance::SURFACE_PREFIXES as $surface) {
            $default = $surface === 'background' ? PublicAppearance::DEFAULT_PAGE_COLOR : PublicAppearance::DEFAULT_SURFACE_COLOR;
            $mode = $this->data[$surface.'_mode'] ?? PublicAppearance::MODE_SOLID;
            $this->data[$surface.'_primary_color'] = $mode === PublicAppearance::MODE_GRADIENT
                ? ($this->data[$surface.'_gradient_start'] ?? $default)
                : ($this->data[$surface.'_color'] ?? $default);
            $this->data[$surface.'_secondary_color'] = $this->data[$surface.'_gradient_end'] ?? null;
        }
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
                    $this->throwSocialDialogValidation([
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
                $this->throwSocialDialogValidation([
                    'platform' => 'This social profile no longer exists. Reopen the editor.',
                ]);
            }
            $platform = (string) ($data['platform'] ?? '');
            $url = (string) ($data['url'] ?? '');

            foreach ($links as $otherIndex => $link) {
                if ($otherIndex !== $index && ($link['platform'] ?? null) === $platform) {
                    $this->throwSocialDialogValidation([
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
        $this->persistChangedFields([$field]);
    }

    /** @param list<string> $fields */
    private function persistChangedFields(array $fields): void
    {
        if (! is_array($this->data)) {
            return;
        }

        $record = PublicContentSetting::general();
        $changes = [];
        foreach ($fields as $field) {
            if (! in_array($field, self::PERSISTED_FIELDS, true) || ! array_key_exists($field, $this->data)) {
                continue;
            }
            $this->clearPersistenceErrors($field);
            $candidate = $this->normalizePersistenceValue($field, $this->data[$field]);
            if ($candidate !== $this->normalizePersistenceValue($field, $record->getAttribute($field))) {
                $changes[$field] = $candidate;
            }
        }

        if ($changes === []) {
            return;
        }

        try {
            $updated = app(AdminSettingsService::class)->updatePublicContent($record, $changes);
            $this->settingsRecord = null;
            if (! $updated->wasChanged()) {
                return;
            }
            foreach (array_keys($changes) as $field) {
                $this->data[$field] = $updated->getAttribute($field);
            }
            if (array_intersect(array_keys($changes), [
                'favicon_media_asset_id',
                'background_mode',
                'background_color',
                'background_gradient_start',
                'background_gradient_end',
                'background_gradient_angle',
                'menu_background_mode',
                'menu_background_color',
                'menu_background_gradient_start',
                'menu_background_gradient_end',
                'menu_background_gradient_angle',
                'page_background_mode',
                'page_background_color',
                'page_background_gradient_start',
                'page_background_gradient_end',
                'page_background_gradient_angle',
                'background_gradient_stops',
                'background_gradient_repeating',
                'background_gradient_repeat_span',
                'background_gradient_center_x',
                'background_gradient_center_y',
                'background_gradient_shape',
                'menu_background_gradient_stops',
                'menu_background_gradient_repeating',
                'menu_background_gradient_repeat_span',
                'menu_background_gradient_center_x',
                'menu_background_gradient_center_y',
                'menu_background_gradient_shape',
                'page_background_gradient_stops',
                'page_background_gradient_repeating',
                'page_background_gradient_repeat_span',
                'page_background_gradient_center_x',
                'page_background_gradient_center_y',
                'page_background_gradient_shape',
                'public_page_width',
                'public_content_padding',
            ]) !== []) {
                $this->dispatch('general-appearance-updated');
            }

            app(AdminNotifier::class)->notification(
                'Changes saved',
                implode(', ', array_map($this->persistenceFeedbackLabel(...), array_keys($changes))).' updated.',
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
            foreach (array_keys($changes) as $field) {
                $this->addError('data.'.$field, $message);
            }
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
            'menu_background_mode' => 'Menu',
            'page_background_mode' => 'Page',
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
            'background_mode', 'menu_background_mode', 'page_background_mode' => $value === PublicAppearance::MODE_DEFAULT || $value === '' ? null : $value,
            'background_color', 'background_gradient_start', 'background_gradient_end',
            'menu_background_color', 'menu_background_gradient_start', 'menu_background_gradient_end',
            'page_background_color', 'page_background_gradient_start', 'page_background_gradient_end' => $this->normalizeColorCandidate($value),
            'background_gradient_angle', 'menu_background_gradient_angle', 'page_background_gradient_angle' => is_numeric($value) && (string) (int) $value === trim((string) $value) ? (int) $value : ($value === '' ? null : $value),
            'background_gradient_stops', 'menu_background_gradient_stops', 'page_background_gradient_stops' =>
                is_array($value) ? array_map(static fn (mixed $stop): mixed => is_array($stop)
                    ? ['color' => is_string($stop['color'] ?? null) ? strtoupper($stop['color']) : ($stop['color'] ?? null),
                       'position' => is_numeric($stop['position'] ?? null) ? (int) $stop['position'] : ($stop['position'] ?? null)]
                    : $stop, array_values($value)) : $value,
            'background_gradient_repeating', 'menu_background_gradient_repeating', 'page_background_gradient_repeating' => (bool) $value,
            'background_gradient_repeat_span', 'menu_background_gradient_repeat_span', 'page_background_gradient_repeat_span',
            'background_gradient_center_x', 'menu_background_gradient_center_x', 'page_background_gradient_center_y',
            'background_gradient_center_y', 'menu_background_gradient_center_y', 'page_background_gradient_center_x' =>
                is_numeric($value) ? (int) $value : $value,
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
            $record = PublicContentSetting::general();
            if ($this->normalizeSocialLinks($record->social_links) === $this->normalizeSocialLinks($links)) {
                $this->data['social_links'] = $links;

                return;
            }
            $updated = app(AdminSettingsService::class)->updatePublicContent(
                $record,
                ['social_links' => $links],
                ['social_links' => $this->data['social_links'] ?? null],
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
                } elseif ($key === 'social_links') {
                    $mapped['platform'] = $messages;
                } elseif (str_ends_with($key, '.url')) {
                    $mapped['url'] = $messages;
                }
            }

            $this->throwSocialDialogValidation(
                $mapped !== [] ? $mapped : ['url' => 'This social profile could not be saved.'],
            );
        } catch (Throwable $exception) {
            report($exception);
            $this->throwSocialDialogValidation([
                'url' => 'This social profile could not be saved. Please try again.',
            ]);
        }

        $this->data['social_links'] = $links;
    }

    /** @param array<string, string|list<string>> $errors */
    private function throwSocialDialogValidation(array $errors): never
    {
        $statePath = $this->getMountedActionSchema()->getStatePath();
        $mapped = [];
        foreach ($errors as $field => $messages) {
            $mapped[$statePath.'.'.$field] = $messages;
        }
        throw ValidationException::withMessages($mapped);
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
