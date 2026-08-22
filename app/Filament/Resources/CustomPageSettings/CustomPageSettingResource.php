<?php

namespace App\Filament\Resources\CustomPageSettings;

use App\Domain\Admin\EditorialRecordService;
use App\Domain\Content\SocialLinks;
use App\Filament\Resources\CustomPageSettings\Pages\EditCustomPageSetting;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\MediaAsset;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

final class CustomPageSettingResource extends Resource
{
    protected static ?string $model = CustomPageSetting::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $modelLabel = 'custom page';

    protected static ?string $pluralModelLabel = 'custom pages';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Repeater::make('blocks')
                ->label('Page components')
                ->extraAttributes(['class' => 'admin-component-repeater'])
                ->schema([
                    Select::make('type')
                        ->label('Component')
                        ->options([
                            'image' => 'Image',
                            'cv_list' => 'CV List',
                            'text' => 'Text / Rich Text',
                            'list' => 'List',
                            'divider' => 'Divider',
                            'contact' => 'Contact',
                        ])
                        ->required()
                        ->live(),
                    Select::make('media_asset_id')
                        ->label('Image from Media')
                        ->options(fn (): array => MediaAsset::query()
                            ->where('state', 'available')
                            ->where('mime_type', 'like', 'image/%')
                            ->orderBy('original_filename')
                            ->pluck('original_filename', 'id')
                            ->all())
                        ->searchable()
                        ->required(fn (callable $get): bool => $get('type') === 'image')
                        ->live()
                        ->visible(fn (callable $get): bool => $get('type') === 'image'),
                    Placeholder::make('image_preview')
                        ->label('Preview')
                        ->content(function (callable $get): HtmlString|string {
                            $mediaId = $get('media_asset_id');
                            if (! is_numeric($mediaId)) {
                                return 'Choose an image from Media.';
                            }

                            $asset = MediaAsset::query()->find((int) $mediaId);
                            if (! $asset instanceof MediaAsset) {
                                return 'The selected image is unavailable.';
                            }

                            $url = e(route('admin.media.original', ['mediaAsset' => $asset]));
                            $filename = e((string) $asset->getAttribute('original_filename'));
                            $alt = e((string) ($asset->getAttribute('alt_text') ?? ''));

                            return new HtmlString(
                                '<div class="admin-component-image-preview">'
                                .'<img src="'.$url.'" alt="'.$alt.'" loading="lazy">'
                                .'<span>'.$filename.'</span>'
                                .'</div>',
                            );
                        })
                        ->columnSpanFull()
                        ->visible(fn (callable $get): bool => $get('type') === 'image'),
                    Toggle::make('image_decorative')
                        ->label('Decorative image (empty ALT on the public page)')
                        ->helperText('Leave off for content images. Their ALT text is managed canonically in Media.')
                        ->default(false)
                        ->visible(fn (callable $get): bool => $get('type') === 'image'),
                    Placeholder::make('cv_entries')
                        ->label('CV entries')
                        ->content(function (): HtmlString {
                            $service = app(EditorialRecordService::class);
                            /** @var EloquentCollection<int, CvEntry> $entries */
                            $entries = CvEntry::query()->orderBy('position')->orderBy('id')->get();
                            $rows = $entries->map(function (CvEntry $entry) use ($service): array {
                                return [
                                    'id' => (int) $entry->getKey(),
                                    'year' => (string) ($entry->getAttribute('year_text') ?? ''),
                                    'title' => (string) $entry->getAttribute('title'),
                                    'state' => (string) $entry->getAttribute('state'),
                                    'edit_url' => CvEntryResource::getUrl('edit', ['record' => $entry]),
                                    'can_move_up' => $service->canMove($entry, 'up'),
                                    'can_move_down' => $service->canMove($entry, 'down'),
                                ];
                            })->values()->all();

                            return new HtmlString(view('filament.forms.components.cv-entry-list', [
                                'entries' => $rows,
                                'createUrl' => CvEntryResource::getUrl('create'),
                            ])->render());
                        })
                        ->columnSpanFull()
                        ->visible(fn (callable $get): bool => $get('type') === 'cv_list'),
                    TextInput::make('title')
                        ->label('Component heading')
                        ->maxLength(160)
                        ->visible(fn (callable $get): bool => in_array($get('type'), ['text', 'list'], true)),
                    MarkdownEditor::make('body')
                        ->label('Text')
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->maxLength(20000)
                        ->columnSpanFull()
                        ->visible(fn (callable $get): bool => $get('type') === 'text'),
                    Repeater::make('items')
                        ->label('List entries')
                        ->extraAttributes(['class' => 'admin-component-repeater admin-component-repeater--nested'])
                        ->schema([
                            Toggle::make('visible')
                                ->label('Visible on public page')
                                ->default(true),
                            TextInput::make('date')->label('Date / year')->maxLength(120),
                            TextInput::make('title')->required()->maxLength(240),
                            TextInput::make('meta')->label('Organisation / context')->maxLength(240),
                            TextInput::make('location')->maxLength(240),
                            TextInput::make('url')
                                ->label('Optional link')
                                ->url()
                                ->maxLength(2048),
                            MarkdownEditor::make('body')
                                ->label('Details')
                                ->toolbarButtons([
                                    ['bold', 'italic', 'link'],
                                    ['bulletList', 'orderedList'],
                                    ['undo', 'redo'],
                                ])
                                ->maxLength(10000)
                                ->columnSpanFull(),
                        ])
                        ->columns(2)
                        ->defaultItems(0)
                        ->reorderableWithButtons()
                        ->reorderableWithDragAndDrop(false)
                        ->itemLabel(fn (array $state): ?string => isset($state['title']) && is_string($state['title']) ? $state['title'] : null)
                        ->columnSpanFull()
                        ->visible(fn (callable $get): bool => $get('type') === 'list'),
                    Select::make('form_state')
                        ->label('Form presentation')
                        ->options([
                            'enabled' => 'Enabled',
                            'under_construction' => 'Under construction',
                            'hidden' => 'Hidden',
                        ])
                        ->default('enabled')
                        ->required(fn (callable $get): bool => $get('type') === 'contact')
                        ->live()
                        ->visible(fn (callable $get): bool => $get('type') === 'contact'),
                    TextInput::make('status_text')
                        ->label('Status text')
                        ->maxLength(500)
                        ->required(fn (callable $get): bool => $get('type') === 'contact' && $get('form_state') === 'under_construction')
                        ->visible(fn (callable $get): bool => $get('type') === 'contact' && $get('form_state') === 'under_construction'),
                    Toggle::make('show_email')
                        ->label('Show public email from General')
                        ->default(true)
                        ->visible(fn (callable $get): bool => $get('type') === 'contact'),
                    Toggle::make('show_form')
                        ->label('Show contact form')
                        ->default(true)
                        ->visible(fn (callable $get): bool => $get('type') === 'contact'),
                    Select::make('social_platforms')
                        ->label('Social links from General')
                        ->options(SocialLinks::options())
                        ->multiple()
                        ->default(array_keys(SocialLinks::options()))
                        ->visible(fn (callable $get): bool => $get('type') === 'contact'),
                    Placeholder::make('divider_note')
                        ->label('Divider')
                        ->content('A divider is a visible separator. Move or remove it like any other component.')
                        ->visible(fn (callable $get): bool => $get('type') === 'divider'),
                ])
                ->columns(2)
                ->defaultItems(0)
                ->addActionLabel('Add component')
                ->reorderableWithButtons()
                ->reorderableWithDragAndDrop(false)
                ->itemLabel(function (array $state): string {
                    $title = $state['title'] ?? null;
                    if (is_string($title) && trim($title) !== '') {
                        return trim($title);
                    }

                    return match ($state['type'] ?? null) {
                        'image' => 'Image',
                        'cv_list' => 'CV List',
                        'text' => 'Text / Rich Text',
                        'list' => 'List',
                        'divider' => 'Divider',
                        'contact' => 'Contact',
                        default => 'Component',
                    };
                })
                ->columnSpanFull(),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'edit' => EditCustomPageSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }
}
