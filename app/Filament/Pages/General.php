<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\PublicAppearance;
use App\Filament\Support\AdminBooleanControl;
use App\Filament\Support\AdminColorControl;
use App\Filament\Support\AdminHelp;
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

    public string $socialSearch = '';

    public string $socialVisibility = 'any';

    /** @var list<int> */
    public array $selectedSocialLinkIndexes = [];

    public function mount(): void
    {
        $data = PublicContentSetting::general()->only(self::PERSISTED_FIELDS);
        $mode = $data['background_mode'] ?? null;
        if ($mode === null || $mode === '' || $mode === PublicAppearance::MODE_DEFAULT) {
            $data['background_mode'] = PublicAppearance::MODE_SOLID;
            $data['background_color'] = PublicAppearance::DEFAULT_PAGE_COLOR;
        }

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
                Group::make([
                    MediaAssetSelect::make(
                        'favicon_media_asset_id',
                        'faviconMediaAsset',
                        'Site icon',
                        imagesOnly: true,
                        includeDimensions: false,
                    )
                        ->placeholder('Choose from Media Files')
                        ->selectablePlaceholder(false)
                        ->extraFieldWrapperAttributes(['class' => 'admin-favicon-control'])
                        ->nullable()
                        ->live()
                        ->suffixAction(
                            Action::make('removeFavicon')
                                ->label('Remove site icon')
                                ->icon('heroicon-m-x-mark')
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
                                PublicAppearance::MODE_GRADIENT => 'Gradient',
                            ])
                            ->native()
                            ->required()
                            ->live()
                            ->afterStateUpdated(function ($livewire): void {
                                if ($livewire instanceof self) {
                                    $livewire->persistChangedField('background_mode');
                                    if (($livewire->data['background_mode'] ?? null) === PublicAppearance::MODE_SOLID) {
                                        $livewire->persistChangedField('background_color');
                                    }
                                    $livewire->syncAppearanceControlState();
                                }
                            }),

                        Group::make([
                            AdminColorControl::make('background_primary_color', 'Primary color')
                                ->extraFieldWrapperAttributes(['class' => 'general-color-control'])
                                ->lazy()
                                ->extraInputAttributes(self::commitOnEnterAttributes())
                                ->afterStateUpdated(function ($livewire, mixed $state): void {
                                    if ($livewire instanceof self) {
                                        $livewire->persistAppearanceColor('primary', $state);
                                    }
                                }),
                            AdminColorControl::make('background_secondary_color', 'Secondary color')
                                ->extraFieldWrapperAttributes(['class' => 'general-color-control'])
                                ->lazy()
                                ->extraInputAttributes(self::commitOnEnterAttributes())
                                ->afterStateUpdated(function ($livewire, mixed $state): void {
                                    if ($livewire instanceof self) {
                                        $livewire->persistAppearanceColor('secondary', $state);
                                    }
                                })
                                ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_GRADIENT),
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
                                ->visible(fn (callable $get): bool => $get('background_mode') === PublicAppearance::MODE_GRADIENT),
                        ])
                            ->columns(3)
                            ->columnSpanFull(),

                        Hidden::make('background_color'),
                        Hidden::make('background_gradient_start'),
                        Hidden::make('background_gradient_end'),
                    ])
                        ->columns(1)
                        ->columnSpanFull(),

                    View::make('filament.schemas.components.general-separator')
                        ->columnSpanFull(),

                    View::make('filament.schemas.components.general-social-links')
                        ->viewData(fn ($livewire): array => ['generalPage' => $livewire])
                        ->columnSpanFull(),

                    View::make('filament.schemas.components.general-separator')
                        ->columnSpanFull(),

                    Group::make([
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
                            ->afterStateUpdated(self::persist('contact_recipient_email')),
                    ])
                        ->columns(3)
                        ->columnSpanFull(),

                    View::make('filament.schemas.components.general-separator')
                        ->columnSpanFull(),

                    Group::make([
                        TextInput::make('default_media_copyright_notice')
                            ->label('Default copyright notice')
                            ->maxLength(500)
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('default_media_copyright_notice')),
                        TextInput::make('legal_disclaimer')
                            ->label('Legal disclaimer')
                            ->nullable()
                            ->lazy()
                            ->extraInputAttributes(self::commitOnEnterAttributes())
                            ->afterStateUpdated(self::persist('legal_disclaimer')),
                    ])
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                    ->extraAttributes(['class' => 'admin-form-controls general-form-controls'])
                    ->columnSpanFull(),
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

    public function addSocialLink(): void
    {
        if (! is_array($this->data)) {
            return;
        }

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
        $this->clearSocialSelection();
        $this->persistChangedField('social_links');
    }

    public function sortSocialLink(int|string $index, int $position): void
    {
        if (! $this->canDragSortSocialLinks()) {
            return;
        }

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
        $this->data['social_links'] = array_values($links);
        $this->clearSocialSelection();
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
        $this->clearSocialSelection();
        $this->persistChangedField('social_links');
    }

    public function toggleSocialSelection(int $index): void
    {
        if (! isset($this->data['social_links'][$index])) {
            return;
        }

        $selected = $this->socialSelectedIndexes();
        $this->selectedSocialLinkIndexes = in_array($index, $selected, true)
            ? array_values(array_filter($selected, static fn (int $selectedIndex): bool => $selectedIndex !== $index))
            : [...$selected, $index];
    }

    public function toggleVisibleSocialSelection(): void
    {
        $visible = array_column($this->socialVisibleRows(), 'index');
        $selected = $this->socialSelectedIndexes();
        $selectedVisible = array_values(array_intersect($visible, $selected));

        $this->selectedSocialLinkIndexes = count($selectedVisible) === count($visible) && $visible !== []
            ? array_values(array_diff($selected, $visible))
            : array_values(array_unique([...$selected, ...$visible]));
    }

    public function deleteSelectedSocialLinks(): void
    {
        $links = is_array($this->data['social_links'] ?? null) ? array_values($this->data['social_links']) : [];
        $selected = $this->socialSelectedIndexes();
        rsort($selected);

        foreach ($selected as $index) {
            if (isset($links[$index])) {
                array_splice($links, $index, 1);
            }
        }

        if ($links === array_values($this->data['social_links'] ?? [])) {
            return;
        }

        $this->data['social_links'] = array_values($links);
        $this->clearSocialSelection();
        $this->persistChangedField('social_links');
    }

    public function updatedSocialVisibility(): void
    {
        if (! in_array($this->socialVisibility, ['any', 'visible', 'hidden'], true)) {
            $this->socialVisibility = 'any';
        }
    }

    public function resetSocialFilters(): void
    {
        $this->socialSearch = '';
        $this->socialVisibility = 'any';
    }

    public function canDragSortSocialLinks(): bool
    {
        return trim($this->socialSearch) === ''
            && $this->socialVisibility === 'any';
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

    /** @return array<int, array{index: int, link: array<string, mixed>}> */
    public function socialVisibleRows(): array
    {
        $search = mb_strtolower(trim($this->socialSearch));
        $visibility = $this->socialVisibility;
        if (! in_array($visibility, ['any', 'visible', 'hidden'], true)) {
            $visibility = 'any';
        }

        $rows = [];
        foreach ($this->socialLinks() as $index => $link) {
            $matchesSearch = $search === ''
                || str_contains(mb_strtolower((string) ($link['platform'] ?? '')), $search)
                || str_contains(mb_strtolower((string) ($link['url'] ?? '')), $search);
            $isVisible = (bool) ($link['visible'] ?? true);
            $matchesVisibility = $visibility === 'any'
                || ($visibility === 'visible' && $isVisible)
                || ($visibility === 'hidden' && ! $isVisible);

            if ($matchesSearch && $matchesVisibility) {
                $rows[] = ['index' => $index, 'link' => $link];
            }
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

    /** @return array<int, array<string, mixed>> */
    private function socialLinks(): array
    {
        return is_array($this->data['social_links'] ?? null)
            ? array_values(array_filter($this->data['social_links'], 'is_array'))
            : [];
    }

    /** @return array<int, int> */
    private function socialSelectedIndexes(): array
    {
        $selected = $this->selectedSocialLinkIndexes;

        return array_values(array_unique(array_map('intval', $selected)));
    }

    private function clearSocialSelection(): void
    {
        $this->selectedSocialLinkIndexes = [];
    }

    /** @return array<string, string> */
    private static function commitOnEnterAttributes(): array
    {
        return ['x-on:keydown.enter.prevent' => '$event.target.blur()'];
    }
}
