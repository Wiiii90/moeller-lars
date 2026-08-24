<?php

namespace App\Filament\Resources\Exhibitions\Pages;

use App\Filament\Pages\JournalWorkspace;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use Filament\Resources\Pages\Page;

class ListExhibitions extends Page
{
    protected static string $resource = ExhibitionResource::class;

    protected string $view = 'filament.resources.exhibitions.pages.list-exhibitions';

    public function mount(): void
    {
        $sectionId = request()->integer('section');
        abort_unless($sectionId > 0, 404);

        $this->redirect(JournalWorkspace::getUrl(['section' => $sectionId]), navigate: false);
    }
}
