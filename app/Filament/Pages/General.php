<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Filament\Support\AdminBooleanControl;
use App\Filament\Support\AdminColorControl;
use App\Filament\Support\AdminForm;
use App\Filament\Support\AdminHelp;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Text;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
        'public_email',
        'show_public_email',
        'contact_recipient_email',
        'social_links',
        'default_media_copyright_notice',
        'legal_disclaimer',
    ];

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedGlobeAlt;

    protected static string|UnitEnum|null $navigationGroup = null;

    protected static ?string $navigationLabel = 'General';

    protected static ?string $title = 'General';

    protected static ?string $slug = 'general';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.pages.general';

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public function mount(): void
    {
        $data = PublicContentSetting::general()->only(self::PERSISTED_FIELDS);
        $data['background_mode'] = $data['background_mode'] ?? PublicAppearance::MODE_DEFAULT;
        $this->data = $data;
        $this->syncAppearanceControlState();
        $this->form->fill($this->data);
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                AdminForm::section('Appearance', 'admin-form-controls')
                    ->columns(2)
                    ->schema([
                        Group::make([
                            Text::make('Site icon')
                                ->extraAttributes(['class' => 'admin-form-area-title']),
                            MediaAssetSelect::make(
                                'favicon_media_asset_id',
                                'faviconMediaAsset',
                                fn (callable $get): string => filled($get('favicon_media_asset_id'))
                                    ? 'Replace from Media Files'
                                    : 'Choose from Media Files',
                                imagesOnly: true,
                                includeDimensions: false,
                            )
                                ->placeholder('Choose from Media Files')
                                ->selectablePlaceholder(false)
                                ->extraFieldWrapperAttributes(['class' => 'admin-favicon-control'])
                                ->nullable()
                                ->live()
                                ->afterStateUpdated(self::persist('favicon_media_asset_id')),
                            View::make('filament.schemas.components.favicon-actions'),
                        ])
                            ->extraAttributes(['class' => 'admin-form-area']),
                        Group::make([
                            Text::make('Background')
                                ->extraAttributes(['class' => 'admin-form-area-title']),
                            Radio::make('background_mode')
                                ->label('Mode')
                                ->options([
                                    PublicAppearance::MODE_DEFAULT => 'Default',
                                    PublicAppearance::MODE_SOLID => 'Solid',
                                    PublicAppearance::MODE_GRADIENT => 'Gradient',
                                ])
                                ->inline()
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($livewire): void {
                                    if ($livewire instanceof self) {
                                        $livewire->persistChangedField('background_mode');
                                        $livewire->syncAppearanceControlState();
                                    }
                                }),
                            AdminColorControl::make('background_primary_color', 'Primary color')
                                ->disabled(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_DEFAULT)
                                ->lazy()
                                ->extraInputAttributes(self::commitOnEnterAttributes())
                                ->afterStateUpdated(function ($livewire, mixed $state): void {
                                    if ($livewire instanceof self) {
                                        $livewire->persistAppearanceColor('primary', $state);
                                    }
                                }),
                            AdminColorControl::make('background_secondary_color', 'Secondary color')
                                ->disabled(fn (callable $get): bool => $get('background_mode') !== PublicAppearance::MODE_GRADIENT)
                                ->lazy()
                                ->extraInputAttributes(self::commitOnEnterAttributes())
                                ->afterStateUpdated(function ($livewire, mixed $state): void {
                                    if ($livewire instanceof self) {
                                        $livewire->persistAppearanceColor('secondary', $state);
                                    }
                                }),
                            Hidden::make('background_color'),
                            Hidden::make('background_gradient_start'),
                            Hidden::make('background_gradient_end'),
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
                                ->extraInputAttributes(self::commitOnEnterAttributes())
                                ->afterStateUpdated(self::persist('background_gradient_angle'))
                                ->disabled(fn (callable $get): bool => $get('background_mode') !== PublicAppearance::MODE_GRADIENT),
                        ])
                            ->extraAttributes(['class' => 'admin-form-area']),
                    ]),
                AdminForm::section('Contact', 'admin-form-controls')
                    ->columns(2)
                    ->schema([
                        TextInput::make('public_email')
                            ->label('Public email')
                            ->email()
                            ->maxLength(254)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('public_email')),
                        AdminBooleanControl::make('show_public_email', 'Visibility', 'Visible', 'Hidden')
                            ->live()
                            ->afterStateUpdated(self::persist('show_public_email')),
                        TextInput::make('contact_recipient_email')
                            ->label(self::contactRecipientLabel())
                            ->email()
                            ->maxLength(254)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('contact_recipient_email'))
                            ->columnSpanFull(),
                    ]),
                AdminForm::section('Social links', 'admin-form-controls')
                    ->schema([
                        View::make('filament.schemas.components.general-social-links')
                            ->columnSpanFull(),
                    ]),
                AdminForm::section('Legal', 'admin-form-controls')
                    ->columns(2)
                    ->schema([
                        TextInput::make('default_media_copyright_notice')
                            ->label('Default copyright notice')
                            ->maxLength(500)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('default_media_copyright_notice')),
                        Textarea::make('legal_disclaimer')
                            ->label('Legal disclaimer')
                            ->rows(8)
                            ->nullable()
                            ->lazy()
                            ->afterStateUpdated(self::persist('legal_disclaimer'))
                            ->columnSpanFull(),
                    ]),
            ])
            ->record(PublicContentSetting::general())
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
            : ($this->data['background_color'] ?? null);
        $this->data['background_secondary_color'] = $this->data['background_gradient_end'] ?? null;
    }

    public function addSocialLink(): void
    {
        $links = is_array($this->data['social_links'] ?? null) ? array_values($this->data['social_links']) : [];
        $links[] = ['platform' => '', 'url' => '', 'visible' => true];
        $this->data['social_links'] = $links;
    }

    public function updateSocialLink(int $index, string $field, mixed $value): void
    {
        if (! in_array($field, ['platform', 'url', 'visible'], true) || ! isset($this->data['social_links'][$index]) || ! is_array($this->data['social_links'][$index])) {
            return;
        }

        $this->data['social_links'][$index][$field] = $field === 'visible' ? ((string) $value === '1') : $value;
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
            if (in_array($field, ['background_color', 'background_gradient_start', 'background_gradient_end'], true)) {
                $this->data[$field] = $candidate;
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

    private static function persist(string $field): \Closure
    {
        return static function ($livewire) use ($field): void {
            if ($livewire instanceof self) {
                $livewire->persistChangedField($field);
            }
        };
    }

    private static function contactRecipientLabel(): HtmlString
    {
        return new HtmlString(
            '<span class="admin-form-label-with-help">Contact form recipient'.
            AdminHelp::make(
                'About contact form delivery',
                'Contact-form messages are delivered privately to this address. It can be different from the public email.',
            )->toHtml().
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
            'show_public_email' => (bool) $value,
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
                'visible' => (bool) ($link['visible'] ?? true),
            ];
        }, $value));
    }

    /** @return array<string, string> */
    private static function commitOnEnterAttributes(): array
    {
        return ['x-on:keydown.enter.prevent' => '$event.target.blur()'];
    }
}
