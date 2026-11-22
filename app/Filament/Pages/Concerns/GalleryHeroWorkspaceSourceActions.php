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
use App\Filament\Resources\Artworks\ArtworkResource;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\GalleryHeroSettingsDialog;
use App\Filament\Support\GalleryHeroDummyGalleryDialog;
use App\Filament\Support\ArtworkEditDialog;
use App\Filament\Support\GalleryHeroArtworkDialog;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Controls\AdminControl;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\GalleryHeroSetting;
use App\Models\MediaAsset;
use App\Models\SiteSection;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Schemas\Components\View;
use Filament\Facades\Filament;
use Filament\Pages\Page;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;

trait GalleryHeroWorkspaceSourceActions
{
    /** The Create dialog stages its gallery name and members in one commit. */
    public function addDummyGalleryAction(): Action
    {
        return AdminDialog::create(
            Action::make('addDummyGallery')
                ->label('Add Dummy Gallery')
                ->modalHeading('Add Dummy Gallery')
                ->fillForm(function (): array {
                    $this->draftDummyMembers = [];
                    return ['name' => '', 'pending_candidate' => null];
                })
                ->schema(fn (): array => app(GalleryHeroDummyGalleryDialog::class)->schema(0, true))
                ->action(function (array $data, Action $action): void {
                    $created = app(GalleryHeroConfigurationService::class)->createDummyGallery(
                        $this->settings(), (string) ($data['name'] ?? ''), $this->draftDummyMembers,
                    );
                    if ($created === null) {
                        $action->halt();
                        return;
                    }
                    $this->draftDummyMembers = [];
                    $this->reloadWorkspace();
                    app(AdminNotifier::class)->notification(title: 'Dummy Gallery added', status: 'success');
                }),
            'Add Dummy Gallery',
            AdminDialogSize::Large,
            onClear: fn (self $livewire) => $livewire->clearDraftDummyGallery(),
        );
    }

    public function clearDraftDummyGallery(): void
    {
        $this->draftDummyMembers = [];
    }

    /**
     * Edit this source's Hero version, never the original Artwork or Gallery.
     * The normal ArtworkEditDialog is deliberately not used for persistence.
     */
    public function editHeroArtworkAction(): Action
    {
        $dialog = app(GalleryHeroArtworkDialog::class);
        $service = app(GalleryHeroConfigurationService::class);

        return AdminDialog::edit(
            Action::make('editHeroArtwork')
                ->label('Edit')
                ->modalHeading('Edit Hero Artwork')
                ->fillForm(function (array $arguments) use ($dialog, $service): array {
                    $sourceId = (int) ($arguments['gallery'] ?? 0);
                    $artwork = $this->heroArtworkForAction($arguments);

                    return $dialog->fill(
                        $artwork,
                        $service->heroArtworkOverridesFor($this->settings(), $sourceId, (int) $artwork->getKey()),
                    );
                })
                ->schema(fn (): array => $dialog->schema())
                ->action(function (array $arguments, array $data) use ($service): void {
                    $sourceId = (int) ($arguments['gallery'] ?? 0);
                    $artwork = $this->heroArtworkForAction($arguments);
                    $changed = $service->saveHeroArtworkOverrides(
                        $this->settings(), $sourceId, (int) $artwork->getKey(), $data,
                    );
                    if ($changed) {
                        $this->reloadWorkspace();
                        app(AdminNotifier::class)->notification(
                            title: 'Hero Artwork version saved', status: 'success',
                        );
                    }
                }),
            AdminDialogSize::Large,
        );
    }

    private function heroArtworkForAction(array $arguments): Artwork
    {
        $sourceId = (int) ($arguments['gallery'] ?? 0);
        $artworkId = (int) ($arguments['artwork'] ?? 0);
        if ($sourceId === 0 || $artworkId < 1) {
            throw ValidationException::withMessages(['artwork' => 'Choose a Hero source Artwork.']);
        }

        return app(GalleryHeroConfigurationService::class)->sourceArtworkForHero(
            $this->settings(), $sourceId, $artworkId,
        );
    }

