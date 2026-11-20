<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Filament\Pages\Concerns\GalleryHeroWorkspaceDummyActions;
use App\Filament\Pages\Concerns\GalleryHeroWorkspaceSources;
use App\Filament\Pages\Concerns\GalleryHeroWorkspaceProjection;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Artwork\ArtworkDate;
use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Content\GalleryHeroCandidate;
use App\Domain\Content\GalleryHeroConfigurationService;
use App\Domain\Content\GalleryHeroResolver;
use App\Domain\Content\GalleryTemplate;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Media\MediaPreviewVariant;
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

final class GalleryHeroWorkspace extends Page
{
    use GalleryHeroWorkspaceDummyActions;
    use GalleryHeroWorkspaceSources;
    use GalleryHeroWorkspaceProjection;

    protected static bool $shouldRegisterNavigation = false;
    protected static ?string $slug = 'pages/gallery/hero/{section}';
    protected static ?string $title = 'Hero Artworks';
    protected string $view = 'filament.pages.gallery-hero-workspace';

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getCurrentPanel());
    }

    #[Locked]
    public int $sectionId = 0;
    #[Locked]
    public int $settingsId = 0;

    public string $pageTitle = 'Hero Artworks';
    public string $previewUrl = '';
    /** @var list<array{role:string,label:string,value:string,description:string}> */
    public array $metrics = [];
    public bool $artworkShowDetails = true;
    public bool $artworkShowGalleryLink = false;
    public bool $artworkShowNavigation = false;
    public bool $artworkEnableViewer = true;
    public string $heroGroupSource = 'automatic';
    /** @var list<int> */
    public array $disabledAutoHeroIds = [];
    /** @var list<int> */
    public array $enabledGalleryIds = [];
    /** @var list<array<string,mixed>> */
    public array $excludedHeroRows = [];
    public string $heroDisplayStrategy = 'ordered';
    public bool $randomAvailable = false;
    public string $heroSourceRule = 'newest_artwork';
    public string $heroSelectionMode = 'global';
    public int $heroGroupSize = GalleryHeroConfigurationService::DEFAULT_GROUP_SIZE;
    /** @var list<array<string,mixed>> */
    public array $dummyGalleries = [];
    /** @var list<array<string,mixed>> */
    public array $draftDummyMembers = [];
    public int $rotationIntervalCount = 1;
    public string $rotationIntervalUnit = 'weeks';
    public ?string $rotationStartedAt = null;
    public ?string $nextRotationAt = null;
    /** @var array<string,mixed>|null */
    public ?array $currentArtwork = null;
    /** @var list<array<string,mixed>> */
    public array $heroRailRows = [];
    public int $candidatePoolCount = 0;
    public ?string $selectionIssue = null;
    public int $eligibleArtworkCount = 0;
    public int $sourceGalleryCount = 0;
    public ?int $newestEligibleYear = null;
    public string $sourceSearch = '';
    public int $sourcePage = 1;
    #[Session]
    public int $sourcePerPage = 10;
    /** @var list<int|string> */
    public array $selectedSourceIds = [];
    /** @var list<int> */
    public array $expandedSourceIds = [];
    public bool $heroAnalyticsLoaded = false;
    public string $heroAnalyticsStatus = 'loading';
    public ?float $homeVisits = null;
    public ?float $homeViews = null;

    public function getTitle(): string
    {
        return $this->pageTitle;
    }

    public function mount(int|string $section): void
    {
        /** @var SiteSection $page */
        $page = SiteSection::query()->findOrFail((int) $section);
        $settings = GalleryHeroSetting::forSection($page);
        $this->sectionId = (int) $page->getKey();
        $this->settingsId = (int) $settings->getKey();
        $this->pageTitle = (string) $page->getAttribute('title');
        $this->previewUrl = app(SitePreviewContext::class)->previewUrlFor($page) ?? '';
        $this->reloadWorkspace();
    }

    public function loadHeroAnalytics(): void
    {
        if ($this->heroAnalyticsLoaded) {
            return;
        }
        $this->heroAnalyticsLoaded = true;
        /** @var SiteSection $section */
        $section = SiteSection::query()->findOrFail($this->sectionId);
        $path = app(\App\Routing\SiteNodeRoute::class)->path($section) ?? '/';
        $report = app(ArtistReportingService::class)->customPage($path, '30d');
        $this->heroAnalyticsStatus = (string) ($report['status'] ?? 'unavailable');
        $this->homeVisits = $this->analyticsMetricValue($report['page']['visits'] ?? null);
        $this->homeViews = $this->analyticsMetricValue($report['page']['views'] ?? null);
        $this->refreshMetrics();
    }

    public function updatedSourceSearch(): void
    {
        $this->sourcePage = 1;
        $this->clearSourceSelection();
    }

    public function updatedSourcePerPage(): void
    {
        if (! in_array($this->sourcePerPage, [10, 25], true)) {
            $this->sourcePerPage = 10;
        }
        $this->sourcePage = 1;
        $this->clearSourceSelection();
    }

    public function resetSourceFilters(): void
    {
        $this->sourceSearch = '';
        $this->sourcePage = 1;
        $this->clearSourceSelection();
    }

    public function goToSourcePage(int $page): void
    {
        $this->sourcePage = max(1, $page);
        $this->clearSourceSelection();
    }


    public function settingsAction(): Action
    {
        $dialog = app(GalleryHeroSettingsDialog::class);
        $action = Action::make('settings')
            ->label('Settings')
            ->modalHeading('Hero settings')
            ->fillForm(fn (): array => $dialog->fill($this->settings()))
            ->schema(fn (): array => $dialog->schema(
                $this->settings(),
                fn (string $field, bool $value) => $this->persistHeroDisplayToggle($field, $value),
            ))
            ->action(function (array $data) use ($dialog): void {
                $previousSource = $this->heroGroupSource;
                $changed = $dialog->save($this->settings(), $data);
                if (($data['group_source'] ?? $previousSource) !== $previousSource) {
                    $this->sourcePage = 1;
                    $this->expandedSourceIds = [];
                    $this->clearSourceSelection();
                }
                $this->reloadWorkspace();

                if ($changed) {
                    app(AdminNotifier::class)->notification(
                        title: 'Hero settings saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    private function persistHeroDisplayToggle(string $field, bool $value): void
    {
        if (! in_array($field, [
            'show_details', 'show_gallery_link', 'show_navigation', 'enable_viewer',
            'video_sound_enabled', 'show_media_credits', 'show_media_copyright',
        ], true)) {
            throw new \InvalidArgumentException('Unsupported Hero display setting.');
        }

        // One existing audited mutation owner; no parallel Livewire/DB writer.
        $changed = app(GalleryHeroConfigurationService::class)->updateArtworkSettings(
            $this->settings(), [$field => $value],
        );
        if (! $changed) {
            return;
        }

        $this->reloadWorkspace();
        app(AdminNotifier::class)->notification(
            title: 'Hero settings saved',
            status: 'success',
        );
    }

}
