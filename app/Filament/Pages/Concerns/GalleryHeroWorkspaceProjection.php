<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\GalleryHeroCandidate;
use App\Domain\Content\GalleryHeroConfigurationService;
use App\Domain\Content\GalleryHeroResolver;
use App\Domain\Content\RegionalFormats;
use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\MediaPreviewVariant;
use App\Filament\Pages\GalleryWorkspace;
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\GalleryHeroSettingsDialog;
use App\Filament\Support\GalleryHeroDummyGalleryDialog;
use App\Filament\Support\ArtworkEditDialog;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\GalleryHeroSetting;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;

trait GalleryHeroWorkspaceProjection
{
    private function reloadWorkspace(bool $refreshPreview = true): void
    {
        $settings = $this->settings();
        $hero = app(GalleryHeroConfigurationService::class)->configuration($settings);
        $this->artworkShowDetails = $hero['show_details'];
        $this->artworkShowGalleryLink = $hero['show_gallery_link'];
        $this->artworkShowNavigation = $hero['show_navigation'];
        $this->artworkEnableViewer = $hero['enable_viewer'];
        $this->heroGroupSource = $hero['group_source'];
        $this->disabledAutoHeroIds = $hero['disabled_auto_artwork_ids'];
        $this->enabledGalleryIds = $hero['enabled_gallery_ids'];
        $this->excludedHeroRows = [];
        $this->heroDisplayStrategy = $hero['display_strategy'];
        $this->heroSourceRule = $hero['source_rule'];
        $this->heroSelectionMode = $hero['selection_mode'];
        $this->heroGroupSize = $hero['group_size'];
        $this->dummyGalleries = $hero['dummy_galleries'];
        $this->rotationIntervalCount = $hero['rotation_interval']['count'];
        $this->rotationIntervalUnit = $hero['rotation_interval']['unit'];
        $this->rotationStartedAt = $hero['rotation_started_at'];
        $this->selectionIssue = null;
        $this->currentArtwork = null;
        $this->heroRailRows = [];
        $this->eligibleArtworkCount = 0;
        $this->sourceGalleryCount = 0;
        $this->newestEligibleYear = null;
        $this->candidatePoolCount = 0;
        $this->nextRotationAt = null;
        $this->loadArtworkWorkspace($settings);
        $this->refreshMetrics();
        if ($refreshPreview) {
            $this->dispatch('admin-live-preview-updated', url: $this->previewUrl);
        }
    }