    /** Edit only this Hero page's regular Gallery source, never Gallery metadata. */
    public function editRegularHeroGalleryAction(): Action
    {
        return AdminDialog::edit(
            Action::make('editRegularHeroGallery')
                ->label('Edit')
                ->modalHeading('Edit Hero Gallery')
                ->fillForm(function (array $arguments): array {
                    $gallery = $this->regularHeroGalleryForAction($arguments);

                    return [
                        'gallery_name' => (string) $gallery->getAttribute('name'),
                    ];
                })
                ->schema(function (array $arguments): array {
                    $gallery = $this->regularHeroGalleryForAction($arguments);
                    $galleryId = (int) $gallery->getKey();

                    return [
                        AdminControl::text('gallery_name', 'Gallery')
                            ->readOnly()
                            ->dehydrated(false)
                            ->columnSpanFull(),
                        View::make('filament.pages.partials.gallery-hero-source-members')
                            ->viewData(fn (): array => [
                                'members' => $this->regularHeroGalleryMembers($galleryId),
                                'galleryId' => $galleryId,
                                'creating' => false,
                                'regularSource' => true,
                            ])
                            ->columnSpanFull(),
                    ];
                })
                ->extraModalFooterActions(fn (array $arguments): array => [
                    $this->heroSourcePublicationHeaderAction((int) ($arguments['gallery'] ?? 0), false),
                ])
                ->action(static function (): void {}),
            AdminDialogSize::Large,
        );
    }

    private function regularHeroGalleryForAction(array $arguments): ArtworkCategory
    {
        $id = (int) ($arguments['gallery'] ?? 0);
        if ($id < 1) {
            throw ValidationException::withMessages(['gallery' => 'Choose an existing Gallery.']);
        }

        return ArtworkCategory::query()->with('siteSection')->whereHas('siteSection')->findOrFail($id);
    }

    /** Only current/excluded Hero candidates are editable here; not Gallery membership. */
    public function regularHeroGalleryMembers(int $galleryId): array
    {
        return collect([...$this->heroRailRows, ...$this->excludedHeroRows])
            ->filter(static fn (array $row): bool =>
                ($row['is_automatic'] ?? false) && (int) ($row['gallery_id'] ?? 0) === $galleryId)
            ->unique('id')
            ->map(static function (array $row): array {
                $row['disabled'] = ! ($row['eligible'] ?? false);

                return $row;
            })
            ->values()
            ->all();
    }

    /** Editing an existing Dummy Gallery autosaves immediately. */
    public function editDummyGalleryAction(): Action
    {
        return AdminDialog::edit(
            Action::make('editDummyGallery')
                ->label('Edit Dummy Gallery')
                ->modalHeading('Edit Dummy Gallery')
                ->fillForm(fn (array $arguments): array => [
                    'name' => $this->dummyGallery((int) ($arguments['gallery'] ?? 0))['name'],
                    'pending_candidate' => null,
                ])
                ->schema(fn (array $arguments): array => app(GalleryHeroDummyGalleryDialog::class)
                    ->schema((int) ($arguments['gallery'] ?? 0), false))
                ->extraModalFooterActions(fn (array $arguments): array => [
                    $this->heroSourcePublicationHeaderAction((int) ($arguments['gallery'] ?? 0), true),
                ])
                ->action(static function (): void {}),
            AdminDialogSize::Large,
        );
    }

    /**
     * The row's Hero source state action also belongs in the Edit dialog
     * header, never as a second form field. The canonical dialog adapter
     * places contextual actions immediately left of its native close button.
     */
    private function heroSourcePublicationHeaderAction(int $galleryId, bool $dummy): Action
    {
        if ($dummy) {
            $source = $this->dummyGallery($galleryId);
            $enabled = (bool) $source['enabled'];
            $canEnable = true;
            $editAction = 'editDummyGallery';
        } else {
            $gallery = $this->regularHeroGalleryForAction(['gallery' => $galleryId]);
            $enabled = in_array($galleryId, $this->enabledGalleryIds, true);
            $canEnable = $gallery->getRelationValue('siteSection')?->getAttribute('state') === 'published';
            $editAction = 'editRegularHeroGallery';
        }

        $role = $enabled ? AdminRowAction::Unpublish : AdminRowAction::Publish;

        return AdminDialog::headerAction(
            'dialogHeroSourcePublication',
            $role,
            function () use ($galleryId, $dummy, $enabled, $editAction): void {
                if ($dummy) {
                    $this->toggleDummyGallery($galleryId);
                } else {
                    $this->setRegularHeroGalleryEnabled($galleryId, ! $enabled);
                }
                // Refresh the retained editor and its header against persisted state.
                $this->replaceMountedAction($editAction, ['gallery' => $galleryId]);
            },
        )
            ->label($enabled ? 'Disable' : 'Enable')
            ->disabled(! $enabled && ! $canEnable);
    }

