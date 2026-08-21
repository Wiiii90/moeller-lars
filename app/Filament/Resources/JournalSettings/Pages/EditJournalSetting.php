<?php

namespace App\Filament\Resources\JournalSettings\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\JournalSettings\JournalSettingResource;
use App\Models\JournalSetting;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

final class EditJournalSetting extends EditRecord
{
    protected static string $resource = JournalSettingResource::class;

    public function getBreadcrumbs(): array
    {
        return ['Journal settings'];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('pages')
                ->label('Back to Pages')
                ->url(SitePages::getUrl()),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! array_key_exists('listing_intro', $data)) {
            throw ValidationException::withMessages(['listing_intro' => 'Journal settings form data is incomplete.']);
        }
        if (is_string($data['listing_intro']) && $data['listing_intro'] !== '') {
            app(SafeRichTextRenderer::class)->assertValid($data['listing_intro']);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var JournalSetting $record */
        return app(AdminSettingsService::class)->updateJournal($record, $data);
    }
}
