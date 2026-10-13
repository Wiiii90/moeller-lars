<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Filament\Pages\CustomPageWorkspace;
use App\Http\Controllers\PublicSiteSectionController;
use App\Models\CustomPageSetting;
use App\Models\Redirect;
use App\Models\SiteSection;
use App\Models\User;
use App\Routing\SiteNodeRoute;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;


function customPageWorkspaceRecord(string $title = 'Custom Page test', string $slug = 'custom-page-test'): array
{
    $section = app(SiteSectionEditorialService::class)->createCustomPage($title, $slug);

    /** @var CustomPageSetting $settings */
    $settings = CustomPageSetting::query()
        ->where('site_section_id', $section->getKey())
        ->firstOrFail();

    return [$section, $settings];
}

/** @return list<array<string,mixed>> */
function customPageWorkspaceBlocks(int $count): array
{
    $blocks = [];

    for ($index = 0; $index < $count; $index++) {
        $items = [];
        if ($index === 24) {
            for ($child = 0; $child < 4; $child++) {
                $items[] = [
                    'published' => true,
                    'date' => '202'.($child + 1),
                    'title' => 'Boundary child '.($child + 1),
                ];
            }
        } elseif ($index === $count - 1) {
            $items[] = [
                'published' => true,
                'date' => '2030',
                'title' => 'Last child',
            ];
        }

        $blocks[] = [
            'type' => 'list',
            'published' => true,
            'title' => 'Component '.($index + 1),
            'items' => $items,
        ];
    }

    return $blocks;
}
