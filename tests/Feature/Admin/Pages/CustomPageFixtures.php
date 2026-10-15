<?php

use App\Domain\Content\SiteSectionEditorialService;
use App\Models\CustomPageSetting;


function customPageWorkspaceRecord(string $title = 'Custom Page test', string $slug = 'custom-page-test'): array
{
    $section = app(SiteSectionEditorialService::class)->createCustomPage($title, $slug);

    /** @var CustomPageSetting $settings */
    $settings = CustomPageSetting::query()
        ->where('site_section_id', $section->getKey())
        ->firstOrFail();

    return [$section, $settings];
}

function customPageWorkspaceImage(string $suffix): \App\Models\MediaAsset
{
    return \App\Models\MediaAsset::query()->create([
        'storage_key' => 'originals/'.$suffix.'.jpg', 'original_filename' => $suffix.'.jpg',
        'mime_type' => 'image/jpeg', 'byte_size' => 4, 'sha256' => hash('sha256', $suffix),
        'state' => 'available', 'alt_text' => 'Artist portrait', 'width' => 2, 'height' => 2,
    ]);
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
                    'context' => '202'.($child + 1),
                    'title' => 'Boundary child '.($child + 1),
                ];
            }
        } elseif ($index === $count - 1) {
            $items[] = [
                'published' => true,
                'context' => '2030',
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
