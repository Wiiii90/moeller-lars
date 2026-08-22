<?php

namespace App\Filament\Widgets;

use App\Domain\Contact\ContactDeliveryReadiness;
use App\Domain\Content\SiteNodeType;
use App\Filament\Pages\SitePages;
use App\Filament\Resources\PublicContentSettings\PublicContentSettingResource;
use App\Models\CustomPageSetting;
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
        $delivery = app(ContactDeliveryReadiness::class)->snapshot();
        $placements = $this->placements();

        $formState = match (true) {
            in_array('enabled', $placements['states'], true) => 'Enabled',
            in_array('under_construction', $placements['states'], true) => 'Under construction',
            default => 'Hidden',
        };

        return [
            'publishedPlacements' => $placements['published'],
            'formPlacements' => $placements['forms'],
            'formState' => $formState,
            'delivery' => $delivery,
            'generalUrl' => PublicContentSettingResource::getNavigationUrl(),
            'pagesUrl' => SitePages::getUrl(),
        ];
    }

    /** @return array{published:int,forms:int,states:list<string>} */
    private function placements(): array
    {
        $published = 0;
        $forms = 0;
        $states = [];

        $settings = CustomPageSetting::query()
            ->whereHas('siteSection', static fn ($query) => $query
                ->where('type', SiteNodeType::CustomPage->value)
                ->where('state', 'published'))
            ->get(['blocks']);

        foreach ($settings as $pageSettings) {
            $pageHasContact = false;
            foreach ($pageSettings->components() as $block) {
                if (($block['type'] ?? null) !== 'contact') {
                    continue;
                }

                $pageHasContact = true;
                if (($block['show_form'] ?? true) === true) {
                    $forms++;
                    $states[] = is_string($block['form_state'] ?? null) ? $block['form_state'] : 'enabled';
                }
            }

            if ($pageHasContact) {
                $published++;
            }
        }

        return ['published' => $published, 'forms' => $forms, 'states' => $states];
    }
}
