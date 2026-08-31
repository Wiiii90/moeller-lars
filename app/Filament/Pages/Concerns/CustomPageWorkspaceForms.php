<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\CvEntryEditorialService;
use App\Filament\Support\AdminRichText;
use App\Filament\Support\MediaAssetSelect;
use App\Models\CvEntry;
use App\Models\PublicContentSetting;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceForms
{
    /** @return list<mixed> */
    private function componentEditorSchema(bool $includeTypeSelect): array
    {
        $typeField = $includeTypeSelect
            ? Select::make('type')
                ->label('Component')
                ->options(self::COMPONENT_LABELS)
                ->required()
                ->live()
                ->afterStateUpdated(fn (Select $component) => $component
                    ->getContainer()
                    ->getComponent('dynamicComponentFields')
                    ->getChildSchema()
                    ->fill())
            : Hidden::make('type')->required();

        return [
            $typeField,
            Select::make('publication_state')
                ->label('Status')
                ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                ->default('published')
                ->required(),
            Grid::make(1)
                ->schema(fn (Get $get): array => $this->componentTypeFields((string) $get('type'), $includeTypeSelect))
                ->key('dynamicComponentFields'),
        ];
    }

    /** @return list<mixed> */
    private function componentTypeFields(string $type, bool $isNew): array
    {
        return match ($type) {
            'image' => [
                MediaAssetSelect::makeId('media_asset_id', 'Image from Media Files', imagesOnly: true)->required(),
                Toggle::make('image_decorative')->label('Decorative image')->default(false),
            ],
            'text' => $isNew
                ? AdminRichText::schema('body', 'Rich Text', 20000)
                : [
                    TextInput::make('title')->label('Heading')->maxLength(160),
                    ...AdminRichText::schema('body', 'Rich Text', 20000),
                ],
            'list' => [
                TextInput::make('title')->label('Heading')->maxLength(160),
            ],
            'cv_list' => [
                MediaAssetSelect::makeId('media_asset_id', 'Image from Media Files', imagesOnly: true)->nullable(),
                Repeater::make('cv_entries')
                    ->label('CV entries')
                    ->schema($this->cvEntryRepeaterSchema())
                    ->default(fn (): array => $this->cvEntryEditorRows())
                    ->defaultItems(0)
                    ->addActionLabel('Add CV entry')
                    ->reorderableWithButtons()
                    ->reorderableWithDragAndDrop(false)
                    ->itemLabel(function (array $state): ?string {
                        $title = is_string($state['title'] ?? null) ? trim($state['title']) : '';
                        $year = is_string($state['year_text'] ?? null) ? trim($state['year_text']) : '';
                        if ($title === '' && $year === '') {
                            return null;
                        }

                        return trim($year.' · '.$title, ' ·');
                    })
                    ->columns(2)
                    ->extraAttributes(['class' => 'admin-component-repeater admin-component-repeater--nested'])
                    ->columnSpanFull(),
            ],
            'divider' => [
                Select::make('variant')->label('Divider')->options(self::DIVIDER_LABELS)->default('thin')->required(),
            ],
            'contact' => [
                Placeholder::make('contact_note')
                    ->label('Contact items')
                    ->content($isNew
                        ? 'Public Email, Social Media Links and Contact Form are created with this component.'
                        : 'Contact items are managed in the child rows below.'),
            ],
            'legal_disclaimer' => [
                Placeholder::make('legal_disclaimer_note')
                    ->label('Legal disclaimer from General')
                    ->content(function (): string {
                        $value = PublicContentSetting::general()->getAttribute('legal_disclaimer');

                        return is_string($value) && trim($value) !== ''
                            ? $value
                            : 'No legal disclaimer is configured in General.';
                    }),
            ],
            default => [],
        };
    }

    /** @return list<mixed> */
    private function listEntrySchema(): array
    {
        return [
            Select::make('publication_state')
                ->label('Status')
                ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                ->default('published')
                ->required(),
            TextInput::make('date')->label('Date / year')->maxLength(120),
            TextInput::make('title')->label('Entry')->required()->maxLength(240),
            TextInput::make('meta')->label('Organisation / context')->maxLength(240),
            TextInput::make('location')->maxLength(240),
            TextInput::make('url')->label('Optional link')->url()->maxLength(2048),
            ...AdminRichText::schema('body', 'Details', 10000),
        ];
    }

    /** @return list<mixed> */
    private function cvEntryCreateSchema(): array
    {
        return [
            Select::make('publication_state')
                ->label('Status')
                ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                ->default('unpublished')
                ->required(),
            ...$this->cvEntrySchema(),
        ];
    }

    /** @return list<mixed> */
    private function cvEntrySchema(): array
    {
        return [
            TextInput::make('section')->required()->maxLength(120)->default('CV'),
            TextInput::make('title')->label('Entry')->required()->maxLength(240),
            TextInput::make('year_text')->label('Displayed date / year')->required()->maxLength(80),
            Select::make('date_precision')->options([
                'unknown' => 'Unknown',
                'year' => 'Year',
                'month' => 'Month',
                'day' => 'Day',
            ])->required()->default('unknown'),
            Grid::make()
                ->columns(['md' => 2])
                ->schema([
                    DatePicker::make('starts_on')->label('Starts on')->nullable(),
                    DatePicker::make('ends_on')->label('Ends on')->nullable(),
                ]),
            TextInput::make('organisation')->maxLength(240)->nullable(),
            TextInput::make('location')->maxLength(240)->nullable(),
            ...AdminRichText::schema('body', 'Details', 10000),
            MediaAssetSelect::makeId('image_media_asset_id', 'Image from Media Files', imagesOnly: true)
                ->nullable()
                ->columnSpanFull(),
            TextInput::make('external_url')->label('External URL')->url()->maxLength(2048)->nullable()->columnSpanFull(),
        ];
    }

    /** @return list<mixed> */
    private function cvEntryRepeaterSchema(): array
    {
        return [
            Hidden::make('id'),
            Select::make('publication_state')
                ->label('Status')
                ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
                ->default('unpublished')
                ->required(),
            ...$this->cvEntrySchema(),
        ];
    }

    /** @return list<array<string,mixed>> */
    private function cvEntryEditorRows(): array
    {
        return CvEntry::query()
            ->orderBy('position')
            ->orderBy('id')
            ->get()
            ->map(fn (CvEntry $entry): array => $this->cvEntryEditorRow($entry))
            ->all();
    }

    /** @return array<string,mixed> */
    private function cvEntryEditorRow(CvEntry $entry): array
    {
        return [
            'id' => (int) $entry->getKey(),
            'publication_state' => (string) $entry->getAttribute('state') === 'published' ? 'published' : 'unpublished',
            'section' => $entry->getAttribute('section'),
            'title' => $entry->getAttribute('title'),
            'year_text' => $entry->getAttribute('year_text'),
            'date_precision' => $entry->getAttribute('date_precision'),
            'starts_on' => $entry->getAttribute('starts_on'),
            'ends_on' => $entry->getAttribute('ends_on'),
            'organisation' => $entry->getAttribute('organisation'),
            'location' => $entry->getAttribute('location'),
            'body' => $entry->getAttribute('body'),
            'image_media_asset_id' => $entry->getAttribute('image_media_asset_id'),
            'external_url' => $entry->getAttribute('external_url'),
        ];
    }

    private function syncCvEntryEditorRows(mixed $rows): void
    {
        if (! is_array($rows) || ! array_is_list($rows)) {
            throw ValidationException::withMessages(['cv_entries' => 'CV entries must be an ordered list.']);
        }

        app(CvEntryEditorialService::class)->syncOrdered($rows);
    }
}
