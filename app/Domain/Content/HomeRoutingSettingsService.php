<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Models\HomePresentationSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class HomeRoutingSettingsService
{
    private const ROUTING_KEY = 'routing';

    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SiteNodeRoute $routes,
    ) {}

    /** @return array{skip_home:bool,skip_target_section_id:?int} */
    public function configuration(HomePresentationSetting $settings): array
    {
        $root = $settings->getAttribute('template_settings');
        $root = is_array($root) ? $root : [];
        $routing = is_array($root[self::ROUTING_KEY] ?? null) ? $root[self::ROUTING_KEY] : [];
        $targetId = filter_var($routing['skip_target_section_id'] ?? null, FILTER_VALIDATE_INT);

        return [
            // Legacy rows used Skip Home as a template. Keep those rows behaving
            // exactly as before until the next settings write normalizes them.
            'skip_home' => (bool) ($routing['skip_home'] ?? false)
                || $settings->template() === HomeTemplate::SkipHome,
            'skip_target_section_id' => $targetId === false || $targetId <= 0 ? null : (int) $targetId,
        ];
    }

    public function enabled(HomePresentationSetting $settings): bool
    {
        return $this->configuration($settings)['skip_home'];
    }

    public function configuredTargetId(HomePresentationSetting $settings): ?int
    {
        return $this->configuration($settings)['skip_target_section_id'];
    }

    public function update(HomePresentationSetting $settings, bool $enabled, ?int $targetSectionId): bool
    {
        if ($targetSectionId !== null) {
            $this->validateTarget($targetSectionId);
        }

        return DB::transaction(function () use ($settings, $enabled, $targetSectionId): bool {
            /** @var HomePresentationSetting $fresh */
            $fresh = HomePresentationSetting::query()
                ->whereKey($settings->getKey())
                ->lockForUpdate()
                ->firstOrFail();

            $root = $fresh->getAttribute('template_settings');
            $root = is_array($root) ? $root : [];
            $root[self::ROUTING_KEY] = [
                'skip_home' => $enabled,
                'skip_target_section_id' => $targetSectionId,
            ];
            $fresh->setAttribute('template_settings', $root);

            // Normalize the old fourth-template representation without losing
            // its behaviour. Hero Artwork is the legacy row's content fallback.
            if ($fresh->template() === HomeTemplate::SkipHome) {
                $fresh->setAttribute('template', HomeTemplate::Artwork->value);
            }

            if (! $fresh->isDirty(['template', 'template_settings'])) {
                return false;
            }

            $fresh->save();
            $actor = $this->audit->requireActor();
            $this->audit->record(
                $actor,
                'site_section.updated',
                'site_section',
                (int) $fresh->getAttribute('site_section_id'),
            );

            return true;
        });
    }

    private function validateTarget(int $targetSectionId): void
    {
        /** @var SiteSection|null $target */
        $target = SiteSection::query()->find($targetSectionId);
        if (! $target instanceof SiteSection
            || $target->getAttribute('parent_id') !== null
            || $target->nodeType() === SiteNodeType::Home
            || $target->nodeType() === SiteNodeType::NavigationNode
            || (string) $target->getAttribute('state') !== 'published'
            || ! $target->nodeType()->hasPublicPage()
            || $this->routes->path($target) === null) {
            throw ValidationException::withMessages([
                'skip_target_section_id' => 'Choose a published top-level page with a public route.',
            ]);
        }
    }
}
