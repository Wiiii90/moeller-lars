<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\CustomPageSetting;
use App\Models\HomePresentationSetting;

/**
 * General owns social profile identity. Removing a profile must also remove
 * its persisted Contact selections across both component storage formats.
 * Invoked inside the General mutation transaction, never from an editor.
 */
final class ContactSocialProfileCascade
{
    public function __construct(private readonly AdminAuditService $audit) {}

    /** @param list<string> $removed */
    public function removePlatforms(array $removed): void
    {
        if ($removed === []) {
            return;
        }

        $actor = $this->audit->requireActor();

        foreach (CustomPageSetting::query()->orderBy('id')->lockForUpdate()->get() as $page) {
            $before = $page->components();
            $after = $this->withoutPlatforms($before, $removed);
            if ($before === $after) {
                continue;
            }

            $page->setAttribute('blocks', $after);
            $page->save();
            $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $page->getAttribute('site_section_id'));
        }

        foreach (HomePresentationSetting::query()->orderBy('id')->lockForUpdate()->get() as $home) {
            $configuration = $home->configuration();
            $changed = false;

            // Inactive Home templates retain content too, so clean both.
            foreach ([HomeTemplate::UnderConstruction, HomeTemplate::Custom] as $template) {
                $mode = $configuration[$template->value] ?? null;
                if (! is_array($mode) || ! is_array($mode['components'] ?? null)) {
                    continue;
                }

                $before = $mode['components'];
                $after = $this->withoutPlatforms($before, $removed);
                if ($before === $after) {
                    continue;
                }

                $configuration[$template->value]['components'] = $after;
                $changed = true;
            }

            if (! $changed) {
                continue;
            }

            $home->setAttribute('configuration', $configuration);
            $home->save();
            $this->audit->record($actor, 'site_section.updated', 'site_section', (int) $home->getAttribute('site_section_id'));
        }
    }

    /**
     * Preserve everything except removed profile keys; keep component,
     * child, platform and publication order unchanged.
     *
     * @param list<array<string, mixed>> $components
     * @param list<string> $removed
     * @return list<array<string, mixed>>
     */
    private function withoutPlatforms(array $components, array $removed): array
    {
        foreach ($components as $index => $component) {
            if (! is_array($component)) {
                continue;
            }

            if (($component['type'] ?? null) === 'contact' && is_array($component['children'] ?? null)) {
                foreach ($component['children'] as $childIndex => $child) {
                    if (! is_array($child) || ($child['type'] ?? null) !== 'social_links'
                        || ! is_array($child['social_platforms'] ?? null)) {
                        continue;
                    }

                    $previous = $child['social_platforms'];
                    $selected = array_values(array_filter(
                        $previous,
                        static fn (mixed $platform): bool => ! in_array($platform, $removed, true),
                    ));

                    if ($selected !== $previous) {
                        $components[$index]['children'][$childIndex]['social_platforms'] = $selected;
                    }
                }
            }

            if (($component['type'] ?? null) === 'image' && is_array($component['children'] ?? null)) {
                $children = $this->withoutPlatforms($component['children'], $removed);
                if ($children !== $component['children']) {
                    $components[$index]['children'] = $children;
                }
            }
        }

        return $components;
    }
}
