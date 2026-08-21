<?php

namespace App\Filament\Widgets;

use App\Domain\Contact\ContactDeliveryReadiness;
use App\Filament\Resources\ContactContentSettings\ContactContentSettingResource;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Filament\Widgets\Widget;

final class ContactHealth extends Widget
{
    protected string $view = 'filament.widgets.contact-health';

    protected static ?int $sort = 2;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    /** @return array<string, mixed> */
    protected function getViewData(): array
    {
        $section = SiteSection::query()->where('type', SiteSection::TYPE_CONTACT)->first();
        $contact = PublicContentSetting::contact();
        $delivery = app(ContactDeliveryReadiness::class)->snapshot();

        $pageState = $section?->getAttribute('state') === 'published' ? 'Published' : 'Hidden';
        $formState = match ($contact->getAttribute('contact_state')) {
            'enabled' => 'Enabled',
            'under_construction' => 'Under construction',
            default => 'Hidden',
        };

        return [
            'pageState' => $pageState,
            'formState' => $formState,
            'delivery' => $delivery,
            'generalUrl' => PublicContentSettingResource::getNavigationUrl(),
            'contactUrl' => ContactContentSettingResource::getSettingsUrl(),
        ];
    }
}
