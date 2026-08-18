<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $post = $this->post();
        $data['state'] = (string) $post->getAttribute('state');
        $data['position'] = (int) $post->getAttribute('position');
        $data['published_at'] = $post->getAttribute('published_at');
        $data['scheduled_at'] = $post->getAttribute('scheduled_at');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BlogPost $record */
        return app(BlogEditorialService::class)->update($record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('publish')
                ->label(fn (): string => $this->post()->getAttribute('state') === 'scheduled' ? 'Publish now' : 'Publish')
                ->visible(fn (): bool => in_array($this->post()->getAttribute('state'), ['draft', 'scheduled', 'unpublished'], true))
                ->action(fn () => $this->runPostAction(
                    fn (): BlogPost => app(BlogEditorialService::class)->publish($this->post()),
                    'Blog post published',
                )),
            Action::make('schedule')
                ->label(fn (): string => $this->post()->getAttribute('state') === 'scheduled' ? 'Reschedule' : 'Schedule')
                ->visible(fn (): bool => in_array($this->post()->getAttribute('state'), ['draft', 'scheduled', 'unpublished'], true))
                ->schema([
                    DateTimePicker::make('scheduled_at')
                        ->label('Publish at')
                        ->required()
                        ->seconds(false),
                ])
                ->action(function (array $data): void {
                    $this->runPostAction(
                        fn (): BlogPost => app(BlogEditorialService::class)->schedule($this->post(), $data['scheduled_at'] ?? null),
                        'Blog post scheduled',
                    );
                }),
            Action::make('cancelSchedule')
                ->label('Cancel schedule')
                ->visible(fn (): bool => $this->post()->getAttribute('state') === 'scheduled')
                ->requiresConfirmation()
                ->action(fn () => $this->runPostAction(
                    fn (): BlogPost => app(BlogEditorialService::class)->restoreDraft($this->post()),
                    'Schedule cancelled; post restored to draft',
                )),
            Action::make('unpublish')
                ->label('Unpublish')
                ->visible(fn (): bool => $this->post()->getAttribute('state') === 'published')
                ->requiresConfirmation()
                ->action(fn () => $this->runPostAction(
                    fn (): BlogPost => app(BlogEditorialService::class)->unpublish($this->post()),
                    'Blog post unpublished',
                )),
            Action::make('archive')
                ->label('Archive')
                ->visible(fn (): bool => $this->post()->getAttribute('state') !== 'archived')
                ->requiresConfirmation()
                ->action(fn () => $this->runPostAction(
                    fn (): BlogPost => app(BlogEditorialService::class)->archive($this->post()),
                    'Blog post archived',
                )),
            Action::make('restoreDraft')
                ->label('Restore to draft')
                ->visible(fn (): bool => in_array($this->post()->getAttribute('state'), ['unpublished', 'archived'], true))
                ->action(fn () => $this->runPostAction(
                    fn (): BlogPost => app(BlogEditorialService::class)->restoreDraft($this->post()),
                    'Blog post restored to draft',
                )),
        ];
    }

    private function post(): BlogPost
    {
        /** @var BlogPost $record */
        $record = $this->getRecord();

        return $record;
    }

    private function runPostAction(callable $callback, string $success): ?BlogPost
    {
        try {
            /** @var BlogPost $result */
            $result = $callback();
            $this->post()->refresh();
            $this->refreshFormData(['state', 'scheduled_at', 'published_at']);
            Notification::make()->title($success)->success()->send();

            return $result;
        } catch (ValidationException $exception) {
            Notification::make()
                ->title('Blog publication action failed')
                ->body((string) collect($exception->errors())->flatten()->first())
                ->danger()
                ->send();

            return null;
        }
    }
}
