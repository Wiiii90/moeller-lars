<?php

namespace App\Filament\Resources\ArtworkCategories\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Models\ArtworkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditArtworkCategory extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = ArtworkCategoryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ArtworkCategoryEditorialService::class)->update($this->categoryRecord(), $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(ArtworkResource::getUrl('gallery', [
            'gallery' => $this->categoryRecord()->getKey(),
        ]));
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pages')
                ->label('Pages')
                ->url(SitePages::getUrl()),
            Action::make('manageArtworks')
                ->label('Artworks')
                ->url(fn (): string => ArtworkResource::getUrl('gallery', ['gallery' => $this->categoryRecord()->getKey()])),
            Action::make('changeSlug')
                ->label('Change public slug')
                ->schema([
                    TextInput::make('slug')
                        ->required()
                        ->maxLength(80)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/'),
                ])
                ->action(function (array $data): void {
                    $this->runCategoryAction(fn () => app(ArtworkCategoryEditorialService::class)->changeSlug($this->categoryRecord(), $data['slug']), 'Public slug changed');
                    $this->categoryRecord()->refresh();
                }),
            Action::make('deleteCategory')
                ->label('Delete Gallery')
                ->visible(fn (): bool => $this->categoryRecord()->siteSection()->where('state', 'hidden')->exists())
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->runCategoryAction(fn () => app(ArtworkCategoryEditorialService::class)->delete($this->categoryRecord()), 'Gallery deleted');
                    $this->redirect(SitePages::getUrl());
                }),
        ];
    }

    private function categoryRecord(): ArtworkCategory
    {
        /** @var ArtworkCategory $record */
        $record = $this->getRecord();

        return $record;
    }

    private function runCategoryAction(callable $callback, string $success): mixed
    {
        try {
            $result = $callback();
            $this->categoryRecord()->refresh();
            Notification::make()->title($success)->success()->send();

            return $result;
        } catch (ValidationException $exception) {
            Notification::make()->title('Gallery action failed')->body(collect($exception->errors())->flatten()->first())->danger()->send();

            return $this->categoryRecord();
        }
    }
}
