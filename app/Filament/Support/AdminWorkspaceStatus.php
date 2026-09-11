<?php

namespace App\Filament\Support;

use App\Domain\Content\SiteNodeType;
use App\Domain\Publication\PublicationService;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use App\Models\PublicContentSetting;
use App\Models\SiteSection;
use Throwable;

final class AdminWorkspaceStatus
{
    /** @return array{label:string,tone:string}|null */
    public function resolve(string $title): ?array
    {
        try {
            $path = $this->currentPath();

            if ($path === '/admin' && $title === 'Dashboard') {
                return $this->homeStatus('Homepage healthy');
            }

            if ($path === '/admin/general' && $title === 'General') {
                $faviconId = PublicContentSetting::general()->getAttribute('favicon_media_asset_id');

                return filled($faviconId)
                    ? $this->status('Site identity ready', 'success')
                    : $this->status('Site icon missing', 'danger');
            }

            if ($path === '/admin/media-files' && $title === 'Media Files') {
                if (MediaAsset::query()->where('state', 'quarantined')->exists()) {
                    return $this->status('Quarantine pending', 'warning');
                }

                return MediaAsset::query()->where('state', 'available')->exists()
                    ? $this->status('Library ready', 'success')
                    : $this->status('Library empty', 'neutral');
            }

            if ($path === '/admin/pages' && $title === 'Pages') {
                $pages = SiteSection::query()->where('type', '<>', SiteNodeType::NavigationNode->value);
                $total = (clone $pages)->count();
                if ($total === 0) {
                    return $this->status('No pages', 'danger');
                }

                $unpublished = (clone $pages)->where('state', '<>', 'published')->count();

                return $unpublished > 0
                    ? $this->status($unpublished.' unpublished', 'warning')
                    : $this->status('All pages published', 'success');
            }

            if ($path === '/admin/pages/home' && $title === 'Home') {
                return $this->homeStatus('Homepage published');
            }

            if (preg_match('#^/admin/pages/gallery/(\d+)$#', $path, $matches) === 1) {
                $gallery = ArtworkCategory::query()
                    ->with('siteSection:id,artwork_category_id,state')
                    ->find((int) $matches[1]);
                $section = $gallery?->getRelationValue('siteSection');

                return $section instanceof SiteSection && $section->getAttribute('state') === 'published'
                    ? $this->status('Gallery published', 'success')
                    : $this->status('Gallery unpublished', 'warning');
            }

            if (preg_match('#^/admin/pages/custom/(\d+)$#', $path, $matches) === 1) {
                return $this->sectionStatus((int) $matches[1], 'Page');
            }

            if (preg_match('#^/admin/pages/journal/(\d+)$#', $path, $matches) === 1) {
                return $this->sectionStatus((int) $matches[1], 'Journal');
            }

            if ($path === '/admin/activity' && $title === 'Activity') {
                return app(PublicationService::class)->hasPendingChanges()
                    ? $this->status('Changes staged', 'warning')
                    : $this->status('Clean', 'success');
            }
        } catch (Throwable $exception) {
            report($exception);

            return $this->status('Status unavailable', 'danger');
        }

        return null;
    }

    /** @return array{label:string,tone:string} */
    private function homeStatus(string $healthyLabel): array
    {
        $home = SiteSection::query()
            ->where('type', SiteNodeType::Home->value)
            ->first(['state']);

        return $home instanceof SiteSection && $home->getAttribute('state') === 'published'
            ? $this->status($healthyLabel, 'success')
            : $this->status('Homepage unavailable', 'danger');
    }

    /** @return array{label:string,tone:string} */
    private function sectionStatus(int $sectionId, string $label): array
    {
        $section = SiteSection::query()->find($sectionId, ['state']);

        return $section instanceof SiteSection && $section->getAttribute('state') === 'published'
            ? $this->status($label.' published', 'success')
            : $this->status($label.' unpublished', 'warning');
    }

    /** @return array{label:string,tone:string} */
    private function status(string $label, string $tone): array
    {
        return ['label' => $label, 'tone' => $tone];
    }

    private function currentPath(): string
    {
        $path = request()->getPathInfo();

        if (str_starts_with($path, '/livewire/')) {
            $referer = request()->headers->get('referer');
            $refererPath = is_string($referer) ? parse_url($referer, PHP_URL_PATH) : null;
            if (is_string($refererPath) && $refererPath !== '') {
                $path = $refererPath;
            }
        }

        $path = rtrim($path, '/');

        return $path === '' ? '/' : $path;
    }
}
