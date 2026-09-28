<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Content\BlogEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Pages\JournalWorkspace;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Support\JournalEntryEditorState;
use App\Models\BlogPost;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditBlogPost extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...app(JournalEntryEditorState::class)->for($this->post())];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $post = $this->post();
        $data['site_section_id'] = (int) $post->getAttribute('site_section_id');
        $data['state'] = (string) $post->getAttribute('state');
        $data['position'] = (int) $post->getAttribute('position');
        $data['published_at'] = $post->getAttribute('published_at');
        $data['scheduled_at'] = $post->getAttribute('scheduled_at');

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BlogPost $record */
        $before = $this->mutationState($record);
        $updated = app(BlogEditorialService::class)->update($record, $data);
        $this->adminEditorSetMutationChanged($before !== $this->mutationState($updated));

        return $updated;
    }

    protected function adminEditorSavedNotificationTitle(): string
    {
        return 'Post saved';
    }

    /**
     * @return array{record:array<string,mixed>,media:list<array<string,mixed>>}
     */
    private function mutationState(BlogPost $post): array
    {
        /** @var BlogPost $fresh */
        $fresh = BlogPost::query()->findOrFail($post->getKey());
        $media = $fresh->mediaUsages()
            ->orderBy('id')
            ->get()
            ->map(static fn ($usage): array => $usage->getAttributes())
            ->values()
            ->all();

        return [
            'record' => $fresh->getAttributes(),
            'media' => $media,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(JournalWorkspace::getUrl([
            'section' => (int) $this->post()->getAttribute('site_section_id'),
        ]));
    }

    private function post(): BlogPost
    {
        /** @var BlogPost $record */
        $record = $this->getRecord();

        return $record;
    }
}
