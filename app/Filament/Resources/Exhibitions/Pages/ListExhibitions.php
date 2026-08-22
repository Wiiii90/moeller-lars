<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Content\JournalEntryOrderService;
use App\Filament\Concerns\HasJournalSettingsAction;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

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
                ->url(ExhibitionResource::getUrl('create', ['section' => $this->sectionId])),
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

            return [
                'id' => (int) $exhibition->getKey(),
                'type' => is_string($kind) && $kind !== '' ? ucfirst($kind).' exhibition' : 'Exhibition',
                'title' => (string) $exhibition->getAttribute('title'),
                'date' => (string) $exhibition->getAttribute('date_text'),
                'meta' => $meta === [] ? 'No venue details' : implode(' · ', $meta),
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
            ->where('type', SiteSection::TYPE_JOURNAL)
            ->where('template', SiteSection::JOURNAL_TEMPLATE_EXHIBITIONS)
            ->exists();
        abort_unless($exists, 404);

        return $sectionId;
    }
}