    private function loadArtworkWorkspace(GalleryHeroSetting $settings): void
    {
        $publicArtworks = app(PublicArtworkQuery::class);
        $statistics = $publicArtworks->heroCandidateStatistics($this->enabledGalleryIds);
        $this->eligibleArtworkCount = $statistics['eligible'];
        $this->newestEligibleYear = $statistics['newest_year'];
        $activeDummy = $this->heroGroupSource === 'automatic' ? [] : array_values(array_filter(
            $this->dummyGalleries, static fn (array $gallery): bool => $gallery['enabled'],
        ));
        $regularCount = $this->heroGroupSource === 'manual' ? 0
            : ArtworkCategory::query()->whereKey($this->enabledGalleryIds)
                ->whereHas('siteSection', fn (Builder $section): Builder => $section->where('state', 'published'))->count();
        $this->sourceGalleryCount = $regularCount + count($activeDummy);

        $resolver = app(GalleryHeroResolver::class);
        $resolution = $resolver->resolve($settings);
        $this->candidatePoolCount = $resolution['group']->count();
        $this->randomAvailable = $resolver->hasRandomAlternatives($resolution['group'], $resolution['weights']);
        if ($this->heroSelectionMode === 'per_gallery') {
            $reserved = $this->disabledAutoHeroIds;
            foreach ($activeDummy as $dummy) {
                foreach ($dummy['members'] as $member) {
                    if (isset($member['artwork_id'])) {
                        $reserved[] = (int) $member['artwork_id'];
                    }
                }
            }
            $regularEligible = $this->heroGroupSource === 'manual' ? 0
                : $publicArtworks->eligibleHeroGalleryCount($this->enabledGalleryIds, array_values(array_unique($reserved)));
            $regularSlots = $this->heroGroupSource === 'manual' ? 0
                : $publicArtworks->eligibleHeroCandidateSlotsPerGallery(
                    $this->enabledGalleryIds, 
                    $this->heroGroupSize, array_values(array_unique($reserved)),
                );
            $dummyPotential = 0;
            foreach ($activeDummy as $dummy) {
                $candidates = app(GalleryHeroResolver::class)->dummyCandidates(
                    array_values(array_filter($dummy['members'], static fn (array $member): bool => $member['enabled'])),
                    (int) $dummy['id'], (string) $dummy['name'],
                );
                $dummyPotential += min($candidates->count(), $this->heroGroupSize);
            }
            $potential = $dummyPotential + $regularSlots;
            if ($potential > GalleryHeroConfigurationService::MAX_GROUP_SIZE) {
                $this->selectionIssue = sprintf(
                    'Per Gallery requests up to %d candidates from %d eligible sources, exceeding the %d-candidate limit. The active group is capped at %d.',
                    $this->heroGroupSize,
                    $regularEligible + count($activeDummy),
                    GalleryHeroConfigurationService::MAX_GROUP_SIZE,
                    GalleryHeroConfigurationService::MAX_GROUP_SIZE,
                );
            }
        }
        $this->nextRotationAt = $resolution['next_rotation_at']?->toIso8601String();
        $current = $resolution['current'];
        if ($current instanceof GalleryHeroCandidate) {
            $this->currentArtwork = $this->candidateRow($current);
        } else {
            $this->selectionIssue ??= 'No eligible Hero candidate is available.';
        }

        if ($this->disabledAutoHeroIds !== []) {
            $disabled = $publicArtworks->heroCandidatesByIds($this->disabledAutoHeroIds);
            $stored = app(GalleryHeroConfigurationService::class)
                ->configuration($settings)['hero_artwork_overrides'];
            $candidates = $disabled->map(static fn (Artwork $artwork): GalleryHeroCandidate =>
                GalleryHeroCandidate::fromArtwork(
                    $artwork, (int) $artwork->getAttribute('artwork_category_id'),
                )
            );
            $this->excludedHeroRows = $resolver->applyHeroArtworkOverrides($candidates, $stored)
                ->map(function (GalleryHeroCandidate $candidate): array {
                    $row = $this->candidateRow($candidate);
                    $row['eligible'] = false;
                    $row['disabled'] = true;
                    $row['is_manual'] = false;
                    $row['is_automatic'] = true;
                    $row['weight'] = 0;
                    $row['percentage'] = '0.00';
                    $row['sequence_label'] = 'Excluded';

                    return $row;
                })->values()->all();
        }

        // The sequence displayed for editing is the saved rank, not today's
        // rotation offset. Only the Current/Next labels follow rotation.
        $sequence = $resolution['group'];
        $rotationKeys = $resolution['sequence']
            ->map(static fn (GalleryHeroCandidate $candidate): string => $candidate->key())->all();
        $currentId = $current instanceof GalleryHeroCandidate ? $current->key() : null;
        $nextWeights = $resolver->nextRandomWeights(
            $resolution['group'], $resolution['weights'],
            $current instanceof GalleryHeroCandidate ? $current->visualKey() : null,
        );
        $this->heroRailRows = $sequence->values()->map(function (GalleryHeroCandidate $candidate) use ($currentId, $resolution, $rotationKeys, $nextWeights): array {
            $row = $this->candidateRow($candidate);
            $row['eligible'] = true;
            $row['is_manual'] = $candidate->sourceId !== null && $candidate->sourceId < 0;
            $row['is_automatic'] = ! $row['is_manual'];
            $row['dummy_gallery_id'] = $row['is_manual'] ? $candidate->sourceId : null;
            $weight = $resolution['weights'][$candidate->key()] ?? 0;
            $row['weight'] = $weight;
            $row['percentage'] = number_format($weight / 100, 2, '.', '');
            $row['next_percentage'] = number_format(($nextWeights[$candidate->key()] ?? 0) / 100, 2, '.', '');
            $rotationRank = array_search($candidate->key(), $rotationKeys, true);
            $row['sequence_label'] = $this->heroDisplayStrategy === 'sequential'
                ? ($rotationRank === 0 ? 'Current' : ($rotationRank === 1 ? 'Next' : 'Then'))
                : ($row['id'] === $currentId ? 'Current' : null);

            return $row;
        })->all();

    }


