<?php

namespace App\Filament\Pages;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\ExhibitionDate;
use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalSettingsService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\RegionalFormats;
use App\Domain\Content\SitePreviewContext;
use App\Domain\Content\SiteSectionType;
use App\Domain\Media\MediaPreviewVariant;
use App\Filament\Support\AdminRowAction;
use App\Filament\Support\Controls\AdminControl;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Pages\Concerns\JournalWorkspaceStage;
use App\Filament\Pages\Concerns\JournalWorkspaceOrganizers;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Filament\Support\JournalEntryEditorState;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Models\User;
use App\Routing\SiteNodeRoute;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Session;
use Throwable;

final class JournalWorkspace extends Page
{
    use JournalWorkspaceStage;
    use JournalWorkspaceOrganizers;

    #[Locked]
    public array $journalSettingsOriginal = [];

    /** Journal presentation controls shared by Settings and the Stage. */
    public array $journalStageSettingsData = [];

    #[Locked]
    public array $entryEditorOriginal = [];

    public static function canAccess(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->canAccessPanel(Filament::getCurrentPanel());
    }

    private const PAGE_SIZES = [25, 50, 100];

    private const DEFAULT_PAGE_SIZE = 50;

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'pages/journal/{section}';

    protected static ?string $title = 'Journal';

    #[Locked]
    public int $sectionId = 0;

    #[Locked]
    public string $template = '';

    public string $journalTitle = 'Journal';

    public ?string $journalPublicUrl = null;

    public ?string $journalPreviewUrl = null;

    public string $journalSlug = '';

    public array $metrics = [];

    public array $posts = [];

    public array $exhibitions = [];

    public int $unfilteredEntryCount = 0;

    public string $search = '';

    public string $statusFilter = 'any';

    public string $timingFilter = 'any';

    public array $selectedPostIds = [];

    public array $selectedExhibitionIds = [];

    /** The shared Journal Stage follows the most recently selected visible entry. */
    public bool $journalPreviewAutoFocus = true;

    public ?int $journalPreviewFocusedEntryId = null;

    public int $page = 1;

    #[Session]
    public int $pageSize = self::DEFAULT_PAGE_SIZE;

    public int $total = 0;

    public int $pages = 1;

    public function mount(int|string $section): void
    {
        $siteSection = SiteSection::query()->whereKey((int) $section)->where('type', SiteSectionType::Journal->value)->firstOrFail();
        $template = $siteSection->journalTemplate();
        abort_unless($template instanceof JournalTemplate, 404);
        $this->sectionId = (int) $siteSection->getKey();
        $this->template = $template->value;
        $this->loadJournalContext($siteSection);
        $this->reloadEntries();
        $this->refreshJournalStageSettings();
        $this->restoreStagedJournalDrafts();
    }

    public function getView(): string
    {
        return match ($this->journalTemplate()) {
            JournalTemplate::Blog => 'filament.resources.blog-posts.pages.list-blog-posts',
            JournalTemplate::Exhibitions => 'filament.resources.exhibitions.pages.list-exhibitions',
        };
    }

    public function getTitle(): string
    {
        $title = trim($this->journalTitle);

        return $title !== '' ? $title : __('Journal');
    }

    public function updatedSearch(): void
    {
        $this->refreshFromFirstPage();
    }

    public function updatedStatusFilter(): void
    {
        $allowed = $this->journalTemplate() === JournalTemplate::Blog
            ? ['any', 'draft', 'scheduled', 'published', 'unpublished']
            : ['any', 'published', 'unpublished'];
        if (! in_array($this->statusFilter, $allowed, true)) {
            $this->statusFilter = 'any';
        }
        $this->refreshFromFirstPage();
    }

    public function updatedTimingFilter(): void
    {
        if (! in_array($this->timingFilter, ['any', 'upcoming', 'current', 'past', 'unknown'], true)) {
            $this->timingFilter = 'any';
        }
        $this->refreshFromFirstPage();
    }

    public function updatedPageSize(mixed $value): void
    {
        $this->pageSize = $this->normalizePageSize($value);
        $this->refreshFromFirstPage();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'any';
        $this->timingFilter = 'any';
        $this->refreshFromFirstPage();
    }

    public function previousPage(): void
    {
        if ($this->page > 1) {
            $this->page--;
            $this->reloadEntries(false);
        }
    }

    public function nextPage(): void
    {
        if ($this->page < $this->pages) {
            $this->page++;
            $this->reloadEntries(false);
        }
    }

    public function canDragSort(): bool
    {
        return trim($this->search) === ''
            && $this->statusFilter === 'any'
            && ($this->journalTemplate() === JournalTemplate::Blog || $this->timingFilter === 'any')
            && $this->page === 1
            && $this->total <= $this->pageSize;
    }

