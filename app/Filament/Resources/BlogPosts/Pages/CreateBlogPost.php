<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateBlogPost extends CreateRecord
{
    use UsesAdminEditor;

    protected static string $resource = BlogPostResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BlogEditorialService::class)->createDraft($data);
    }

    protected function getRedirectUrl(): string
    {
        $sectionId = (int) $this->getRecord()->getAttribute('site_section_id');

        return $this->editorReturnUrl(BlogPostResource::getUrl('index', ['section' => $sectionId]));
    }

    protected function getCreatedNotification(): Notification
    {
        return Notification::make()
            ->success()
            ->title('Blog draft created')
            ->body('The post is private until you explicitly publish it or schedule a future publication.');
    }
}
