<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Filament\Support\AdminBooleanControl;
use App\Filament\Support\AdminForm;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
        $this->form->fill($data);
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
                        MediaAssetSelect::make('favicon_media_asset_id', 'faviconMediaAsset', 'Favicon', imagesOnly: true)
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(self::persist('favicon_media_asset_id')),
                        Select::make('background_mode')
                            ->label('Public site background')
                            ->options(PublicAppearance::modeOptions())
                            ->required()
                            ->live()
                            ->afterStateUpdated(self::persist('background_mode')),
                        TextInput::make('background_color')
                            ->label('Background color')
                            ->placeholder(PublicAppearance::DEFAULT_PAGE_COLOR)
                            ->maxLength(7)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('background_color'))
                            ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_SOLID),
                        TextInput::make('background_gradient_start')
                            ->label('Start color')
                            ->placeholder(PublicAppearance::DEFAULT_PAGE_COLOR)
                            ->maxLength(7)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('background_gradient_start'))
                            ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_GRADIENT),
                        TextInput::make('background_gradient_end')
                            ->label('End color')
                            ->placeholder(PublicAppearance::DEFAULT_PAGE_COLOR)
                            ->maxLength(7)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('background_gradient_end'))
                            ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_GRADIENT),
                        TextInput::make('background_gradient_angle')
                            ->label('Angle')
                            ->numeric()
                            ->integer()
                            ->minValue(0)
                            ->maxValue(360)
                            ->step(1)
                            ->placeholder((string) PublicAppearance::DEFAULT_GRADIENT_ANGLE)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('background_gradient_angle'))
                            ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_GRADIENT),
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
                            ->label('Contact form recipient')
                            ->email()
                            ->maxLength(254)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->helperText('Empty uses the server fallback.')
                            ->afterStateUpdated(self::persist('contact_recipient_email'))
                            ->columnSpanFull(),
                    ]),
                AdminForm::section('Social links', 'admin-form-controls')
                    ->schema([
                        View::make('filament.schemas.components.general-social-links')
                            ->columnSpanFull(),
                    ]),
                AdminForm::section('Legal & media', 'admin-form-controls')
                    ->schema([
                        TextInput::make('default_media_copyright_notice')
                            ->label('Default media copyright')
                            ->maxLength(500)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('default_media_copyright_notice')),
                        Textarea::make('legal_disclaimer')
                            ->label('Legal disclaimer')
                            ->rows(6)
                            ->nullable()
                            ->lazy()
                            ->afterStateUpdated(self::persist('legal_disclaimer')),
                    ]),
            ])
            ->record(PublicContentSetting::general())
            ->statePath('data');
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
