<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Models\BlogPost;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

final class EditBlogPost extends EditRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BlogPost $record */
        return app(BlogEditorialService::class)->update($record, $data);
    }
}
