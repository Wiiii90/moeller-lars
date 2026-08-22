<?php

namespace App\Filament\Resources\CustomPageSettings\Pages;

use App\Domain\Admin\EditorialRecordService;
use App\Domain\Content\SitePreviewContext;
use App\Filament\Concerns\UsesAdminEditor;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Models\CvEntry;
use App\Models\CustomPageSetting;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

final class EditCustomPageSetting extends EditRecord
{
    use UsesAdminEditor;

    protected static string $resource = CustomPageSettingResource::class;

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string
    {
        $section = $this->pageSection();

        return $section instanceof SiteSection
            ? (string) ($section->getAttribute('title') ?: $section->getAttribute('navigation_label') ?: 'Custom Page')
            : 'Custom Page';
    }

    public function moveCvEntry(int $entryId, string $direction): void
    {
        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        app(EditorialRecordService::class)->move($entry, $direction);
    }

    public function removeCvEntry(int $entryId): void
    {
        /** @var CvEntry $entry */
        $entry = CvEntry::query()->findOrFail($entryId);
        app(EditorialRecordService::class)->deleteCv($entry);

        Notification::make()
            ->success()
            ->title('CV entry removed')
            ->body('The referenced Media asset was kept.')
            ->send();
    }

    protected function getHeaderActions(): array
    {
        $previewUrl = ($section = $this->pageSection()) instanceof SiteSection
            ? app(SitePreviewContext::class)->previewUrlFor($section)
            : null;

        return [
            Action::make('pages')
                ->label('Pages')
                ->url(SitePages::getUrl()),
            Action::make('preview')
                ->label('Public Preview')
                ->url($previewUrl)
                ->openUrlInNewTab()
                ->visible(is_string($previewUrl) && $previewUrl !== ''),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->editorReturnUrl(SitePages::getUrl());
    }

    private function pageSection(): ?SiteSection
    {
        $record = $this->record;
        if (! $record instanceof CustomPageSetting) {
            return null;
        }

        $record->loadMissing('siteSection');
        $section = $record->getRelation('siteSection');

        return $section instanceof SiteSection ? $section : null;
    }
}
