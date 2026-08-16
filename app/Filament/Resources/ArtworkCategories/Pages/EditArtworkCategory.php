<?php

namespace App\Filament\Resources\ArtworkCategories\Pages;

use App\Domain\Artwork\ArtworkCategoryEditorialService;
use App\Filament\Resources\ArtworkCategories\ArtworkCategoryResource;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use LogicException;

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
                ->visible(fn (): bool => $this->categoryRecord()->getAttribute('state') === 'hidden')
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->runCategoryAction(fn () => app(ArtworkCategoryEditorialService::class)->delete($this->categoryRecord()), 'Category deleted');
                    $this->redirect(ArtworkCategoryResource::getUrl('index'));
                }),
            Action::make('reorderArtworks')
                ->label('Reorder gallery')
                ->visible(fn (): bool => $this->categoryRecord()->artworks()->count() >= 2)
                ->schema([
                    Repeater::make('artworks')
                        ->default(fn (): array => $this->reorderableArtworkState())
                        ->schema([
                            Hidden::make('id'),
                            TextInput::make('label')
                                ->disabled()
                                ->dehydrated(false),
                        ])
                        ->addable(false)
                        ->deletable(false)
                        ->cloneable(false)
                        ->reorderable(),
                ])
                ->action(function (array $data): void {
                    try {
                        if (! array_key_exists('artworks', $data) || ! is_array($data['artworks'])) {
                            throw ValidationException::withMessages(['artworks' => 'The artwork order is missing.']);
                        }

                        $ids = [];
                        foreach ($data['artworks'] as $item) {
                            if (! is_array($item) || ! array_key_exists('id', $item) || ! is_numeric($item['id'])) {
                                throw ValidationException::withMessages(['artworks' => 'The artwork order is invalid.']);
                            }
                            $ids[] = (int) $item['id'];
                        }

                        app(ArtworkCategoryEditorialService::class)->reorderArtworks($this->categoryRecord(), $ids);
                    } catch (ValidationException) {
                        Notification::make()->title('Gallery order could not be updated')->danger()->send();

                        return;
                    }

                    $this->categoryRecord()->refresh();
                    Notification::make()->title('Gallery order updated')->success()->send();
                }),
        ];
    }

    /** @return list<array{id:int,label:string}> */
    private function reorderableArtworkState(): array
    {
        /** @var Collection<int, Artwork> $artworks */
        $artworks = $this->categoryRecord()->artworks()
            ->orderBy('position')
            ->get();

        $positions = $artworks
            ->map(static fn (Artwork $artwork): int => (int) $artwork->getAttribute('position'))
            ->all();
        if (count($positions) !== count(array_unique($positions))) {
            throw new LogicException('Artwork positions must be unique before the gallery can be reordered.');
        }

        return $artworks->map(static function (Artwork $artwork): array {
            $workDate = $artwork->getAttribute('work_date');
            $year = $workDate instanceof Carbon ? $workDate->format('Y') : 'undated';

            return [
                'id' => (int) $artwork->getKey(),
                'label' => sprintf('%s — %s — %s', (string) $artwork->getAttribute('title'), $year, (string) $artwork->getAttribute('state')),
            ];
        })->values()->all();
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
