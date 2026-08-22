<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Content\ExhibitionDraftService;
use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Concerns\HasJournalSettingsAction;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;

class ListExhibitions extends Page
{
    use HasJournalSettingsAction;

    protected static string $resource = ExhibitionResource::class;

    protected string $view = 'filament.resources.exhibitions.pages.list-exhibitions';

    public int $sectionId;

    /** @var list<array<string, mixed>> */
    public array $exhibitions = [];

    public function mount(): void
    {
        $this->sectionId = $this->resolveSectionId();
        $this->loadExhibitions();
    }

    public function moveExhibition(int $exhibitionId, string $direction): void
    {
        /** @var Exhibition $exhibition */
        $exhibition = Exhibition::query()
            ->where('site_section_id', $this->sectionId)
            ->findOrFail($exhibitionId);
        if (app(JournalEntryOrderService::class)->move($exhibition, $direction)) {
            Notification::make()->title('Exhibition order updated')->success()->send();
        }

        $this->loadExhibitions();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addExhibition')
                ->label('Add exhibition')
                ->icon(Heroicon::OutlinedPlus)
                ->schema([
                    TextInput::make('title')
                        ->required()
                        ->maxLength(240)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                            if (blank($get('slug')) && filled($state)) {
                                $set('slug', Str::slug($state));
                            }
                        }),
                    TextInput::make('slug')
                        ->label('Entry URL slug')
                        ->required()
                        ->maxLength(180)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->unique('exhibitions', 'slug'),
                    TextInput::make('date_text')
                        ->label('Displayed exhibition dates')
                        ->required()
                        ->maxLength(160),
                    TextInput::make('opening_text')
                        ->label('Opening / vernissage')
                        ->maxLength(500)
                        ->nullable(),
                    Select::make('kind')
                        ->options([
                            'solo' => 'Solo',
                            'group' => 'Group',
                        ])
                        ->nullable(),
                    DatePicker::make('starts_on')->nullable(),
                    DatePicker::make('ends_on')->nullable(),
                    TextInput::make('venue')->maxLength(240)->nullable(),
                    TextInput::make('city')->maxLength(160)->nullable(),
                    TextInput::make('country')->maxLength(160)->nullable(),
                    TextInput::make('location_text')
                        ->label('Location / address')
                        ->maxLength(500)
                        ->nullable()
                        ->columnSpanFull(),
                    MarkdownEditor::make('description')
                        ->label('Description')
                        ->toolbarButtons([
                            ['bold', 'italic', 'link'],
                            ['bulletList', 'orderedList'],
                            ['undo', 'redo'],
                        ])
                        ->helperText('Formatting is limited to emphasis, links and lists so it stays compatible with the public exhibition renderer.')
                        ->maxLength(10000)
                        ->nullable()
                        ->columnSpanFull(),
                    TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
                    TextInput::make('directions_url')->label('Directions URL')->url()->maxLength(2048)->nullable(),
                ])
                ->modalHeading('Add exhibition')
                ->modalSubmitActionLabel('Create draft')
                ->action(function (array $data): void {
                    $data['site_section_id'] = $this->sectionId;
                    app(ExhibitionDraftService::class)->create($data);
                    $this->loadExhibitions();

                    Notification::make()
                        ->title('Exhibition draft created')
                        ->body('The exhibition remains private until it is explicitly published. Media can be attached while editing the draft.')
                        ->success()
                        ->send();
                }),
            $this->journalSettingsAction(),
            Action::make('pages')
                ->label('Back to Pages')
                ->url(SitePages::getUrl()),
        ];
    }

    protected function journalSectionId(): int
    {
        return $this->sectionId;
    }

    private function loadExhibitions(): void
    {
        /** @var EloquentCollection<int, Exhibition> $records */
        $records = Exhibition::query()
            ->where('site_section_id', $this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $lastIndex = $records->count() - 1;

        $this->exhibitions = $records->values()->map(static function (Exhibition $exhibition, int $index) use ($lastIndex): array {
            $meta = array_values(array_filter([
                $exhibition->getAttribute('venue'),
                $exhibition->getAttribute('city'),
                $exhibition->getAttribute('country'),
            ], static fn ($value): bool => is_string($value) && trim($value) !== ''));
            $kind = $exhibition->getAttribute('kind');
            $opening = $exhibition->getAttribute('opening_text');

            return [
                'id' => (int) $exhibition->getKey(),
                'type' => is_string($kind) && $kind !== '' ? ucfirst($kind).' exhibition' : 'Exhibition',
                'title' => (string) $exhibition->getAttribute('title'),
                'date' => (string) $exhibition->getAttribute('date_text'),
                'meta' => $meta === [] ? 'No venue details' : implode(' · ', $meta),
                'opening' => is_string($opening) && trim($opening) !== '' ? trim($opening) : null,
                'state' => (string) $exhibition->getAttribute('state'),
                'edit_url' => ExhibitionResource::getUrl('edit', ['record' => $exhibition]),
                'public_url' => $exhibition->getAttribute('state') === 'published' ? ExhibitionResource::publicUrl($exhibition) : null,
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $lastIndex,
            ];
        })->all();
    }

    private function resolveSectionId(): int
    {
        $sectionId = request()->integer('section');
        abort_unless($sectionId > 0, 404);

        $exists = SiteSection::query()
            ->whereKey($sectionId)
            ->where('type', SiteNodeType::Journal->value)
            ->where('template', JournalTemplate::Exhibitions->value)
            ->exists();
        abort_unless($exists, 404);

        return $sectionId;
    }
}
