<?php

namespace App\Filament\Resources\CvEntries\Pages;

use App\Domain\Admin\EditorialRecordService;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\CvEntry;
use App\Models\PublicContentSetting;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ListCvEntries extends Page
{
    protected static string $resource = CvEntryResource::class;

    protected string $view = 'filament.resources.cv-entries.pages.list-cv-entries';

    /** @var list<array<string, mixed>> */
    public array $entries = [];

    public function mount(): void
    {
        $this->loadEntries();
    }

    public function moveEntry(int $entryId, string $direction): void
    {
        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        if (app(EditorialRecordService::class)->move($entry, $direction)) {
            Notification::make()->title('Vita / CV order updated')->success()->send();
        }

        $this->loadEntries();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addEntry')
                ->label('Add Vita / CV entry')
                ->icon(Heroicon::OutlinedPlus)
                ->url(CvEntryResource::getUrl('create')),
            Action::make('contactSettings')
                ->label('Contact settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->url(PublicContentSettingResource::getUrl('edit', ['record' => PublicContentSetting::query()->sole()])),
            Action::make('pages')
                ->label('Back to Pages')
                ->url(SitePages::getUrl()),
        ];
    }

    private function loadEntries(): void
    {
        /** @var EloquentCollection<int, CvEntry> $records */
        $records = CvEntry::query()->orderBy('position')->orderBy('id')->get();
        $lastIndex = $records->count() - 1;

        $this->entries = $records->values()->map(static function (CvEntry $entry, int $index) use ($lastIndex): array {
            $meta = array_values(array_filter([
                $entry->getAttribute('organisation'),
                $entry->getAttribute('location'),
            ], static fn ($value): bool => is_string($value) && trim($value) !== ''));

            return [
                'id' => (int) $entry->getKey(),
                'section' => (string) $entry->getAttribute('section'),
                'title' => (string) $entry->getAttribute('title'),
                'date' => (string) $entry->getAttribute('year_text'),
                'meta' => $meta === [] ? 'Vita / CV entry' : implode(' · ', $meta),
                'state' => (string) $entry->getAttribute('state'),
                'edit_url' => CvEntryResource::getUrl('edit', ['record' => $entry]),
                'public_url' => $entry->getAttribute('state') === 'published' ? route('cv') : null,
                'can_move_up' => $index > 0,
                'can_move_down' => $index < $lastIndex,
            ];
        })->all();
    }
}