    public function renameDummyGallery(int $galleryId, string $name): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->updateDummyGallery(
            $this->settings(), $galleryId, ['name' => $name],
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery renamed', status: 'success');
        }
    }

    public function deleteDummyGalleryAction(): Action
    {
        return AdminDialog::confirm(
            Action::make('deleteDummyGallery')
                ->label('Delete Dummy Gallery')
                ->action(function (array $arguments): void {
                    $changed = app(GalleryHeroConfigurationService::class)->removeDummyGallery(
                        $this->settings(), (int) ($arguments['gallery'] ?? 0),
                    );
                    if ($changed) {
                        $this->clearSourceSelection();
                        $this->reloadWorkspace();
                        app(AdminNotifier::class)->notification(title: 'Dummy Gallery deleted', status: 'success');
                    }
                }),
            'Delete Dummy Gallery and its Hero memberships?',
            'The referenced Artworks and Storage files remain intact.',
            submitLabel: 'Delete',
            danger: true,
        );
    }

    private function dummyGallery(int $galleryId): array
    {
        foreach (app(GalleryHeroConfigurationService::class)->configuration($this->settings())['dummy_galleries'] as $gallery) {
            if ($gallery['id'] === $galleryId) {
                return $gallery;
            }
        }
        throw ValidationException::withMessages(['dummy_galleries' => 'Dummy Gallery no longer exists.']);
    }

    /** @return list<array<string,mixed>> */
    public function dummyEditorMembers(int $galleryId, bool $creating): array
    {
        $gallery = $creating
            ? ['id' => 0, 'name' => 'New Dummy Gallery', 'members' => $this->draftDummyMembers]
            : $this->dummyGallery($galleryId);
        $resolver = app(GalleryHeroResolver::class);
        $available = $resolver->dummyCandidates(
            $gallery['members'], (int) $gallery['id'], (string) $gallery['name'],
        );
        if (! $creating) {
            $stored = app(GalleryHeroConfigurationService::class)
                ->configuration($this->settings())['hero_artwork_overrides'];
            $available = $resolver->applyHeroArtworkOverrides($available, $stored);
        }
        $available = $available->keyBy(static fn (GalleryHeroCandidate $candidate): string => $candidate->key());
        $rows = [];
        foreach ($gallery['members'] as $member) {
            $key = GalleryHeroConfigurationService::memberKey($member);
            $candidate = $available->get($key);
            $row = $candidate instanceof GalleryHeroCandidate ? $this->candidateRow($candidate) : [
                'id' => $key, 'title' => 'Unavailable '.$key, 'thumbnail_url' => null,
                'gallery' => $gallery['name'], 'gallery_id' => $galleryId, 'date' => null,
            ];
            $artwork = $candidate?->artwork();
            $row['year'] = $artwork?->getAttribute('year') ?? ($member['year'] ?? null);
            $row['month'] = $artwork?->getAttribute('month') ?? ($member['month'] ?? null);
            $row['day'] = $artwork?->getAttribute('day') ?? ($member['day'] ?? null);
            if (! $artwork instanceof Artwork) {
                $row['date'] = ArtworkDate::displayParts(
                    $row['year'], $row['month'], $row['day'], RegionalFormats::adminRegion(),
                );
            }
            $row['disabled'] = ! ($member['enabled'] ?? true);
            $rows[] = $row;
        }
        return $rows;
    }

    public function addDraftDummyMedia(int $mediaAssetId): void
    {
        $asset = MediaAsset::query()->with('variants')->find($mediaAssetId);
        if (! $asset instanceof MediaAsset || ! GalleryHeroCandidate::mediaIsEligible($asset)) {
            throw ValidationException::withMessages(['pending_candidate' => 'Choose an eligible Storage file.']);
        }
        $key = 'media:'.$mediaAssetId;
        if (collect($this->draftDummyMembers)->contains(
            static fn (array $member): bool => GalleryHeroConfigurationService::memberKey($member) === $key,
        )) {
            throw ValidationException::withMessages(['pending_candidate' => 'Already added to this Dummy Gallery.']);
        }
        $this->draftDummyMembers[] = [
            'media_asset_id' => $mediaAssetId, 'enabled' => true,
            'title' => (string) $asset->getAttribute('original_filename'),
            'year' => null, 'month' => null, 'day' => null,
        ];
    }

    public function addDraftDummyArtwork(int $artworkId): void
    {
        if (app(PublicArtworkQuery::class)->editorialHeroCandidatesByIds([$artworkId])->isEmpty()) {
            throw ValidationException::withMessages(['pending_candidate' => 'Choose an eligible Artwork.']);
        }
        $key = 'artwork:'.$artworkId;
        if (collect($this->draftDummyMembers)->contains(
            static fn (array $member): bool => GalleryHeroConfigurationService::memberKey($member) === $key,
        )) {
            throw ValidationException::withMessages(['pending_candidate' => 'Already added to this Dummy Gallery.']);
        }
        $this->draftDummyMembers[] = ['artwork_id' => $artworkId, 'enabled' => true];
    }

    public function addDummyMediaFromStorage(int $galleryId, int $mediaAssetId): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->addDummyMedia($this->settings(), $galleryId, $mediaAssetId);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member added', status: 'success');
        }
    }

    public function addDummyArtwork(int $galleryId, int $artworkId): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->addDummyArtwork($this->settings(), $galleryId, $artworkId);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member added', status: 'success');
        }
    }

    public function updateDraftDummyMember(int $unused, string $key, string $field, mixed $value): void
    {
        if ($field !== 'title') {
            throw ValidationException::withMessages(['candidate' => 'Unknown Dummy Gallery field.']);
        }
        $changes = ['title' => $value === '' ? null : $value];

        foreach ($this->draftDummyMembers as &$member) {
            if (GalleryHeroConfigurationService::memberKey($member) === $key && isset($member['media_asset_id'])) {
                foreach ($changes as $part => $partValue) {
                    $member[$part] = $partValue;
                }
                unset($member);
                return;
            }
        }
        unset($member);
    }

    public function updateDummyMember(int $galleryId, string $key, string $field, mixed $value): void
    {
        if ($field !== 'title') {
            throw ValidationException::withMessages(['candidate' => 'Unknown Dummy Gallery field.']);
        }
        $changes = ['title' => $value === '' ? null : $value];

        $changed = app(GalleryHeroConfigurationService::class)->updateDummyMember(
            $this->settings(), $galleryId, $key, $changes,
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member saved', status: 'success');
        }
    }

    public function toggleDraftDummyMember(int $unused, string $key): void
    {
        foreach ($this->draftDummyMembers as &$member) {
            if (GalleryHeroConfigurationService::memberKey($member) === $key) {
                $member['enabled'] = ! $member['enabled'];
                return;
            }
        }
        unset($member);
    }

    public function toggleDummyMember(int $galleryId, string $key): void
    {
        $gallery = $this->dummyGallery($galleryId);
        foreach ($gallery['members'] as $member) {
            if (GalleryHeroConfigurationService::memberKey($member) !== $key) {
                continue;
            }
            $changed = app(GalleryHeroConfigurationService::class)->setDummyMemberEnabled(
                $this->settings(), $galleryId, $key, ! $member['enabled'],
            );
            if ($changed) {
                $this->reloadWorkspace();
                app(AdminNotifier::class)->notification(title: 'Dummy Gallery member updated', status: 'success');
            }
            return;
        }
    }

    public function removeDraftDummyMember(int $unused, string $key): void
    {
        $this->draftDummyMembers = array_values(array_filter(
            $this->draftDummyMembers,
            static fn (array $member): bool => GalleryHeroConfigurationService::memberKey($member) !== $key,
        ));
    }

    public function removeDummyMember(int $galleryId, string $key): void
    {
        $changed = app(GalleryHeroConfigurationService::class)->removeDummyMember($this->settings(), $galleryId, $key);
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery member removed', status: 'success');
        }
    }

    public function toggleDummyGallery(int $galleryId): void
    {
        $gallery = $this->dummyGallery($galleryId);
        $changed = app(GalleryHeroConfigurationService::class)->updateDummyGallery(
            $this->settings(), $galleryId, ['enabled' => ! $gallery['enabled']],
        );
        if ($changed) {
            $this->reloadWorkspace();
            app(AdminNotifier::class)->notification(title: 'Dummy Gallery source updated', status: 'success');
        }
    }
}
