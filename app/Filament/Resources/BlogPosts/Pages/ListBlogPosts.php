<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\BlogSettings\BlogSettingResource;
use App\Models\BlogSetting;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

final class ListBlogPosts extends ListRecords
{
    protected static string $resource = BlogPostResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('blogSettings')
                ->label('Blog settings')
                ->icon(Heroicon::OutlinedCog6Tooth)
                ->url(function (): string {
                    $settings = BlogSetting::query()->findOrFail(1);

                    return BlogSettingResource::getUrl('edit', ['record' => $settings]);
                }),
        ];
    }
}
