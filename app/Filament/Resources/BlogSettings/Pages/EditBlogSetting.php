<?php

namespace App\Filament\Resources\BlogSettings\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Filament\Resources\BlogSettings\BlogSettingResource;
use App\Models\BlogSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class EditBlogSetting extends EditRecord
{
    protected static string $resource = BlogSettingResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('listing_intro', $data)) {
            throw ValidationException::withMessages(['listing_intro' => 'Blog settings form data is incomplete.']);
        }
        if (is_string($data['listing_intro']) && $data['listing_intro'] !== '') {
            app(SafeRichTextRenderer::class)->assertValid($data['listing_intro']);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var BlogSetting $record */
        return app(AdminSettingsService::class)->updateBlog($record, $data);
    }
}
