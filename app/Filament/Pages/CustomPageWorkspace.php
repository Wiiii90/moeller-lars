<?php

namespace App\Filament\Pages;

use App\Domain\Admin\EditorialRecordService;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\CustomPageSettings\CustomPageSettingResource;
use App\Models\CustomPageSetting;
use App\Models\CvEntry;
use App\Models\SiteSection;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;

final class CustomPageWorkspace extends Page
{
    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pages/custom/{section}';

    protected static ?string $title = 'Custom Page';

    protected string $view = 'filament.pages.custom-page-workspace';

    public int $sectionId;

    public int $settingsId;

    /** @var array<string, mixed> */
    public ?array $data = [];

    public function mount(int|string $section): void
    {
        /** @var SiteSection $siteSection */
        $siteSection = SiteSection::query()
            ->whereKey((int) $section)
            ->where('type', SiteNodeType::CustomPage->value)
            ->firstOrFail();
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()
            ->where('site_section_id', $siteSection->getKey())
            ->firstOrFail();

        $this->sectionId = (int) $siteSection->getKey();
        $this->settingsId = (int) $settings->getKey();
        $this->form->fill(['blocks' => $settings->components()]);
    }

    public function form(Schema $schema): Schema
    {
        return CustomPageSettingResource::form($schema)
            ->statePath('data')
            ->model($this->settings());
    }

    public function save(): void
    {
        $settings = $this->settings();
        $settings->fill($this->form->getState());
        if ($settings->isDirty()) {
            $settings->save();
        }

        Notification::make()->title('Custom Page saved')->success()->send();
    }

    public function getBreadcrumbs(): array
    {
        return [];
    }

    public function getHeading(): string
    {
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($this->sectionId);

        return (string) ($section->getAttribute('title') ?: $section->getAttribute('navigation_label') ?: 'Custom Page');
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

    private function settings(): CustomPageSetting
    {
        /** @var CustomPageSetting $settings */
        $settings = CustomPageSetting::query()->findOrFail($this->settingsId);

        return $settings;
    }
}
