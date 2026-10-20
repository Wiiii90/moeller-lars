<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Domain\Content\RegionalFormats;
use App\Domain\Content\SocialLinks;
use App\Filament\Support\AdminAngleControl;
use App\Filament\Support\AdminColorControl;
use App\Filament\Support\AdminHelp;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\AdminTableRepeater;
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
use Filament\Forms\Components\Repeater\TableColumn;
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
        'frame_style',
        'menu_type',
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
        'header_name_visible',
        'header_name_text',
        'header_name_alignment',
        'header_name_mobile_alignment',
        'header_name_position',
        'header_name_caps',
        'header_name_color',
        'header_name_mode',
        'header_name_gradient_start',
        'header_name_gradient_end',
        'header_name_gradient_angle',
        'header_name_gradient_stops',
        'header_name_gradient_repeating',
        'header_name_gradient_repeat_span',
        'header_name_gradient_center_x',
        'header_name_gradient_center_y',
        'header_name_gradient_shape',
        'header_name_size',
        'header_name_min_size',
        'header_divider_mode',
        'header_divider_color',
        'header_divider_paint_mode',
        'header_divider_paint_gradient_start',
        'header_divider_paint_gradient_end',
        'header_divider_paint_gradient_angle',
        'header_divider_paint_gradient_stops',
        'header_divider_paint_gradient_repeating',
        'header_divider_paint_gradient_repeat_span',
        'header_divider_paint_gradient_center_x',
        'header_divider_paint_gradient_center_y',
        'header_divider_paint_gradient_shape',
        'header_name_auto_scale',
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
        'regional_format',
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
                if (! in_array($surface, ['header_name', 'header_divider_paint'], true)) {
                    $data[$surface.'_color'] = $surface === 'background'
                        ? PublicAppearance::DEFAULT_PAGE_COLOR
                        : PublicAppearance::DEFAULT_SURFACE_COLOR;
                }
            }
        }

        $data['frame_style'] = PublicAppearance::frameStyle($data['frame_style'] ?? null);
        $data['menu_type'] = PublicAppearance::menuType($data['menu_type'] ?? null);
        $data['header_name_visible'] = $data['header_name_visible'] === null ? true : (bool) $data['header_name_visible'];
        $data['header_name_text'] = PublicAppearance::headerText($data['header_name_text'] ?? null);
        $data['header_name_alignment'] = PublicAppearance::presentedHeaderAlignment($data['header_name_alignment'] ?? null, $data['header_name_position'] ?? null);
        $data['header_name_mobile_alignment'] = PublicAppearance::headerAlignment($data['header_name_mobile_alignment'] ?? null, 'header_name_mobile_alignment');
        $data['header_name_position'] = PublicAppearance::headerPosition($data['header_name_position'] ?? null);
        $data['header_name_caps'] = PublicAppearance::headerCaps($data['header_name_caps'] ?? null);
        $data['header_name_color'] = PublicAppearance::normalizeColor($data['header_name_color'] ?? null, 'header_name_color');
        $data['header_name_mode'] ??= PublicAppearance::MODE_SOLID;
        $data['header_name_size'] = PublicAppearance::normalizeTitleSize($data['header_name_size'] ?? null);
        $data['header_name_min_size'] = PublicAppearance::normalizeTitleMinSize($data['header_name_min_size'] ?? null);
        $data['header_divider_mode'] = PublicAppearance::headerDivider($data['header_divider_mode'] ?? null);
        $data['header_divider_color'] = PublicAppearance::normalizeColor($data['header_divider_color'] ?? null, 'header_divider_color');
        $data['header_name_auto_scale'] = $data['header_name_auto_scale'] === null ? true : (bool) $data['header_name_auto_scale'];
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

                            View::make('filament.schemas.components.general-appearance-switcher')
                                ->viewData(fn ($livewire): array => ['generalPage' => $livewire])
                                ->extraAttributes(['class' => 'general-appearance-stage__toolbar'])
                                ->columnSpanFull(),

                            Group::make([
                            Group::make([
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

                                Select::make('frame_style')
                                    ->label('Frame style')
                                    ->options(PublicAppearance::FRAME_STYLES)
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(self::persist('frame_style')),
                                self::appearanceSurfaceControls('background', 'Background'),
                            ])
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'admin-stage-panel-content general-appearance-stage__panel',
                                    'data-appearance-panel' => 'global',
                                    'x-bind:class' => "{ 'is-active': appearanceTab === 'global' }",
                                    'x-bind:inert' => "appearanceTab !== 'global'",
                                    'x-bind:aria-hidden' => "(appearanceTab !== 'global').toString()",
                                ])
                                ->columnSpanFull(),

                            Group::make([
                                Group::make([
                                Select::make('header_name_visible')
                                    ->label('Title')
                                    ->options(['1' => 'Show', '0' => 'Hide'])
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->formatStateUsing(static fn (mixed $state): string => $state === null || (bool) $state ? '1' : '0')
                                    ->afterStateUpdated(self::persist('header_name_visible')),
                                TextInput::make('header_name_text')
                                    ->label('Text')
                                    ->required()
                                    ->maxLength(100)
                                    ->lazy()
                                    ->visible(fn (callable $get): bool => (bool) $get('header_name_visible'))
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('header_name_text')),
                                ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                                Group::make([
                                Select::make('header_name_position')
                                    ->label('Position')
                                    ->options(PublicAppearance::HEADER_POSITIONS)
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(function ($livewire): void {
                                        if (! $livewire instanceof self) {
                                            return;
                                        }

                                        // Inside Menu has no center slot. Normalize an existing
                                        // centered selection along with Position in one save.
                                        if (($livewire->data['header_name_position'] ?? null) === 'inside'
                                            && ($livewire->data['header_name_alignment'] ?? null) === 'center') {
                                            $livewire->data['header_name_alignment'] = 'left';
                                            $livewire->persistChangedFields(['header_name_position', 'header_name_alignment']);

                                            return;
                                        }

                                        $livewire->persistChangedField('header_name_position');
                                    }),
                                Select::make('header_name_caps')
                                    ->label('Lettering')
                                    ->options(PublicAppearance::HEADER_CAPS)
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(self::persist('header_name_caps')),
                            ])
                                ->columns(2)
                                ->visible(fn (callable $get): bool => (bool) $get('header_name_visible'))
                                ->columnSpanFull(),

                            Group::make([
                                Select::make('header_name_alignment')
                                    ->label('Alignment')
                                    ->options(fn (callable $get): array => PublicAppearance::headerAlignmentOptions((string) $get('header_name_position')))
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(self::persist('header_name_alignment')),
                                Select::make('header_name_mobile_alignment')
                                    ->label('Responsive alignment')
                                    ->options(PublicAppearance::HEADER_ALIGNMENT)
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(self::persist('header_name_mobile_alignment')),
                            ])
                                ->columns(2)
                                ->visible(fn (callable $get): bool => (bool) $get('header_name_visible'))
                                ->columnSpanFull(),

                            Group::make([
                                TextInput::make('header_name_size')
                                    ->label('Size')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(PublicAppearance::MIN_TITLE_SIZE)
                                    ->maxValue(PublicAppearance::MAX_TITLE_SIZE)
                                    ->step(1)
                                    ->suffix('px')
                                    ->required()
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('header_name_size')),
                                Select::make('header_name_auto_scale')
                                    ->label('Responsive size')
                                    ->options(['1' => 'Allow reduction', '0' => 'Keep selected size'])
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->formatStateUsing(static fn (mixed $state): string => $state === null || (bool) $state ? '1' : '0')
                                    ->afterStateUpdated(self::persist('header_name_auto_scale')),
                                TextInput::make('header_name_min_size')
                                    ->label('Minimum size')
                                    ->numeric()
                                    ->integer()
                                    ->minValue(PublicAppearance::MIN_RESPONSIVE_TITLE_SIZE)
                                    ->maxValue(PublicAppearance::MAX_TITLE_SIZE)
                                    ->suffix('px')
                                    ->required()
                                    ->lazy()
                                    ->visible(fn (callable $get): bool => (bool) $get('header_name_auto_scale'))
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('header_name_min_size')),
                            ])
                                ->columns(2)
                                ->visible(fn (callable $get): bool => (bool) $get('header_name_visible'))
                                ->columnSpanFull(),
                            self::appearanceSurfaceControls('header_name', 'Title color')
                                ->visible(fn (callable $get): bool => (bool) $get('header_name_visible')),

                            ])
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'admin-stage-panel-content general-appearance-stage__panel',
                                    'data-appearance-panel' => 'title',
                                    'x-bind:class' => "{ 'is-active': appearanceTab === 'title' }",
                                    'x-bind:inert' => "appearanceTab !== 'title'",
                                    'x-bind:aria-hidden' => "(appearanceTab !== 'title').toString()",
                                ])
                                ->columnSpanFull(),

                            Group::make([
                                Group::make([
                                Select::make('menu_type')
                                    ->label('Menu type')
                                    ->options(PublicAppearance::MENU_TYPES)
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(self::persist('menu_type')),
                                Select::make('header_divider_mode')
                                    ->label('Menu to page')
                                    ->options(PublicAppearance::HEADER_DIVIDERS)
                                    ->native()
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(self::persist('header_divider_mode')),
                                ])
                                    ->columns(2)
                                    ->columnSpanFull(),
                                self::appearanceSurfaceControls('menu_background', 'Menu background'),
                                self::appearanceSurfaceControls('header_divider_paint', 'Divider color'),
                            ])
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'admin-stage-panel-content general-appearance-stage__panel',
                                    'data-appearance-panel' => 'menu',
                                    'x-bind:class' => "{ 'is-active': appearanceTab === 'menu' }",
                                    'x-bind:inert' => "appearanceTab !== 'menu'",
                                    'x-bind:aria-hidden' => "(appearanceTab !== 'menu').toString()",
                                ])
                                ->columnSpanFull(),

                            Group::make([
                                Group::make([
                                TextInput::make('public_page_width')
                                    ->label('Content width')
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

                                self::appearanceSurfaceControls('page_background', 'Page background'),
                            ])
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'admin-stage-panel-content general-appearance-stage__panel',
                                    'data-appearance-panel' => 'page',
                                    'x-bind:class' => "{ 'is-active': appearanceTab === 'page' }",
                                    'x-bind:inert' => "appearanceTab !== 'page'",
                                    'x-bind:aria-hidden' => "(appearanceTab !== 'page').toString()",
                                ])
                                ->columnSpanFull(),

                            Hidden::make('background_gradient_start'),
                            Hidden::make('background_gradient_end'),
                            Hidden::make('menu_background_gradient_start'),
                            Hidden::make('menu_background_gradient_end'),
                            Hidden::make('page_background_gradient_start'),
                            Hidden::make('page_background_gradient_end'),
                            ])
                                ->columns(1)
                                ->extraAttributes([
                                    'class' => 'general-appearance-stage__scroll',
                                    'x-ref' => 'appearanceScroll',
                                ])
                                ->columnSpanFull(),
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
                            'x-data' => "{ appearanceTab: 'global', moreSpace: false }",
                            'x-bind:class' => "{ 'admin-visual-stage--expanded': moreSpace }",
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

                View::make('filament.schemas.components.general-separator')->columnSpanFull(),

                Group::make([
                    View::make('filament.schemas.components.admin-section-heading')
                        ->viewData(['label' => 'Regional Formats', 'class' => 'general-form-section-heading'])
                        ->columnSpanFull(),
                    Select::make('regional_format')
                        ->label('Dates and numbers')
                        ->options(RegionalFormats::OPTIONS)
                        ->required()
                        ->live()
                        ->afterStateUpdated(self::persist('regional_format'))
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

    /** Shared editor for all five global paints, including Title and Divider. */
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
                    ...($surface === 'page_background' ? [PublicAppearance::MODE_TRANSPARENT => 'Transparent'] : []),
                ])
                ->native()->required()->live()
                ->extraFieldWrapperAttributes(['class' => 'general-background-mode-control'])
                ->afterStateUpdated(fn ($livewire) => $livewire instanceof self ? $livewire->persistSurfaceMode($surface) : null),

            Select::make($surface.'_gradient_repeating')
                ->label('Repeating')
                ->options(['0' => 'Off', '1' => 'On'])
                ->formatStateUsing(static fn (mixed $state): string => (bool) $state ? '1' : '0')
                ->native()
                ->live()
                ->extraFieldWrapperAttributes(['class' => 'general-background-mode-control'])
                ->afterStateUpdated(function ($livewire, mixed $state) use ($surface): void {
                    if ($livewire instanceof self) {
                        $livewire->persistChangedField($surface.'_gradient_repeating');
                        $livewire->data[$surface.'_gradient_repeating'] = (bool) $state ? '1' : '0';
                    }
                })
                ->visible($gradient),

            AdminAngleControl::make($surface.'_gradient_angle')
                ->live()
                ->afterStateUpdated(self::persist($surface.'_gradient_angle'))
                ->visible(fn (callable $get): bool => in_array($get($surface.'_mode'), [PublicAppearance::MODE_GRADIENT, PublicAppearance::MODE_CONIC], true)),

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

            AdminColorControl::make(PublicAppearance::solidColorField($surface), 'Color')
                ->required()
                ->formatStateUsing(static fn (mixed $state): string => filled($state) ? (string) $state : match ($surface) {
                    'background' => PublicAppearance::DEFAULT_PAGE_COLOR,
                    'header_name' => PublicAppearance::DEFAULT_TITLE_GRADIENT_COLOR,
                    'header_divider_paint' => PublicAppearance::DEFAULT_DIVIDER_COLOR,
                    default => PublicAppearance::DEFAULT_SURFACE_COLOR,
                })
                // Filament's picker only commits onBlur after focus/popup close.
                // Live mode persists a settled color selection without requiring
                // the artist to dismiss the picker before leaving this page.
                ->live()
                ->afterStateUpdated(static function ($livewire, mixed $state) use ($surface): void {
                    if ($livewire instanceof self) {
                        $livewire->persistSurfaceColor($surface, 'primary', $state);
                    }
                })
                ->visible(fn (callable $get): bool => $get($surface.'_mode') === PublicAppearance::MODE_SOLID),

            self::appearanceColorsTable($surface)->visible($gradient),
        ])
            ->columns(2)
            ->extraAttributes(['class' => 'general-background-row'])
            ->columnSpanFull();
    }

    /** Gradient color stops use the shared AdminTableRepeater; Solid is one inline field. */
    private static function appearanceColorsTable(string $surface): AdminTableRepeater
    {
        return AdminTableRepeater::make($surface.'_gradient_stops')
            ->label('Colors')
            ->hiddenLabel()
            ->table([TableColumn::make('Color'), TableColumn::make('Position')])
            ->compact()
            ->schema([
                AdminColorControl::make('color', 'Color')
                    ->hiddenLabel()
                    ->required()
                    ->live(),
                TextInput::make('position')
                    ->label('Position')
                    ->hiddenLabel()
                    ->integer()
                    ->minValue(0)
                    ->maxValue(100)
                    ->default(50)
                    ->suffix('%')
                    ->required()
                    ->live(onBlur: true),
            ])
            ->minItems(2)
            ->maxItems(PublicAppearance::MAX_STOPS)
            ->defaultItems(2)
            ->reorderable(false)
            ->cloneable(false)
            ->collapsible(false)
            ->addable()
            ->deletable()
            ->live()
            ->afterStateUpdated(function ($livewire) use ($surface): void {
                if ($livewire instanceof self) {
                    $livewire->persistAppearanceTable($surface);
                }
            })
            ->addActionLabel('Add color')
            ->extraFieldWrapperAttributes(['class' => 'general-color-table'])
            ->columnSpanFull();
    }

    public function persistAppearanceTable(string $surface): void
    {
        if (! is_array($this->data) || ! in_array($surface, PublicAppearance::SURFACE_PREFIXES, true)) {
            return;
        }

        $entries = $this->data[$surface.'_gradient_stops'] ?? null;
        if (! is_array($entries)) {
            return;
        }

        $this->saveAppearanceStops($surface, array_values($entries));
        $this->syncAppearanceControlState();
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
        $isGradient = PublicAppearance::isGradient($mode);
        $alias = $surface.($slot === 'primary' ? '_primary_color' : '_secondary_color');
        if (! in_array($slot, ['primary', 'secondary'], true) || (! $isGradient && $slot !== 'primary')) {
            return;
        }
        $this->resetErrorBag('data.'.$alias);

        if ($isGradient && is_array($this->generalSettingsRecord()->getAttribute($surface.'_gradient_stops'))) {
            $stops = PublicAppearance::stops($this->generalSettingsRecord(), $surface);
            $index = $slot === 'primary' ? 0 : count($stops) - 1;
            $stops[$index]['color'] = $value;
            $this->data[$surface.'_gradient_stops'] = $stops;
            $field = $surface.'_gradient_stops';
            $this->persistChangedField($field);
        } else {
            $field = $isGradient
                ? $surface.($slot === 'primary' ? '_gradient_start' : '_gradient_end')
                : PublicAppearance::solidColorField($surface);
            $this->data[$field] = $value;
            $this->persistChangedFields($isGradient ? [$field] : [$surface.'_mode', $field]);
        }

        foreach ($this->getErrorBag()->get('data.'.$field) as $message) {
            $this->addError('data.'.$alias, $message);
        }
        $this->syncAppearanceControlState();
    }

    public function persistSurfaceMode(string $surface): void
    {
        if (! is_array($this->data) || ! in_array($surface, PublicAppearance::SURFACE_PREFIXES, true)) {
            return;
        }

        $fields = [$surface.'_mode'];
        if (($this->data[$surface.'_mode'] ?? null) === PublicAppearance::MODE_SOLID) {
            $solidField = PublicAppearance::solidColorField($surface);
            // Legacy dividers may have inherited a CSS default rather than a
            // stored color. Only an explicit switch to Solid materializes the
            // visible fallback; simply opening General must not change it.
            if ($surface === 'header_divider_paint' && blank($this->data[$solidField] ?? null)) {
                $this->data[$solidField] = PublicAppearance::DEFAULT_DIVIDER_COLOR;
            }
            $fields[] = $solidField;
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
            $default = match ($surface) {
                'background' => PublicAppearance::DEFAULT_PAGE_COLOR,
                'header_name' => $this->data['header_name_color'] ?? PublicAppearance::DEFAULT_TITLE_GRADIENT_COLOR,
                'header_divider_paint' => $this->data['header_divider_color'] ?? PublicAppearance::DEFAULT_DIVIDER_COLOR,
                default => PublicAppearance::DEFAULT_SURFACE_COLOR,
            };
            $mode = $this->data[$surface.'_mode'] ?? PublicAppearance::MODE_SOLID;
            if (PublicAppearance::isGradient($mode)) {
                $stops = PublicAppearance::stops($this->generalSettingsRecord(), $surface);
                $this->data[$surface.'_primary_color'] = $stops[0]['color'];
                $this->data[$surface.'_secondary_color'] = $stops[count($stops) - 1]['color'];
            } else {
                $this->data[$surface.'_primary_color'] = $this->data[PublicAppearance::solidColorField($surface)] ?? $default;
                $this->data[$surface.'_secondary_color'] = null;
            }
        }
    }

    /** @return list<array{color:string,position:int}> */
    public function appearanceStops(string $surface): array
    {
        abort_unless(in_array($surface, PublicAppearance::SURFACE_PREFIXES, true), 404);

        return PublicAppearance::stops(PublicContentSetting::general(), $surface);
    }

    /** @param list<array{color:mixed,position:mixed}> $stops */
    private function saveAppearanceStops(string $surface, array $stops): void
    {
        $validated = PublicAppearance::normalizeStops($stops, $surface.'_gradient_stops');
        $this->data[$surface.'_gradient_stops'] = $validated;
        $this->persistChangedField($surface.'_gradient_stops');
        $this->syncAppearanceControlState();
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
                'frame_style',
                'menu_type',
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
                'header_name_mode',
                'header_name_gradient_start',
                'header_name_gradient_end',
                'header_name_gradient_angle',
                'header_name_gradient_stops',
                'header_name_gradient_repeating',
                'header_name_gradient_repeat_span',
                'header_name_gradient_center_x',
                'header_name_gradient_center_y',
                'header_name_gradient_shape',
                'header_name_visible',
                'header_name_text',
                'header_name_alignment',
                'header_name_mobile_alignment',
                'header_name_position',
                'header_name_caps',
                'header_name_color',
                'header_name_size',
                'header_name_min_size',
                'header_divider_mode',
                'header_divider_color',
                'header_divider_paint_mode',
                'header_divider_paint_gradient_start',
                'header_divider_paint_gradient_end',
                'header_divider_paint_gradient_angle',
                'header_divider_paint_gradient_stops',
                'header_divider_paint_gradient_repeating',
                'header_divider_paint_gradient_repeat_span',
                'header_divider_paint_gradient_center_x',
                'header_divider_paint_gradient_center_y',
                'header_divider_paint_gradient_shape',
                'header_name_auto_scale',
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
            'frame_style' => 'Frame style',
            'menu_type' => 'Menu type',
            'contact_recipient_email' => 'Contact form recipient',
            'public_email' => 'Public email',
            'show_public_email' => 'Public email visibility',
            'social_links' => 'Social links',
            'legal_disclaimer' => 'External Links Notice',
            'legal_imprint' => 'Imprint',
            'legal_privacy' => 'Privacy Policy',
            'legal_usage_rights' => 'Copyright & AI Use',
            'regional_format' => 'Public regional formats',
            'default_media_copyright_notice' => 'Default copyright notice',
            'background_mode' => 'Background',
            'background_color' => 'Background color',
            'background_gradient_start' => 'Background gradient start color',
            'background_gradient_end' => 'Background gradient end color',
            'background_gradient_angle' => 'Background gradient angle',
            'menu_background_mode' => 'Menu',
            'page_background_mode' => 'Page',
            'header_name_visible' => 'Artist title visibility',
            'header_name_text' => 'Artist title text',
            'header_name_alignment' => 'Title alignment',
            'header_name_mobile_alignment' => 'Narrow-screen title alignment',
            'header_name_position' => 'Artist name position',
            'header_name_caps' => 'Artist name lettering',
            'header_name_color' => 'Artist name color',
            'header_name_mode' => 'Artist title paint',
            'header_name_size' => 'Artist name size',
            'header_name_min_size' => 'Minimum responsive name size',
            'header_divider_mode' => 'Menu and page divider',
            'header_divider_color' => 'Divider color',
            'header_divider_paint_mode' => 'Divider color mode',
            'header_name_auto_scale' => 'Responsive name size',
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
            'background_mode', 'menu_background_mode', 'page_background_mode', 'header_name_mode', 'header_divider_paint_mode' => $value === PublicAppearance::MODE_DEFAULT || $value === '' ? null : $value,
            'background_color', 'background_gradient_start', 'background_gradient_end',
            'menu_background_color', 'menu_background_gradient_start', 'menu_background_gradient_end',
            'page_background_color', 'page_background_gradient_start', 'page_background_gradient_end',
            'header_name_color', 'header_name_gradient_start', 'header_name_gradient_end', 'header_divider_color',
            'header_divider_paint_gradient_start', 'header_divider_paint_gradient_end' => $this->normalizeColorCandidate($value),
            'background_gradient_angle', 'menu_background_gradient_angle', 'page_background_gradient_angle', 'header_name_gradient_angle', 'header_divider_paint_gradient_angle' => is_numeric($value) && (string) (int) $value === trim((string) $value) ? (int) $value : ($value === '' ? null : $value),
            'background_gradient_stops', 'menu_background_gradient_stops', 'page_background_gradient_stops', 'header_name_gradient_stops', 'header_divider_paint_gradient_stops' =>
                is_array($value) ? array_map(static fn (mixed $stop): mixed => is_array($stop)
                    ? ['color' => is_string($stop['color'] ?? null) ? strtoupper($stop['color']) : ($stop['color'] ?? null),
                       'position' => is_numeric($stop['position'] ?? null) ? (int) $stop['position'] : ($stop['position'] ?? null)]
                    : $stop, array_values($value)) : $value,
            'background_gradient_repeating', 'menu_background_gradient_repeating', 'page_background_gradient_repeating', 'header_name_gradient_repeating', 'header_divider_paint_gradient_repeating' => (bool) $value,
            'background_gradient_repeat_span', 'menu_background_gradient_repeat_span', 'page_background_gradient_repeat_span', 'header_name_gradient_repeat_span', 'header_divider_paint_gradient_repeat_span',
            'background_gradient_center_x', 'menu_background_gradient_center_x', 'page_background_gradient_center_y', 'header_name_gradient_center_x', 'header_name_gradient_center_y', 'header_divider_paint_gradient_center_x', 'header_divider_paint_gradient_center_y',
            'background_gradient_center_y', 'menu_background_gradient_center_y', 'page_background_gradient_center_x' =>
                is_numeric($value) ? (int) $value : $value,
            'header_name_visible', 'header_name_auto_scale' => (bool) $value,
            'header_name_size', 'header_name_min_size', 'public_page_width', 'public_content_padding' => is_numeric($value) && (string) (int) $value === trim((string) $value) ? (int) $value : $value,
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
