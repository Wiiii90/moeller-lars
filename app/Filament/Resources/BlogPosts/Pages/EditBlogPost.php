<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\JournalEntryMediaService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Pages\JournalWorkspace;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditBlogPost extends EditRecord
{
    use UsesAdminEditor;
    protected static string $resource = BlogPostResource::class;

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [...$data, ...app(JournalEntryMediaService::class)->editorState($this->post())];
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
        return app(BlogEditorialService::class)->update($record, $data);
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(JournalWorkspace::getUrl(['section' => (int) $this->post()->getAttribute('site_section_id')]));
    }

    private function post(): BlogPost
    {
        /** @var BlogPost $record */
        $record = $this->getRecord();
        return $record;
    }
}
