<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Models\Exhibition;
use App\Models\SiteSection;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;

class ListExhibitions extends Page
{
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
