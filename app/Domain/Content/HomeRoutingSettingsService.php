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
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly SiteNodeRoute $routes,
    ) {}

    /** @return array{skip_home:bool,skip_target_section_id:?int} */
    public function configuration(HomePresentationSetting $settings): array
    {
        $targetId = filter_var($settings->getAttribute('skip_target_section_id'), FILTER_VALIDATE_INT);

        return [
            // Keep pre-migration rows readable while rolling deployments cross
            // the schema boundary. The migration normalizes Skip Home to Artwork.
            'skip_home' => (bool) $settings->getAttribute('skip_home')
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

            $fresh->setAttribute('skip_home', $enabled);
            $fresh->setAttribute('skip_target_section_id', $enabled ? $targetSectionId : null);

            // Normalize the old fourth-template representation without losing
            // its routing behaviour. Hero Artwork is the legacy content fallback.
            if ($fresh->template() === HomeTemplate::SkipHome) {
                $fresh->setAttribute('template', HomeTemplate::Artwork->value);
                $fresh->setAttribute('skip_home', true);
            }

            if (! $fresh->isDirty(['template', 'skip_home', 'skip_target_section_id'])) {
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
            || $target->nodeType() === SiteSectionType::Home
            || $target->nodeType() === SiteSectionType::NavigationNode
            || (string) $target->getAttribute('state') !== 'published'
            || ! $target->nodeType()->hasPublicPage()
            || $this->routes->path($target) === null) {
            throw ValidationException::withMessages([
                'skip_target_section_id' => 'Choose a published top-level page with a public route.',
            ]);
        }
    }
}