    private function refreshMetrics(): void
    {
        $analyticsDescription = $this->analyticsDescription();
        $this->metrics = [
            ['role' => 'visits', 'label' => 'Visits | 30d', 'value' => $this->formatMetric($this->homeVisits), 'description' => $analyticsDescription],
            ['role' => 'views', 'label' => 'Views | 30d', 'value' => $this->formatMetric($this->homeViews), 'description' => $analyticsDescription],
            ['role' => 'sources', 'label' => 'Source Galleries', 'value' => number_format($this->sourceGalleryCount), 'description' => 'Effective sources'],
            ['role' => 'eligible', 'label' => 'Eligible Artworks', 'value' => number_format($this->eligibleArtworkCount), 'description' => 'Eligible candidates'],
            ['role' => 'candidates', 'label' => 'Candidate Group', 'value' => number_format($this->candidatePoolCount), 'description' => 'Effective group'],
            ['role' => 'year', 'label' => 'Newest Year', 'value' => $this->newestEligibleYear === null ? '—' : (string) $this->newestEligibleYear, 'description' => 'Eligible newest'],
        ];
    }

    private function candidateRow(GalleryHeroCandidate $candidate): array
    {
        $artwork = $candidate->artwork();
        $asset = $candidate->mediaAsset();
        $heroAsset = $candidate->heroMediaAsset;
        $gallery = $candidate->gallery();
        ($heroAsset ?? $asset)?->loadMissing('variants');
        $preview = ($heroAsset ?? $asset) instanceof MediaAsset
            ? MediaPreviewVariant::preview($heroAsset ?? $asset) : null;
        $date = $candidate->heroOverrides;
        $dateParts = array_key_exists('date', $date) ? ArtworkDate::normalize($date['date']) : null;

        return [
            'id' => $candidate->key(),
            'visual_key' => $candidate->visualKey(),
            'title' => $candidate->title(),
            'year' => $dateParts === null
                ? ($artwork?->getAttribute('year') ?? $candidate->year)
                : $dateParts['year'],
            'date' => $dateParts !== null
                ? ArtworkDate::displayParts($dateParts['year'], $dateParts['month'], $dateParts['day'], RegionalFormats::adminRegion())
                : ($artwork instanceof Artwork
                    ? ArtworkDate::display($artwork, RegionalFormats::adminRegion())
                    : ArtworkDate::displayParts($candidate->year, $candidate->month, $candidate->day, RegionalFormats::adminRegion())),
            'gallery' => $candidate->sourceName
                ?? ($gallery instanceof ArtworkCategory ? (string) $gallery->getAttribute('name') : null),
            'gallery_id' => $candidate->sourceId ?? $gallery?->getKey(),
            'thumbnail_url' => $heroAsset instanceof MediaAsset
                ? ($preview ? route('admin.media.variant', $preview) : null)
                : ($artwork instanceof Artwork
                    ? ArtworkResource::thumbnailUrl($artwork)
                    : ($preview ? route('admin.media.variant', $preview) : null)),
            'gallery_url' => $gallery instanceof ArtworkCategory
                ? GalleryWorkspace::getUrl(['gallery' => $gallery->getKey()])
                : null,
        ];
    }

    private function settings(): GalleryHeroSetting
    {
        /** @var GalleryHeroSetting $settings */
        $settings = GalleryHeroSetting::query()
            ->whereKey($this->settingsId)
            ->where('site_section_id', $this->sectionId)
            ->firstOrFail();
        GalleryHeroSetting::forSection($this->sectionId);

        return $settings;
    }

    private function clearSourceSelection(): void
    {
        $this->selectedSourceIds = [];
    }

    private function analyticsMetricValue(mixed $metric): ?float
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return null;
        }

        return (float) $metric['value'];
    }

    private function formatMetric(?float $value): string
    {
        if ($value === null) {
            return '—';
        }

        return number_format($value, $value === floor($value) ? 0 : 1);
    }

    private function analyticsDescription(): string
    {
        if (! $this->heroAnalyticsLoaded) {
            return 'Loading | 30d';
        }

        return match ($this->heroAnalyticsStatus) {
            'stale' => 'Cached | 30d',
            'available' => 'Hero page | 30d',
            default => 'Unavailable | 30d',
        };
    }
}
