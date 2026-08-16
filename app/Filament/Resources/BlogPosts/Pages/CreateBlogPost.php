<?php

namespace App\Filament\Resources\BlogPosts\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

final class CreateBlogPost extends CreateRecord
{
    protected static string $resource = BlogPostResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        return app(BlogEditorialService::class)->create($data);
    }
}
