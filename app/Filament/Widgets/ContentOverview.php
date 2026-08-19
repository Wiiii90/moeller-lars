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
        $artworks = $this->summary(Artwork::class, ['published', 'draft', 'archived']);
        $categories = $this->summary(ArtworkCategory::class, ['published', 'hidden']);
        $exhibitions = $this->summary(Exhibition::class, ['published', 'draft', 'hidden']);
        $cvEntries = $this->summary(CvEntry::class, ['published', 'draft', 'hidden']);
        $blogPosts = $this->summary(BlogPost::class, ['published', 'scheduled', 'draft']);
        $media = $this->summary(MediaAsset::class, ['available', 'quarantined', 'deleted']);

        return [
            Stat::make('Artworks', $artworks['total'])->description($artworks['description']),
            Stat::make('Categories', $categories['total'])->description($categories['description']),
            Stat::make('Exhibitions', $exhibitions['total'])->description($exhibitions['description']),
            Stat::make('Vita / CV', $cvEntries['total'])->description($cvEntries['description']),
            Stat::make('Blog posts', $blogPosts['total'])->description($blogPosts['description']),
            Stat::make('Media', $media['total'])->description($media['description']),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     * @param  list<string>  $states
     * @return array{total:int,description:string}
     */
    private function summary(string $model, array $states): array
    {
        $counts = $model::query()
            ->selectRaw('state, COUNT(*) AS aggregate')
            ->groupBy('state')
            ->pluck('aggregate', 'state');

        $parts = [];
        foreach ($states as $state) {
            $count = (int) ($counts->get($state) ?? 0);
            if ($count > 0) {
                $parts[] = $count.' '.$state;
            }
        }

        return [
            'total' => (int) $counts->sum(),
            'description' => $parts === [] ? 'No records yet' : implode(' · ', $parts),
        ];
    }
}
