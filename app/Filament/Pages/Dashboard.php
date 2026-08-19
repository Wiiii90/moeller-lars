<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\CvEntries\CvEntryResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Widgets\ArtistDashboard;
use Filament\Actions\Action;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Support\Icons\Heroicon;

final class Dashboard extends BaseDashboard
{
    protected static ?string $title = 'Dashboard';

    protected static ?int $navigationSort = -2;

    public function getColumns(): array
    {
        return ['md' => 1];
    }

    public function getWidgets(): array
    {
        return [ArtistDashboard::class];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('addArtwork')
                ->label('Add artwork')
                ->icon(Heroicon::OutlinedPlus)
                ->url(ArtworkResource::getUrl('create')),
            Action::make('addExhibition')
                ->label('Add exhibition')
                ->icon(Heroicon::OutlinedPlus)
                ->url(ExhibitionResource::getUrl('create')),
            Action::make('addCvEntry')
                ->label('Add Vita / CV entry')
                ->icon(Heroicon::OutlinedPlus)
                ->url(CvEntryResource::getUrl('create')),
            Action::make('addBlogPost')
                ->label('Add blog post')
                ->icon(Heroicon::OutlinedPlus)
                ->url(BlogPostResource::getUrl('create')),
            Action::make('managePages')
                ->label('Manage pages')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->url(SitePages::getUrl()),
            Action::make('openSite')
                ->label('Open public site')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->url(route('home'))
                ->openUrlInNewTab(),
        ];
    }
}
