<?php

namespace App\Filament\Widgets;

use App\Domain\Contact\ContactDeliveryReadiness;
use App\Domain\Content\SiteSectionType;
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
                ->where('type', SiteSectionType::CustomPage->value)
                ->where('state', 'published'))
            ->get(['blocks']);

        foreach ($settings as $pageSettings) {
            $pageHasPublishedContact = false;

            foreach ($pageSettings->components() as $block) {
                if (($block['type'] ?? null) !== 'contact' || ! CustomPageSetting::componentPublished($block)) {
                    continue;
                }

                $pageHasPublishedContact = true;

                foreach ($pageSettings->contactChildren($block) as $child) {
                    if (($child['type'] ?? null) !== 'contact_form' || ! CustomPageSetting::contactChildPublished($child)) {
                        continue;
                    }

                    $forms++;
                    $states[] = ($child['form_state'] ?? 'enabled') === 'under_construction'
                        ? 'under_construction'
                        : 'enabled';
                }
            }

            if ($pageHasPublishedContact) {
                $published++;
            }
        }

        return ['published' => $published, 'forms' => $forms, 'states' => $states];
    }
}
