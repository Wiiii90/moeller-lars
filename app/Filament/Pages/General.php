<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SocialLinks;
use App\Filament\Support\MediaAssetSelect;
use App\Models\PublicContentSetting;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
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
        $this->form->fill(PublicContentSetting::general()->only(self::PERSISTED_FIELDS));
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(1)
                    ->extraAttributes(['class' => 'general-settings-sheet'])
                    ->schema([
                        $this->settingsSection(
                            'Site identity',
                            'Choose an available image from Media Files.',
                            [
                                MediaAssetSelect::make('favicon_media_asset_id', 'faviconMediaAsset', 'Favicon', imagesOnly: true)
                                    ->nullable()
                                    ->live()
                                    ->afterStateUpdated(self::persist('favicon_media_asset_id'))
                                    ->columnSpan(['default' => 12, 'lg' => 7]),
                                View::make('filament.schemas.components.favicon-preview')
                                    ->extraAttributes(['class' => 'general-settings-section__favicon-preview'])
                                    ->columnSpan(['default' => 12, 'lg' => 5]),
                            ],
                        ),
                        $this->settingsSection(
                            'Public contact',
                            null,
                            [
                                TextInput::make('public_email')
                                    ->label('Public email')
                                    ->email()
                                    ->maxLength(254)
                                    ->nullable()
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('public_email'))
                                    ->columnSpan(['default' => 12, 'lg' => 9]),
                                Toggle::make('show_public_email')
                                    ->label('Show publicly')
                                    ->default(true)
                                    ->live()
                                    ->afterStateUpdated(self::persist('show_public_email'))
                                    ->columnSpan(['default' => 12, 'lg' => 3]),
                            ],
                            'general-settings-section--public-contact',
                        ),
                        $this->settingsSection(
                            'Contact delivery',
                            'If empty, the server-configured fallback recipient is used.',
                            [
                                TextInput::make('contact_recipient_email')
                                    ->label('Private contact recipient')
                                    ->email()
                                    ->maxLength(254)
                                    ->nullable()
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->afterStateUpdated(self::persist('contact_recipient_email'))
                                    ->columnSpanFull(),
                            ],
                        ),
                        $this->settingsSection(
                            'Social links',
                            null,
                            [
                                Repeater::make('social_links')
                                    ->label('Social profiles')
                                    ->hiddenLabel()
                                    ->schema([
                                        Select::make('platform')
                                            ->options(SocialLinks::options())
                                            ->required()
                                            ->live(),
                                        TextInput::make('url')
                                            ->label('Profile URL')
                                            ->url()
                                            ->maxLength(2048)
                                            ->required()
                                            ->lazy()
                                            ->extraInputAttributes(self::commitOnEnterAttributes()),
                                        Toggle::make('visible')
                                            ->label('Visible')
                                            ->default(true)
                                            ->live(),
                                    ])
                                    ->table([
                                        TableColumn::make('Platform')->width('11rem'),
                                        TableColumn::make('Profile URL'),
                                        TableColumn::make('Visible')->width('7rem'),
                                    ])
                                    ->compact()
                                    ->defaultItems(0)
                                    ->reorderableWithButtons()
                                    ->reorderableWithDragAndDrop(false)
                                    ->addActionAlignment(Alignment::Start)
                                    ->addActionLabel('Add social link')
                                    ->addAction(fn (Action $action): Action => $action
                                        ->icon(Heroicon::Plus)
                                        ->link())
                                    ->afterStateUpdated(self::persist('social_links'))
                                    ->extraAttributes(['class' => 'general-social-links'])
                                    ->columnSpanFull(),
                            ],
                        ),
                        $this->settingsSection(
                            'Legal & media',
                            null,
                            [
                                TextInput::make('default_media_copyright_notice')
                                    ->label('Default media copyright')
                                    ->maxLength(500)
                                    ->nullable()
                                    ->lazy()
                                    ->extraInputAttributes(self::commitOnEnterAttributes())
                                    ->helperText('Inherited by media unless an individual file overrides the notice or explicitly uses no notice.')
                                    ->afterStateUpdated(self::persist('default_media_copyright_notice'))
                                    ->columnSpanFull(),
                                Textarea::make('legal_disclaimer')
                                    ->label('Legal disclaimer')
                                    ->rows(6)
                                    ->nullable()
                                    ->lazy()
                                    ->afterStateUpdated(self::persist('legal_disclaimer'))
                                    ->columnSpanFull(),
                            ],
                        ),
                    ]),
            ])
            ->record(PublicContentSetting::general())
            ->statePath('data');
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
            app(AdminSettingsService::class)->updatePublicContent($record, [
                $field => $candidate,
            ]);
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

    /** @param array<int, mixed> $controls */
    private function settingsSection(string $title, ?string $description, array $controls, string $class = ''): Grid
    {
        return Grid::make(12)
            ->extraAttributes([
                'class' => trim('general-settings-section '.$class),
            ])
            ->schema([
                View::make('filament.schemas.components.general-section-label')
                    ->viewData([
                        'title' => $title,
                        'description' => $description,
                    ])
                    ->columnSpan(['default' => 12, 'md' => 3]),
                Grid::make(12)
                    ->extraAttributes(['class' => 'general-settings-section__controls'])
                    ->schema($controls)
                    ->columnSpan(['default' => 12, 'md' => 9]),
            ])
            ->columnSpanFull();
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
            'social_links' => $this->normalizeSocialLinks($value),
            'public_email', 'contact_recipient_email' => $value === '' ? null : $value,
            'default_media_copyright_notice' => is_string($value)
                ? (($trimmed = trim($value)) === '' ? null : $trimmed)
                : $value,
            'legal_disclaimer' => is_string($value) && trim($value) === '' ? null : $value,
            default => $value,
        };
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
