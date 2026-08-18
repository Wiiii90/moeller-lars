<?php

namespace App\Filament\Widgets;

use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\BlogPost;
use App\Models\CvEntry;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Database\Eloquent\Model;

final class ContentOverview extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected ?string $pollingInterval = null;

    protected ?string $heading = 'Content overview';

    protected ?string $description = 'Current editorial state across the public site.';

    protected function getStats(): array
    {
        return [
            Stat::make('Artworks', Artwork::query()->count())
                ->description($this->stateSummary(Artwork::class, ['published', 'draft', 'archived'])),
            Stat::make('Categories', ArtworkCategory::query()->count())
                ->description($this->stateSummary(ArtworkCategory::class, ['published', 'hidden'])),
            Stat::make('Exhibitions', Exhibition::query()->count())
                ->description($this->stateSummary(Exhibition::class, ['published', 'draft', 'hidden'])),
            Stat::make('Vita / CV', CvEntry::query()->count())
                ->description($this->stateSummary(CvEntry::class, ['published', 'draft', 'hidden'])),
            Stat::make('Blog posts', BlogPost::query()->count())
                ->description($this->stateSummary(BlogPost::class, ['published', 'scheduled', 'draft'])),
            Stat::make('Media', MediaAsset::query()->count())
                ->description($this->stateSummary(MediaAsset::class, ['available', 'quarantined', 'deleted'])),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $states
     */
    private function stateSummary(string $model, array $states): string
    {
        $parts = [];

        foreach ($states as $state) {
            $count = $model::query()->where('state', $state)->count();
            if ($count > 0) {
                $parts[] = $count.' '.$state;
            }
        }

        return $parts === [] ? 'No records yet' : implode(' · ', $parts);
    }
}