    public function sortPost(int|string $id, int $position): void
    {
        if (! $this->canDragSort() || $this->journalTemplate() !== JournalTemplate::Blog || ! $this->validSortEntryId($id)) {
            return;
        }
        $post = $this->post((int) $id);
        $changed = app(JournalEntryOrderService::class)->moveToPosition($post, $position);
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Journal order updated',
                status: 'success',
            );
        }
        $this->loadPosts(false);
        if ($changed) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    public function sortExhibition(int|string $id, int $position): void
    {
        if (! $this->canDragSort() || $this->journalTemplate() !== JournalTemplate::Exhibitions || ! $this->validSortEntryId($id)) {
            return;
        }
        $entry = $this->exhibition((int) $id);
        $changed = app(JournalEntryOrderService::class)->moveToPosition($entry, $position);
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Exhibition order updated',
                status: 'success',
            );
        }
        $this->loadExhibitions(false);
        if ($changed) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    private function validSortEntryId(int|string $id): bool
    {
        return (is_int($id) || ctype_digit($id)) && (int) $id > 0;
    }

    public function sortExhibitionGalleryMedia(string $itemKey, int $position): void
    {
        if ($this->journalTemplate() !== JournalTemplate::Exhibitions
            || $this->getMountedAction()?->getName() !== 'editExhibition') {
            return;
        }

        $schema = $this->getMountedActionSchema();
        $state = $schema->getRawState();
        $items = $state['gallery_images'] ?? null;
        if (! is_array($items) || count($items) < 2) {
            return;
        }

        $keys = array_keys($items);
        $source = array_search($itemKey, array_map(strval(...), $keys), true);
        if ($source === false || $position < 0 || $position >= count($keys) || $source === $position) {
            return;
        }

        $moved = array_splice($keys, $source, 1);
        array_splice($keys, $position, 0, $moved);

        $ordered = [];
        foreach ($keys as $key) {
            $ordered[$key] = $items[$key];
        }

        $schema->partialRawState(['gallery_images' => $ordered]);
        $this->callMountedAction();
    }

    public function togglePostSelection(int $id): void
    {
        $this->selectedPostIds = $this->toggleSelection($this->selectedPostIds, $id);
        $this->syncJournalPreviewFocus();
    }

    public function toggleExhibitionSelection(int $id): void
    {
        $this->selectedExhibitionIds = $this->toggleSelection($this->selectedExhibitionIds, $id);
        $this->syncJournalPreviewFocus();
    }

    public function toggleVisibleSelection(): void
    {
        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $visible = collect($this->posts)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $this->selectedPostIds = $this->toggledVisibleSelection($this->selectedPostIds, $visible);
            $this->syncJournalPreviewFocus();

            return;
        }
        $visible = collect($this->exhibitions)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->selectedExhibitionIds = $this->toggledVisibleSelection($this->selectedExhibitionIds, $visible);
        $this->syncJournalPreviewFocus();
    }

    public function toggleJournalPreviewAutoFocus(): void
    {
        $this->journalPreviewAutoFocus = ! $this->journalPreviewAutoFocus;
        if ($this->journalPreviewAutoFocus) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    public function journalStagePreviewUrl(): ?string
    {
        $selectionCount = count($this->journalTemplate() === JournalTemplate::Blog
            ? $this->selectedPostIds : $this->selectedExhibitionIds);
        if (($this->journalStagePanel === 'new' && $selectionCount === 0)
            || ($this->journalStagePanel === 'staged' && $this->selectedStagedJournalDraftKey !== null)) {
            return route('preview.journal.staged-entry', ['section' => $this->sectionId, 'token' => $this->getId()]);
        }
        if ($this->journalPreviewAutoFocus && $this->journalPreviewFocusedEntryId !== null) {
            $rows = $this->journalTemplate() === JournalTemplate::Blog ? $this->posts : $this->exhibitions;
            foreach ($rows as $row) {
                if ((int) $row['id'] === $this->journalPreviewFocusedEntryId
                    && is_string($row['preview_url'] ?? null)) {
                    return $row['preview_url'];
                }
            }
        }

        return $this->journalPreviewUrl;
    }

    public function journalStagePreviewTitle(): string
    {
        $selectionCount = count($this->journalTemplate() === JournalTemplate::Blog
            ? $this->selectedPostIds : $this->selectedExhibitionIds);
        if (($this->journalStagePanel === 'new' && $selectionCount === 0)
            || ($this->journalStagePanel === 'staged' && $this->selectedStagedJournalDraftKey !== null)) {
            return $this->journalTemplate() === JournalTemplate::Blog
                ? 'Interactive Blog draft preview' : 'Interactive Exhibition draft preview';
        }
        if ($this->journalPreviewAutoFocus && $this->journalPreviewFocusedEntryId !== null) {
            return $this->journalTemplate() === JournalTemplate::Blog
                ? 'Interactive Blog post preview'
                : 'Interactive Exhibition preview';
        }

        return 'Interactive Journal preview';
    }

    /** Selection does not alter a manually navigated preview while Auto-focus is off. */
    private function syncJournalPreviewFocus(bool $forceRefresh = false): void
    {
        $isBlog = $this->journalTemplate() === JournalTemplate::Blog;
        $selected = $isBlog ? $this->selectedPostIds : $this->selectedExhibitionIds;
        $rows = $isBlog ? $this->posts : $this->exhibitions;
        // Like Gallery, one selected entry focuses its own preview; zero
        // or multiple selections return to the Journal's normal index.
        $single = count($selected) === 1 ? (int) reset($selected) : null;
        $next = null;
        foreach ($rows as $row) {
            if ($single !== null && (int) $row['id'] === $single
                && is_string($row['preview_url'] ?? null)) {
                $next = $single;
                break;
            }
        }
        $changed = $this->journalPreviewFocusedEntryId !== $next;
        $this->journalPreviewFocusedEntryId = $next;

        if (($this->journalPreviewAutoFocus && $changed) || $forceRefresh) {
            $this->refreshJournalPreview();
        }
    }

    private function refreshJournalPreview(): void
    {
        if ($this->journalPreviewAutoFocus) {
            $this->dispatch('admin-live-preview-updated',
                url: $this->journalStagePreviewUrl(), title: $this->journalStagePreviewTitle());
        } else {
            // Preserve the Public URL selected manually inside the iframe.
            $this->dispatch('admin-live-preview-updated');
        }
    }

    public function movePost(int $id, string $direction): void
    {
        $changed = app(BlogEditorialService::class)->move($this->post($id), $direction);
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Journal order updated',
                status: 'success',
            );
        }
        $this->loadPosts(false);
        if ($changed) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    public function moveExhibition(int $id, string $direction): void
    {
        $changed = app(ExhibitionEditorialService::class)->move($this->exhibition($id), $direction);
        if ($changed) {
            app(AdminNotifier::class)->notification(
                title: 'Exhibition order updated',
                status: 'success',
            );
        }
        $this->loadExhibitions(false);
        if ($changed) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    public function publishPost(int $id): void
    {
        $post = $this->post($id);
        $this->runEntryAction('Post published', $post, fn (BlogPost $target) => app(BlogEditorialService::class)->publish($target));
    }

    public function unpublishPost(int $id): void
    {
        $post = $this->post($id);
        $this->runEntryAction('Post unpublished', $post, fn (BlogPost $target) => app(BlogEditorialService::class)->unpublish($target));
    }

    public function restorePostDraft(int $id): void
    {
        $post = $this->post($id);
        $this->runEntryAction('Post restored to draft', $post, fn (BlogPost $target) => app(BlogEditorialService::class)->restoreDraft($target));
    }

    public function publishExhibition(int $id): void
    {
        $entry = $this->exhibition($id);
        $this->runEntryAction('Exhibition published', $entry, fn (Exhibition $target) => app(ExhibitionEditorialService::class)->publish($target));
    }

    public function unpublishExhibition(int $id): void
    {
        $entry = $this->exhibition($id);
        $this->runEntryAction('Exhibition unpublished', $entry, fn (Exhibition $target) => app(ExhibitionEditorialService::class)->unpublish($target));
    }

    public function moveSelectedEntries(string $direction): void
    {
        if (! $this->canDragSort() || ! in_array($direction, ['up', 'down'], true)) {
            return;
        }
        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $records = $this->selectedPosts();
            if ($direction === 'down') {
                $records = $records->reverse();
            }
            [$ok, $failed] = $this->runEntryBatch($records, fn (BlogPost $post): bool => app(BlogEditorialService::class)->move($post, $direction));
            $this->notifyBatch('posts reordered', $ok, $failed);
            $this->loadPosts(false);
            if ($ok > 0) {
                $this->syncJournalPreviewFocus(forceRefresh: true);
            }

            return;
        }
        $records = $this->selectedExhibitions();
        if ($direction === 'down') {
            $records = $records->reverse();
        }
        [$ok, $failed] = $this->runEntryBatch($records, fn (Exhibition $entry): bool => app(ExhibitionEditorialService::class)->move($entry, $direction));
        $this->notifyBatch('exhibitions reordered', $ok, $failed);
        $this->loadExhibitions(false);
        if ($ok > 0) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    public function publishSelectedPosts(): void
    {
        $this->runPostBatch('posts published', function (BlogPost $post): bool {
            if ((string) $post->getAttribute('state') === 'published') {
                return false;
            }
            app(BlogEditorialService::class)->publish($post);

            return true;
        });
    }

    public function unpublishSelectedPosts(): void
    {
        $this->runPostBatch('posts unpublished', function (BlogPost $post): bool {
            if ($post->getAttribute('state') !== 'published') {
                return false;
            }
            app(BlogEditorialService::class)->unpublish($post);

            return true;
        });
    }

    public function restoreSelectedPosts(): void
    {
        $this->runPostBatch('posts restored to draft', function (BlogPost $post): bool {
            if (! in_array((string) $post->getAttribute('state'), ['scheduled', 'unpublished', 'archived'], true)) {
                return false;
            }
            app(BlogEditorialService::class)->restoreDraft($post);

            return true;
        });
    }

    public function journalSettingsAction(): Action
    {
        $action = Action::make('journalSettings')
            ->label('Settings')
            ->fillForm(function (): array {
                $section = $this->section();
                $this->journalSettingsOriginal = ['title' => (string) $section->getAttribute('title')];

                return [
                    ...$this->journalSettingsOriginal,
                    ...app(JournalSettingsService::class)->displayValues($section),
                ];
            })
            ->schema([$this->journalSettingFields(false)])
            ->modalHeading(__('Journal settings'))
            ->action(function (array $data): void {
                $section = $this->section();
                $before = (string) $section->getAttribute('title');
                $service = app(JournalSettingsService::class);
                try {
                    [$updated, $displayChanged] = DB::transaction(function () use ($section, $data, $service): array {
                        $updated = $service->update($section, $data, $this->journalSettingsOriginal);

                        return [$updated, $service->updateDisplay($updated, $data)];
                    });
                } catch (ValidationException $exception) {
                    $this->throwMountedValidation($exception);
                }

                $this->journalSettingsOriginal = ['title' => (string) $updated->getAttribute('title')];
                $this->loadJournalContext($updated);
                $this->reloadEntries();
                $this->refreshJournalStageSettings();
                if ($displayChanged || $before !== (string) $updated->getAttribute('title')) {
                    $this->syncJournalPreviewFocus(forceRefresh: true);
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(title: 'Journal settings saved', status: 'success');
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /** Stage and dialog share the same public presentation controls. */
    private function journalSettingFields(bool $stage): Grid
    {
        $fields = [];
        if (! $stage) {
            $fields[] = AdminControl::text('title', 'Journal title')->required()->maxLength(160)->columnSpanFull();
        }
        $galleryOptions = $this->journalTemplate() === JournalTemplate::Blog
            ? ['sequence' => 'Media sequence', 'grid' => 'Grid', 'mosaic' => 'Mosaic', 'slideshow' => 'Slideshow', 'carousel' => 'Carousel']
            : ['grid' => 'Grid', 'mosaic' => 'Mosaic', 'slideshow' => 'Slideshow', 'carousel' => 'Carousel'];

        $gallery = AdminControl::select('default_gallery_presentation')
            ->label('Default gallery presentation')->options($galleryOptions)->required();
        $viewer = AdminControl::toggle('default_image_viewer_enabled', 'Enlarge images on click by default');
        $galleryEnabled = AdminControl::toggle('default_gallery_enabled', 'Enable exhibition gallery by default');
        $vernissage = AdminControl::toggle('default_show_vernissage', 'Show vernissage until it begins by default');

        if ($stage) {
            $gallery->live()->afterStateUpdated(fn (mixed $state) => $this->updateJournalStageSetting('default_gallery_presentation', $state));
            $viewer->live()->afterStateUpdated(fn (mixed $state) => $this->updateJournalStageSetting('default_image_viewer_enabled', (bool) $state));
            $galleryEnabled->live()->afterStateUpdated(fn (mixed $state) => $this->updateJournalStageSetting('default_gallery_enabled', (bool) $state));
            $vernissage->live()->afterStateUpdated(fn (mixed $state) => $this->updateJournalStageSetting('default_show_vernissage', (bool) $state));
        }
        $fields[] = $gallery->columnSpanFull();
        $fields[] = $viewer->columnSpanFull();
        if ($this->journalTemplate() === JournalTemplate::Exhibitions) {
            $fields[] = $galleryEnabled->columnSpanFull();
            $fields[] = $vernissage->columnSpanFull();
        }

        return Grid::make(['default' => 1])->schema($fields);
    }

    public function journalStageSettingsForm(Schema $schema): Schema
    {
        return $schema
            ->components([$this->journalSettingFields(true)])
            ->statePath('journalStageSettingsData');
    }

    private function refreshJournalStageSettings(): void
    {
        $this->journalStageSettingsForm->fill(app(JournalSettingsService::class)->displayValues($this->section()));
    }

    private function updateJournalStageSetting(string $field, mixed $state): void
    {
        try {
            $changed = app(JournalSettingsService::class)->updateDisplay($this->section(), [$field => $state]);
        } catch (ValidationException $exception) {
            $this->refreshJournalStageSettings();
            $this->notifyValidationFailure('Journal settings unchanged', $exception);

            return;
        }

        $this->refreshJournalStageSettings();
        if ($changed) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
            app(AdminNotifier::class)->notification(title: 'Journal settings saved', status: 'success');
        }
    }

    public function addPostAction(): Action
    {
        $action = Action::make('addPost')->label('Add post')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::blog($schema))
            ->fillForm(fn (): array => app(JournalSettingsService::class)->editorDefaults($this->section()))
            ->modalHeading(__('Add post'))
            ->action(function (Action $action, array $data): void {
                $data['site_section_id'] = $this->sectionId;
                try {
                    app(BlogEditorialService::class)->createDraft($data);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Post was not created', $exception);
                    $action->halt();

                    return;
                }
                $this->loadPosts();
                $this->syncJournalPreviewFocus(forceRefresh: true);
                app(AdminNotifier::class)->notification(
                    title: 'Post draft created',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Create draft', AdminDialogSize::Large);
    }

    public function editPostAction(): Action
    {
        $action = Action::make('editPost')->label('Edit')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->fillForm(function (array $arguments): array {
                $post = $this->post((int) ($arguments['post'] ?? 0));

                $this->entryEditorOriginal = $this->journalEntryMutationState($post);

                return [...$post->attributesToArray(), ...app(JournalEntryEditorState::class)->for($post)];
            })
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::blog($schema))
            ->modalHeading(__('Edit post'))
            ->extraModalFooterActions(fn (array $arguments): array => $this->journalEditHeaderActions($arguments, 'post'))
            ->action(function (Action $action, array $data, array $arguments): void {
                $post = $this->post((int) ($arguments['post'] ?? 0));
                $before = $this->journalEntryMutationState($post);
                $data = [...$data, 'site_section_id' => $this->sectionId, 'state' => $post->getAttribute('state'), 'position' => $post->getAttribute('position'), 'published_at' => $post->getAttribute('published_at'), 'scheduled_at' => $post->getAttribute('scheduled_at')];
                try {
                    $updated = $this->saveEditedEntry($post, $data);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Post unchanged', $exception);
                    $this->throwMountedValidation($exception);
                }
                $changed = $before !== $this->journalEntryMutationState($updated);
                $this->loadPosts(false);
                if ($changed) {
                    $this->syncJournalPreviewFocus(forceRefresh: true);
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(
                        title: 'Post saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    public function schedulePostAction(): Action
    {
        $action = Action::make('schedulePost')->label('Schedule publication')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->schema([DateTimePicker::make('scheduled_at')->label('Publish at')->seconds(false)->required()])
            ->modalHeading(__('Schedule publication'))
            ->action(function (Action $action, array $data, array $arguments): void {
                try {
                    app(BlogEditorialService::class)->schedule($this->post((int) ($arguments['post'] ?? 0)), $data['scheduled_at'] ?? null);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Post was not scheduled', $exception);
                    $action->halt();

                    return;
                }
                $this->loadPosts();
                app(AdminNotifier::class)->notification(
                    title: 'Publication scheduled',
                    status: 'success',
                );
                if (($arguments['returnToEditor'] ?? false) === true) {
                    $this->replaceMountedAction('editPost', ['post' => (int) $arguments['post']]);
                }
            });

        return AdminDialog::command($action, 'Schedule publication');
    }

    public function addExhibitionAction(): Action
    {
        $action = Action::make('addExhibition')->label('Add exhibition')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Exhibitions)
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::exhibition($schema))
            ->fillForm(fn (): array => app(JournalSettingsService::class)->editorDefaults($this->section()))
            ->modalHeading(__('Add exhibition'))
            ->action(function (Action $action, array $data): void {
                $data['site_section_id'] = $this->sectionId;
                try {
                    app(ExhibitionEditorialService::class)->createDraft($data);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Exhibition was not created', $exception);
                    $action->halt();

                    return;
                }
                $this->loadExhibitions();
                $this->syncJournalPreviewFocus(forceRefresh: true);
                app(AdminNotifier::class)->notification(
                    title: 'Exhibition draft created',
                    status: 'success',
                );
            });

        return AdminDialog::create($action, 'Create draft', AdminDialogSize::Large);
    }

    public function setExhibitionFieldVisibility(int $id, string $field, bool $visible): void
    {
        abort_unless($this->journalTemplate() === JournalTemplate::Exhibitions, 404);
        $exhibition = $this->exhibition($id);
        if (! app(ExhibitionEditorialService::class)->setPublicFieldVisibility($exhibition, $field, $visible)) {
            return;
        }

        $saved = $exhibition->fresh()->getAttributes();
        $this->entryEditorOriginal['record']['show_'.$field] = $saved['show_'.$field];
        $this->entryEditorOriginal['record']['updated_at'] = $saved['updated_at'];

        $this->loadExhibitions(false);
        $this->syncJournalPreviewFocus(forceRefresh: true);
        app(AdminNotifier::class)->notification(
            title: __('Exhibition visibility updated'),
            status: 'success',
        );
    }

    public function editExhibitionAction(): Action
    {
        $action = Action::make('editExhibition')->label('Edit')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Exhibitions)
            ->fillForm(function (array $arguments): array {
                $entry = $this->exhibition((int) ($arguments['exhibition'] ?? 0));

                $this->entryEditorOriginal = $this->journalEntryMutationState($entry);

                return [...$entry->attributesToArray(), ...app(JournalEntryEditorState::class)->for($entry)];
            })
            ->schema(fn (Schema $schema, array $arguments): Schema => JournalEntryEditorSchema::exhibition(
                $schema,
                $this->exhibition((int) ($arguments['exhibition'] ?? 0)),
            ))
            ->modalHeading(__('Edit exhibition'))
            ->extraModalFooterActions(fn (array $arguments): array => $this->journalEditHeaderActions($arguments, 'exhibition'))
            ->action(function (Action $action, Schema $schema, array $data, array $arguments): void {
                $entry = $this->exhibition((int) ($arguments['exhibition'] ?? 0));
                $before = $this->journalEntryMutationState($entry);
                $data['site_section_id'] = $this->sectionId;
                try {
                    $updated = $this->saveEditedEntry($entry, $data);
                } catch (ValidationException $exception) {
                    // A rejected map toggle must not remain visually enabled
                    // merely because Alpine updated its local switch state.
                    // Reconcile the committed map state, not unrelated fields.
                    $saved = $entry->fresh();
                    $schema->partialRawState([
                        'map_enabled' => (bool) $saved->getAttribute('map_enabled'),
                    ]);
                    $this->notifyValidationFailure('Exhibition unchanged', $exception);
                    $this->throwMountedValidation($exception);
                }
                $changed = $before !== $this->journalEntryMutationState($updated);
                $this->loadExhibitions(false);
                if ($changed) {
                    $this->syncJournalPreviewFocus(forceRefresh: true);
                    $this->forceRender();
                    app(AdminNotifier::class)->notification(
                        title: 'Exhibition saved',
                        status: 'success',
                    );
                }
            });

        return AdminDialog::edit($action, AdminDialogSize::Large);
    }

    /**
     * Edit headers reuse the same domain mutations, roles and modal tasks
     * as their table rows. Only the native Edit dialog owns autosave.
     *
     * @param  array<string,mixed>  $arguments
     * @return list<Action>
     */
    private function journalEditHeaderActions(array $arguments, string $kind): array
    {
        $isPost = $kind === 'post';
        $id = (int) ($arguments[$kind] ?? 0);
        $entry = $isPost ? $this->post($id) : $this->exhibition($id);
        $state = (string) $entry->getAttribute('state');
        $target = [$kind => $id];
        $editAction = $isPost ? 'editPost' : 'editExhibition';
        $orderedIds = ($isPost ? BlogPost::query() : Exhibition::query())
            ->where('site_section_id', $this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn (mixed $value): int => (int) $value)
            ->all();
        $index = array_search($id, $orderedIds, true);
        $actions = [];

        foreach (['up' => AdminRowAction::MoveUp, 'down' => AdminRowAction::MoveDown] as $direction => $role) {
            $canMove = $index !== false && ($direction === 'up' ? $index > 0 : $index < count($orderedIds) - 1);
            $actions[] = AdminDialog::headerAction('dialogMove'.ucfirst($direction), $role, function () use ($isPost, $id, $direction, $editAction, $target): void {
                if ($isPost) {
                    $this->movePost($id, $direction);
                } else {
                    $this->moveExhibition($id, $direction);
                }
                $this->replaceMountedAction($editAction, $target);
            })->disabled(! $canMove);
        }

        if (! $isPost) {
            $published = $state === 'published';
            $actions[] = AdminDialog::headerAction(
                'dialogExhibitionPublication',
                $published ? AdminRowAction::Unpublish : AdminRowAction::Publish,
                function () use ($published, $id, $editAction, $target): void {
                    if ($published) {
                        $this->unpublishExhibition($id);
                    } else {
                        $this->publishExhibition($id);
                    }
                    $this->replaceMountedAction($editAction, $target);
                },
            );
            $actions[] = AdminDialog::headerAction('dialogDeleteExhibition', AdminRowAction::Delete, function () use ($target): void {
                $this->replaceMountedAction('deleteExhibition', $target);
            })->disabled($published);

            return $actions;
        }

        $publicationRole = match ($state) {
            'published' => AdminRowAction::Unpublish,
            'scheduled' => AdminRowAction::CancelSchedule,
            default => AdminRowAction::Publish,
        };
        $actions[] = AdminDialog::headerAction(
            'dialogPostPublication',
            $publicationRole,
            function () use ($state, $id, $editAction, $target): void {
                if ($state === 'published') {
                    $this->unpublishPost($id);
                } elseif ($state === 'scheduled') {
                    $this->restorePostDraft($id);
                } else {
                    $this->publishPost($id);
                }
                $this->replaceMountedAction($editAction, $target);
            },
        )->disabled($state === 'archived');

        $actions[] = AdminDialog::headerAction('dialogSchedulePost', AdminRowAction::Schedule, function () use ($target): void {
            $this->replaceMountedAction('schedulePost', [...$target, 'returnToEditor' => true]);
        })->disabled(! in_array($state, ['draft', 'unpublished'], true));

        // Older archived records remain recoverable; no new Archive action.
        if ($state === 'archived') {
            $actions[] = AdminDialog::headerAction(
                'dialogRestorePost',
                AdminRowAction::Restore,
                function () use ($id, $editAction, $target): void {
                    $this->restorePostDraft($id);
                    $this->replaceMountedAction($editAction, $target);
                },
            );
        }

        $actions[] = AdminDialog::headerAction('dialogDeletePost', AdminRowAction::Delete, function () use ($target): void {
            $this->replaceMountedAction('deletePost', $target);
        })->disabled(in_array($state, ['published', 'scheduled'], true));

        return $actions;
    }

    public function deletePostAction(): Action
    {
        return $this->deleteAction('deletePost', 'post');
    }

    public function deleteExhibitionAction(): Action
    {
        return $this->deleteAction('deleteExhibition', 'exhibition');
    }

    private function deleteAction(string $name, string $type): Action
    {
        $action = Action::make($name)->label('Delete')->color('danger')
            ->action(function (Action $action, array $arguments) use ($type): void {
                $id = (int) ($arguments[$type] ?? 0);
                try {
                    if ($type === 'post') {
                        app(BlogEditorialService::class)->delete($this->post($id));
                        $this->selectedPostIds = array_values(array_filter($this->selectedPostIds, fn (mixed $selected): bool => (int) $selected !== $id));
                        $this->loadPosts();
                    } else {
                        app(ExhibitionEditorialService::class)->delete($this->exhibition($id));
                        $this->selectedExhibitionIds = array_values(array_filter($this->selectedExhibitionIds, fn (mixed $selected): bool => (int) $selected !== $id));
                        $this->loadExhibitions();
                    }
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure(ucfirst($type).' was not deleted', $exception);
                    $action->halt();

                    return;
                }
                $this->syncJournalPreviewFocus(forceRefresh: true);
                app(AdminNotifier::class)->notification(
                    title: ucfirst($type).' deleted',
                    status: 'success',
                );
            });

        return AdminDialog::confirm(
            $action,
            'Delete '.$type.'?',
            'Files in Storage are preserved. Only this Journal entry and its references are removed.',
            'Delete',
            danger: true,
        );
    }

    public function deleteSelectedPostsAction(): Action
    {
        $action = Action::make('deleteSelectedPosts')->label('Delete selected')->color('danger')
            ->action(function (): void {
                [$ok, $failed] = $this->runEntryBatch($this->selectedPosts(), fn (BlogPost $post): bool => tap(true, fn () => app(BlogEditorialService::class)->delete($post)));
                $this->selectedPostIds = [];
                $this->notifyBatch('posts deleted', $ok, $failed);
                $this->loadPosts();
                if ($ok > 0) {
                    $this->syncJournalPreviewFocus(forceRefresh: true);
                }
            });

        return AdminDialog::confirm(
            $action,
            'Delete posts?',
            'Published and scheduled posts are kept. Files in Storage are preserved.',
            'Delete',
            danger: true,
        );
    }

    public function deleteSelectedExhibitionsAction(): Action
    {
        $action = Action::make('deleteSelectedExhibitions')->label('Delete selected')->color('danger')
            ->action(function (): void {
                [$ok, $failed] = $this->runEntryBatch($this->selectedExhibitions(), fn (Exhibition $entry): bool => tap(true, fn () => app(ExhibitionEditorialService::class)->delete($entry)));
                $this->selectedExhibitionIds = [];
                $this->notifyBatch('exhibitions deleted', $ok, $failed);
                $this->loadExhibitions();
                if ($ok > 0) {
                    $this->syncJournalPreviewFocus(forceRefresh: true);
                }
            });

        return AdminDialog::confirm(
            $action,
            'Delete exhibitions?',
            'Published exhibitions are kept. Files in Storage are preserved.',
            'Delete',
            danger: true,
        );
    }

    private function refreshFromFirstPage(): void
    {
        $this->page = 1;
        $this->reloadEntries(false);
    }

    private function loadJournalContext(?SiteSection $section = null): void
    {
        $section ??= $this->section();
        $this->journalTitle = (string) $section->getAttribute('title');
        $this->journalSlug = (string) $section->getAttribute('slug');
        $this->journalPublicUrl = $section->getAttribute('state') === 'published' ? app(SiteNodeRoute::class)->url($section) : null;
        $this->journalPreviewUrl = app(SitePreviewContext::class)->previewUrlFor($section);
    }

    private function loadPosts(bool $refreshMetrics = true): void
    {
        if ($refreshMetrics) {
            $this->loadPostMetrics();
        }
        $query = BlogPost::query()->where('site_section_id', $this->sectionId);
        if ($this->statusFilter !== 'any') {
            $query->where('state', $this->statusFilter);
        }
        $term = trim($this->search);
        if ($term !== '') {
            $query->where(fn (Builder $search) => $search->where('title', 'ilike', '%'.$term.'%')->orWhere('excerpt', 'ilike', '%'.$term.'%'));
        }
        $this->total = (clone $query)->count();
        $this->setPagination($this->total);

        $canonicalIds = BlogPost::query()->where('site_section_id', $this->sectionId)->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $ranks = $canonicalIds->flip();
        $query->with(['mediaUsages' => function ($usages): void {
            $usages->where('role', JournalEntryMedia::ROLE_COVER)->with('mediaAsset.variants');
        }]);
        /** @var EloquentCollection<int, BlogPost> $records */
        $records = $query->orderBy('position')->orderBy('id')->forPage($this->page, $this->pageSize)->get();
        $now = now();
        $count = $canonicalIds->count();
        $isHome = $this->section()->isHome();
        $this->posts = $records->map(function (BlogPost $post) use ($ranks, $count, $now, $isHome): array {
            $state = (string) $post->getAttribute('state');
            $published = $post->getAttribute('published_at');
            $scheduled = $post->getAttribute('scheduled_at');
            $publication = match (true) {
                $state === 'scheduled' && $scheduled instanceof DateTimeInterface => RegionalFormats::adminDateTime($scheduled),
                $published instanceof DateTimeInterface => RegionalFormats::adminDate($published),
                default => null,
            };
            $rank = ((int) ($ranks[(int) $post->getKey()] ?? 0)) + 1;

            return [
                'id' => (int) $post->getKey(), 'rank' => $rank, 'title' => (string) $post->getAttribute('title'),
                'excerpt' => $this->postTableExcerpt($post),
                'publication' => $publication, 'state' => $state, 'thumbnail_url' => $this->coverThumbnailUrl($post),
                'public_url' => $this->journalPublicUrl !== null && $this->postIsPublic($post, $now) ? route('journal.show', ['section' => $this->journalSlug, 'slug' => $post->getKey()]) : null,
                'preview_url' => $state === 'archived' ? null : ($isHome
                    ? route('preview.home.journal.show', ['slug' => $post->getKey()])
                    : route('preview.journal.show', ['section' => $this->journalSlug, 'slug' => $post->getKey()])),
                'can_move_up' => $rank > 1, 'can_move_down' => $rank < $count,
                'can_delete' => ! in_array($state, ['published', 'scheduled'], true),
                'delete_help' => in_array($state, ['published', 'scheduled'], true) ? 'Unpublish or cancel schedule before deleting' : null,
            ];
        })->all();
    }

    /** Display the existing summary, or a plain-text preview when no excerpt was authored. */
    private function postTableExcerpt(BlogPost $post): ?string
    {
        $excerpt = trim((string) ($post->getAttribute('excerpt') ?? ''));
        $body = trim((string) ($post->getAttribute('body') ?? ''));
        $source = \App\Domain\Content\RichTextDocument::plainText($excerpt !== '' ? $excerpt : $body);

        return $source !== '' ? Str::limit($source, 140) : null;
    }

    private function loadExhibitions(bool $refreshMetrics = true): void
    {
        if ($refreshMetrics) {
            $this->loadExhibitionMetrics();
        }
        $query = Exhibition::query()->where('site_section_id', $this->sectionId);
        if ($this->statusFilter === 'published') {
            $query->where('state', 'published');
        } elseif ($this->statusFilter === 'unpublished') {
            $query->where('state', '!=', 'published');
        }
        $this->applyTimingFilter($query);
        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function (Builder $search) use ($term): void {
                $search->where('title', 'ilike', '%'.$term.'%')->orWhere('organizer', 'ilike', '%'.$term.'%')->orWhere('venue', 'ilike', '%'.$term.'%')
                    ->orWhere('location_text', 'ilike', '%'.$term.'%')->orWhere('postal_code', 'ilike', '%'.$term.'%')->orWhere('city', 'ilike', '%'.$term.'%')
                    ->orWhere('country', 'ilike', '%'.$term.'%')->orWhere('starts_on', 'like', '%'.$term.'%')->orWhere('ends_on', 'like', '%'.$term.'%');
            });
        }
        $this->total = (clone $query)->count();
        $this->setPagination($this->total);
        $canonicalIds = Exhibition::query()->where('site_section_id', $this->sectionId)->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $ranks = $canonicalIds->flip();
        $count = $canonicalIds->count();
        $query->with(['mediaUsages' => function ($usages): void {
            $usages->where('role', JournalEntryMedia::ROLE_COVER)->with('mediaAsset.variants');
        }]);
        /** @var EloquentCollection<int, Exhibition> $records */
        $records = $query->orderBy('position')->orderBy('id')->forPage($this->page, $this->pageSize)->get();
        $now = now();
        $this->exhibitions = $records->map(function (Exhibition $entry) use ($ranks, $count, $now): array {
            $internalState = (string) $entry->getAttribute('state');
            $state = $internalState === 'published' ? 'published' : 'unpublished';
            $rank = ((int) ($ranks[(int) $entry->getKey()] ?? 0)) + 1;
            $location = $entry->summaryAttribution();

            $dateRange = ExhibitionDate::display(
                $entry->getAttribute('starts_on'),
                $entry->getAttribute('ends_on'),
                RegionalFormats::adminRegion(),
            ) ?? '';
            $vernissageAt = $entry->getAttribute('vernissage_at');
            $vernissage = $vernissageAt instanceof CarbonInterface
                ? RegionalFormats::adminDateTime($vernissageAt)
                : (filled($entry->getAttribute('opening_text')) ? trim((string) $entry->getAttribute('opening_text')) : null);

            return [
                'id' => (int) $entry->getKey(), 'rank' => $rank, 'title' => (string) $entry->getAttribute('title'), 'location' => $location,
                'state' => $state, 'timing' => $entry->temporalState($now), 'vernissage' => $vernissage,
                'date_range' => $dateRange,
                'thumbnail_url' => $this->coverThumbnailUrl($entry),
                'public_url' => $this->journalPublicUrl !== null && $internalState === 'published' ? $this->journalPublicUrl : null,
                'preview_url' => $internalState === 'archived' || $this->journalPreviewUrl === null
                    ? null : $this->journalPreviewUrl.'#exhibition-'.$entry->getKey(),
                'can_move_up' => $rank > 1, 'can_move_down' => $rank < $count, 'can_delete' => $internalState !== 'published',
                'delete_help' => $internalState === 'published' ? 'Unpublish this exhibition before deleting' : null,
            ];
        })->all();
    }

    private function loadPostMetrics(): void
    {
        $records = BlogPost::query()->where('site_section_id', $this->sectionId)->get(['id', 'state', 'published_at', 'scheduled_at']);
        $this->unfilteredEntryCount = $records->count();
        $analytics = app(ArtistReportingService::class)->blog(null, '30d');
        $this->metrics = [
            ['label' => 'Reads · 30d', 'value' => $this->analyticsValue($analytics['reads'] ?? null), 'description' => $this->analyticsDescription($analytics, 'Posts opened')],
            ['label' => 'Published', 'value' => $records->where('state', 'published')->count(), 'description' => 'Live posts'],
            ['label' => 'Scheduled', 'value' => $records->where('state', 'scheduled')->count(), 'description' => 'Queued posts'],
            ['label' => 'Draft', 'value' => $records->where('state', 'draft')->count(), 'description' => 'Work in progress'],
            ['label' => 'Unpublished', 'value' => $records->where('state', 'unpublished')->count(), 'description' => 'Offline posts'],
            ['label' => 'Total', 'value' => $records->count(), 'description' => 'All posts'],
        ];
    }

    private function loadExhibitionMetrics(): void
    {
        $records = Exhibition::query()->where('site_section_id', $this->sectionId)->get(['id', 'state', 'starts_on', 'ends_on']);
        $this->unfilteredEntryCount = $records->count();
        $now = now();
        $timing = $records->map(fn (Exhibition $entry): string => $entry->temporalState($now));
        $analytics = app(ArtistReportingService::class)->exhibitions('30d');
        $this->metrics = [
            ['label' => 'Visits · 30d', 'value' => $this->analyticsValue($analytics['page']['visits'] ?? null), 'description' => $this->analyticsDescription($analytics, 'Journal page')],
            ['label' => 'Views · 30d', 'value' => $this->analyticsValue($analytics['page']['views'] ?? null), 'description' => $this->analyticsDescription($analytics, 'Journal page')],
            ['label' => 'Published', 'value' => $records->where('state', 'published')->count(), 'description' => 'Public exhibitions'],
            ['label' => 'Current', 'value' => $timing->filter(fn (string $value): bool => $value === 'current')->count(), 'description' => 'Happening now'],
            ['label' => 'Upcoming', 'value' => $timing->filter(fn (string $value): bool => $value === 'upcoming')->count(), 'description' => 'Coming next'],
            ['label' => 'Interactions · 30d', 'value' => $this->analyticsSum($analytics['external_clicks'] ?? null, $analytics['directions_clicks'] ?? null), 'description' => $this->analyticsDescription($analytics, 'External + map')],
        ];
    }

    private function analyticsValue(mixed $metric): int|string
    {
        if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
            return '—';
        }

        return (int) round((float) $metric['value']);
    }

    private function analyticsSum(mixed ...$metrics): int|string
    {
        $sum = 0.0;
        foreach ($metrics as $metric) {
            if (! is_array($metric) || ($metric['state'] ?? null) !== 'available' || ! is_numeric($metric['value'] ?? null)) {
                return '—';
            }
            $sum += (float) $metric['value'];
        }

        return (int) round($sum);
    }

    private function analyticsDescription(array $report, string $base): string
    {
        return match ((string) ($report['status'] ?? 'unavailable')) {
            'stale' => $base.' · stale',
            'loading' => $base.' · loading',
            'unavailable' => $base.' · unavailable',
            default => $base,
        };
    }

    /** @param Builder<Exhibition> $query */
    private function applyTimingFilter(Builder $query): void
    {
        if (! in_array($this->timingFilter, ['upcoming', 'current', 'past', 'unknown'], true)) {
            return;
        }

        // Temporal certainty is owned by the Exhibition model, not a second
        // SQL-only interpretation of imprecise months and years.
        $now = now();
        $ids = (clone $query)
            ->get(['id', 'starts_on', 'ends_on'])
            ->filter(fn (Exhibition $entry): bool => $entry->temporalState($now) === $this->timingFilter)
            ->modelKeys();
        $query->whereIn('id', $ids);
    }

    private function coverThumbnailUrl(BlogPost|Exhibition $entry): ?string
    {
        $usage = $entry->getRelationValue('mediaUsages')->first();
        if (! $usage instanceof JournalEntryMedia) {
            return null;
        }
        $asset = $usage->getRelationValue('mediaAsset');
        if (! $asset instanceof MediaAsset) {
            return null;
        }
        $variant = MediaPreviewVariant::preview($asset);

        return $variant instanceof MediaVariant ? route('admin.media.variant', $variant) : null;
    }

    private function postIsPublic(BlogPost $post, CarbonInterface $now): bool
    {
        $state = (string) $post->getAttribute('state');
        $published = $post->getAttribute('published_at');
        if ($state === 'published' && $published instanceof CarbonInterface) {
            return $published->lessThanOrEqualTo($now);
        }
        $scheduled = $post->getAttribute('scheduled_at');

        return $state === 'scheduled' && $scheduled instanceof CarbonInterface && $scheduled->lessThanOrEqualTo($now);
    }

    private function reloadEntries(bool $refreshMetrics = true): void
    {
        $this->journalTemplate() === JournalTemplate::Blog ? $this->loadPosts($refreshMetrics) : $this->loadExhibitions($refreshMetrics);
    }

    private function setPagination(int $total): void
    {
        $this->pageSize = $this->normalizePageSize($this->pageSize);
        $this->total = $total;
        $this->pages = max(1, (int) ceil($total / $this->pageSize));
        $this->page = min(max(1, $this->page), $this->pages);
    }

    private function normalizePageSize(mixed $value): int
    {
        $size = is_numeric($value) ? (int) $value : self::DEFAULT_PAGE_SIZE;

        return in_array($size, self::PAGE_SIZES, true) ? $size : self::DEFAULT_PAGE_SIZE;
    }

    private function toggleSelection(array $selected, int $id): array
    {
        $ids = collect($selected)->map(fn (mixed $value): int => (int) $value)->unique()->values();

        return $ids->containsStrict($id) ? $ids->reject(fn (int $value): bool => $value === $id)->values()->all() : $ids->push($id)->unique()->values()->all();
    }

    private function toggledVisibleSelection(array $selected, array $visible): array
    {
        $selectedIds = collect($selected)->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $all = $visible !== [] && collect($visible)->every(fn (int $id): bool => $selectedIds->containsStrict($id));

        return $all ? $selectedIds->reject(fn (int $id): bool => in_array($id, $visible, true))->values()->all() : $selectedIds->merge($visible)->unique()->values()->all();
    }

    private function runEntryAction(string $successTitle, BlogPost|Exhibition $entry, callable $action): void
    {
        $changed = false;
        try {
            $before = $this->journalEntryMutationState($entry);
            $updated = $action($entry);
            $changed = ($updated instanceof BlogPost || $updated instanceof Exhibition)
                && $before !== $this->journalEntryMutationState($updated);
            if ($changed) {
                app(AdminNotifier::class)->notification(
                    title: $successTitle,
                    status: 'success',
                );
            }
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Journal entry unchanged', $exception);
        }
        $this->reloadEntries();
        if ($changed) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    private function runPostBatch(string $label, callable $action): void
    {
        [$ok, $failed] = $this->runEntryBatch($this->selectedPosts(), $action);
        $this->selectedPostIds = [];
        $this->notifyBatch($label, $ok, $failed);
        $this->loadPosts();
        if ($ok > 0) {
            $this->syncJournalPreviewFocus(forceRefresh: true);
        }
    }

    private function runEntryBatch(iterable $records, callable $action): array
    {
        try {
            return DB::transaction(function () use ($records, $action): array {
                $changed = 0;
                foreach ($records as $record) {
                    if ($action($record) !== false) {
                        $changed++;
                    }
                }

                return [$changed, 0];
            });
        } catch (ValidationException) {
            return [0, 1];
        } catch (Throwable $exception) {
            report($exception);

            return [0, 1];
        }
    }

    private function notifyBatch(string $label, int $changed, int $failed): void
    {
        if ($changed === 0 && $failed === 0) {
            return;
        }

        app(AdminNotifier::class)->notification(
            title: ucfirst($label),
            body: $changed.' changed'.($failed > 0 ? ' · '.$failed.' failed' : ''),
            status: $failed > 0 ? 'warning' : 'success',
        );
    }

    /** @param array<string, mixed> $data */
    private function saveEditedEntry(BlogPost|Exhibition $entry, array $data): BlogPost|Exhibition
    {
        $updated = DB::transaction(function () use ($entry, $data): BlogPost|Exhibition {
            $locked = $entry->newQuery()->whereKey($entry->getKey())->lockForUpdate()->firstOrFail();
            if ($this->journalEntryMutationState($locked) !== $this->entryEditorOriginal) {
                throw ValidationException::withMessages(['title' => 'This entry changed. Reopen the editor.']);
            }

            return $locked instanceof BlogPost
                ? app(BlogEditorialService::class)->update($locked, $data)
                : app(ExhibitionEditorialService::class)->update($locked, $data);
        });
        $this->entryEditorOriginal = $this->journalEntryMutationState($updated);

        return $updated;
    }

    /** @return array{record:array<string,mixed>,media:list<array<string,mixed>>} */
    private function journalEntryMutationState(BlogPost|Exhibition $entry): array
    {
        $fresh = $entry instanceof BlogPost
            ? BlogPost::query()->findOrFail($entry->getKey())
            : Exhibition::query()->findOrFail($entry->getKey());

        $media = $fresh->mediaUsages()
            ->orderBy('id')
            ->get()
            ->map(static fn ($usage): array => $usage->getAttributes())
            ->values()
            ->all();

        return [
            'record' => $fresh->getAttributes(),
            'media' => $media,
        ];
    }

    private function throwMountedValidation(ValidationException $exception): never
    {
        $path = $this->getMountedActionSchema()->getStatePath();
        $errors = [];
        foreach ($exception->errors() as $field => $messages) {
            $errors[$path.'.'.$field] = $messages;
        }
        throw ValidationException::withMessages($errors);
    }

    private function notifyValidationFailure(string $title, ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first();
        app(AdminNotifier::class)->notification(
            title: $title,
            body: is_string($message) ? $message : 'The requested Journal change is not valid.',
            status: 'danger',
        );
    }

    /** @return array{publish:bool,unpublish:bool,restore:bool,delete:bool} */
    public function selectedEntryCapabilities(): array
    {
        $isBlog = $this->journalTemplate() === JournalTemplate::Blog;
        $records = $isBlog ? $this->selectedPosts() : $this->selectedExhibitions();
        $states = $records->map(static fn (BlogPost|Exhibition $entry): string => (string) $entry->getAttribute('state')
        );

        return [
            'publish' => $isBlog && $states->contains(static fn (string $state): bool => in_array($state, ['draft', 'scheduled', 'unpublished'], true)
            ),
            'unpublish' => $isBlog && $states->contains('published'),
            'restore' => $isBlog && $states->contains(static fn (string $state): bool => in_array($state, ['scheduled', 'unpublished', 'archived'], true)
            ),
            'delete' => $states->contains(static fn (string $state): bool => $isBlog ? ! in_array($state, ['published', 'scheduled'], true) : $state !== 'published'
            ),
        ];
    }

    /** @return EloquentCollection<int, BlogPost> */
    private function selectedPosts(): EloquentCollection
    {
        $ids = collect($this->selectedPostIds)->map(fn (mixed $id): int => (int) $id)->unique()->all();

        return BlogPost::query()->where('site_section_id', $this->sectionId)->whereKey($ids)->orderBy('position')->orderBy('id')->get();
    }

    /** @return EloquentCollection<int, Exhibition> */
    private function selectedExhibitions(): EloquentCollection
    {
        $ids = collect($this->selectedExhibitionIds)->map(fn (mixed $id): int => (int) $id)->unique()->all();

        return Exhibition::query()->where('site_section_id', $this->sectionId)->whereKey($ids)->orderBy('position')->orderBy('id')->get();
    }

    private function post(int $id): BlogPost
    {
        abort_unless($this->journalTemplate() === JournalTemplate::Blog, 404);

        return BlogPost::query()->where('site_section_id', $this->sectionId)->findOrFail($id);
    }

    private function exhibition(int $id): Exhibition
    {
        abort_unless($this->journalTemplate() === JournalTemplate::Exhibitions, 404);

        return Exhibition::query()->where('site_section_id', $this->sectionId)->findOrFail($id);
    }

    private function section(): SiteSection
    {
        return SiteSection::query()->whereKey($this->sectionId)->where('type', SiteSectionType::Journal->value)->where('template', $this->journalTemplate()->value)->firstOrFail();
    }

    private function journalTemplate(): JournalTemplate
    {
        $template = JournalTemplate::tryFrom($this->template);
        abort_unless($template instanceof JournalTemplate, 404);

        return $template;
    }
}
