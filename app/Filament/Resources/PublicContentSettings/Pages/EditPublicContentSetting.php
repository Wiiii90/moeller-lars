<?php

namespace App\Filament\Resources\PublicContentSettings\Pages;

use App\Domain\Admin\AdminSettingsService;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\PublicContentSetting;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Throwable;

class EditPublicContentSetting extends EditRecord
{
    private const AUTOSAVE_FIELDS = [
        'favicon_media_asset_id',
        'public_email',
        'show_public_email',
        'contact_recipient_email',
        'social_links',
        'default_media_copyright_notice',
        'legal_disclaimer',
    ];

    protected static string $resource = PublicContentSettingResource::class;

    protected string $view = 'filament.resources.public-content-settings.pages.edit-public-content-setting';

    public function getHeading(): string
    {
        return 'General';
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    /** @return array<\Filament\Actions\Action> */
    protected function getFormActions(): array
    {
        return [];
    }

    public function autosaveField(string $field): void
    {
        if (! in_array($field, self::AUTOSAVE_FIELDS, true) || ! is_array($this->data) || ! array_key_exists($field, $this->data)) {
            return;
        }

        $this->clearAutosaveErrors($field);

        $record = $this->getRecord();
        if (! $record instanceof PublicContentSetting) {
            return;
        }

        try {
            app(AdminSettingsService::class)->updatePublicContent($record, [
                $field => $this->data[$field],
            ]);
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $key => $messages) {
                $errorKey = str_starts_with($key, 'data.') ? $key : 'data.'.$key;
                foreach ($messages as $message) {
                    $this->addError($errorKey, $message);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            $this->addError('data.'.$field, 'This setting could not be saved. Please try again.');
        }
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var PublicContentSetting $record */
        return app(AdminSettingsService::class)->updatePublicContent($record, $data);
    }

    private function clearAutosaveErrors(string $field): void
    {
        $prefix = 'data.'.$field;

        foreach ($this->getErrorBag()->keys() as $key) {
            if ($key === $prefix || str_starts_with($key, $prefix.'.')) {
                $this->resetErrorBag($key);
            }
        }
    }
}
