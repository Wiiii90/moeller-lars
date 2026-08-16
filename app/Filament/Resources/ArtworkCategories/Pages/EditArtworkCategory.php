<?php

namespace App\Filament\Resources\ArtworkCategories\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Domain\Artwork\ArtworkCategoryPathPolicy;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Models\ArtworkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

class EditArtworkCategory extends EditRecord
{
    protected static string $resource = ArtworkCategoryResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        return app(ArtworkCategoryEditorialService::class)->update($this->categoryRecord(), $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label('Publish')
                ->visible(fn (): bool => $this->categoryRecord()->getAttribute('state') !== 'published')
                ->action(fn (): ArtworkCategory => $this->runCategoryAction(fn () => app(ArtworkCategoryEditorialService::class)->publish($this->categoryRecord()), 'Category published')),
            Action::make('hide')
                ->label('Hide')
                ->visible(fn (): bool => $this->categoryRecord()->getAttribute('state') === 'published')
                ->requiresConfirmation()
                ->action(fn (): ArtworkCategory => $this->runCategoryAction(fn () => app(ArtworkCategoryEditorialService::class)->hide($this->categoryRecord()), 'Category hidden')),
            Action::make('changeSlug')
                ->label('Change public slug')
                ->visible(fn (): bool => ! app(ArtworkCategoryPathPolicy::class)->isLegacyStable((string) $this->categoryRecord()->getAttribute('slug')))
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
                ->label('Delete category')
                ->visible(fn (): bool => ! app(ArtworkCategoryPathPolicy::class)->isLegacyStable((string) $this->categoryRecord()->getAttribute('slug')) && $this->categoryRecord()->getAttribute('state') === 'hidden')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->runCategoryAction(fn () => app(ArtworkCategoryEditorialService::class)->delete($this->categoryRecord()), 'Category deleted');
                    $this->redirect(ArtworkCategoryResource::getUrl('index'));
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
            Notification::make()->title('Category action failed')->body(collect($exception->errors())->flatten()->first())->danger()->send();

            return $this->categoryRecord();
        }
    }
}
