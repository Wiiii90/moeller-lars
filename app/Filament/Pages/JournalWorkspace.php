<?php

namespace App\Filament\Pages;

use App\Domain\Analytics\ArtistReportingService;
use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalEntryOrderService;
use App\Domain\Content\JournalSettingsService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Domain\Media\PublicMedia;
use App\Filament\Support\AdminForm;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Filament\Support\JournalEntryEditorState;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalEntryMedia;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use Carbon\CarbonInterface;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Throwable;

final class JournalWorkspace extends Page
{
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
    public int $page = 1;
    public int $pageSize = self::DEFAULT_PAGE_SIZE;
    public int $total = 0;
    public int $pages = 1;

    public function mount(int|string $section): void
    {
        $siteSection = SiteSection::query()->whereKey((int) $section)->where('type', SiteNodeType::Journal->value)->firstOrFail();
        $template = $siteSection->journalTemplate();
        abort_unless($template instanceof JournalTemplate, 404);
        $this->sectionId = (int) $siteSection->getKey();
        $this->template = $template->value;
        $this->loadJournalContext($siteSection);
        $this->reloadEntries();
    }

    public function getView(): string
    {
        return match ($this->journalTemplate()) {
            JournalTemplate::Blog => 'filament.resources.blog-posts.pages.list-blog-posts',
            JournalTemplate::Exhibitions => 'filament.resources.exhibitions.pages.list-exhibitions',
        };
    }

    public function updatedSearch(): void { $this->refreshFromFirstPage(); }

    public function updatedStatusFilter(): void
    {
        $allowed = $this->journalTemplate() === JournalTemplate::Blog
            ? ['any', 'draft', 'scheduled', 'published', 'unpublished', 'archived']
            : ['any', 'published', 'unpublished'];
        if (! in_array($this->statusFilter, $allowed, true)) { $this->statusFilter = 'any'; }
        $this->refreshFromFirstPage();
    }

    public function updatedTimingFilter(): void
    {
        if (! in_array($this->timingFilter, ['any', 'upcoming', 'current', 'past', 'unknown'], true)) { $this->timingFilter = 'any'; }
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
        if ($this->page > 1) { $this->page--; $this->reloadEntries(false); }
    }

    public function nextPage(): void
    {
        if ($this->page < $this->pages) { $this->page++; $this->reloadEntries(false); }
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
        if (! $this->canDragSort() || $this->journalTemplate() !== JournalTemplate::Blog) { return; }
        $post = $this->post((int) $id);
        if (app(JournalEntryOrderService::class)->moveToPosition($post, $position)) {
            Notification::make()->title('Journal order updated')->success()->send();
        }
        $this->loadPosts(false);
    }

    public function sortExhibition(int|string $id, int $position): void
    {
        if (! $this->canDragSort() || $this->journalTemplate() !== JournalTemplate::Exhibitions) { return; }
        $entry = $this->exhibition((int) $id);
        if (app(JournalEntryOrderService::class)->moveToPosition($entry, $position)) {
            Notification::make()->title('Exhibition order updated')->success()->send();
        }
        $this->loadExhibitions(false);
    }

    public function togglePostSelection(int $id): void { $this->selectedPostIds = $this->toggleSelection($this->selectedPostIds, $id); }
    public function toggleExhibitionSelection(int $id): void { $this->selectedExhibitionIds = $this->toggleSelection($this->selectedExhibitionIds, $id); }

    public function toggleVisibleSelection(): void
    {
        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $visible = collect($this->posts)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $this->selectedPostIds = $this->toggledVisibleSelection($this->selectedPostIds, $visible);
            return;
        }
        $visible = collect($this->exhibitions)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->selectedExhibitionIds = $this->toggledVisibleSelection($this->selectedExhibitionIds, $visible);
    }

    public function movePost(int $id, string $direction): void
    {
        if (app(BlogEditorialService::class)->move($this->post($id), $direction)) { Notification::make()->title('Journal order updated')->success()->send(); }
        $this->loadPosts(false);
    }

    public function moveExhibition(int $id, string $direction): void
    {
        if (app(ExhibitionEditorialService::class)->move($this->exhibition($id), $direction)) { Notification::make()->title('Exhibition order updated')->success()->send(); }
        $this->loadExhibitions(false);
    }

    public function publishPost(int $id): void { $this->runEntryAction('Post published', fn () => app(BlogEditorialService::class)->publish($this->post($id))); }
    public function unpublishPost(int $id): void { $this->runEntryAction('Post unpublished', fn () => app(BlogEditorialService::class)->unpublish($this->post($id))); }
    public function archivePost(int $id): void { $this->runEntryAction('Post archived', fn () => app(BlogEditorialService::class)->archive($this->post($id))); }
    public function restorePostDraft(int $id): void { $this->runEntryAction('Post restored to draft', fn () => app(BlogEditorialService::class)->restoreDraft($this->post($id))); }
    public function publishExhibition(int $id): void { $this->runEntryAction('Exhibition published', fn () => app(ExhibitionEditorialService::class)->publish($this->exhibition($id))); }
    public function unpublishExhibition(int $id): void { $this->runEntryAction('Exhibition unpublished', fn () => app(ExhibitionEditorialService::class)->unpublish($this->exhibition($id))); }

    public function moveSelectedEntries(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) { return; }
        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $records = $this->selectedPosts();
            if ($direction === 'down') { $records = $records->reverse(); }
            [$ok, $failed] = $this->bestEffort($records, fn (BlogPost $post): bool => app(BlogEditorialService::class)->move($post, $direction));
            $this->notifyBatch('posts reordered', $ok, $failed);
            $this->loadPosts(false);
            return;
        }
        $records = $this->selectedExhibitions();
        if ($direction === 'down') { $records = $records->reverse(); }
        [$ok, $failed] = $this->bestEffort($records, fn (Exhibition $entry): bool => app(ExhibitionEditorialService::class)->move($entry, $direction));
        $this->notifyBatch('exhibitions reordered', $ok, $failed);
        $this->loadExhibitions(false);
    }

    public function publishSelectedPosts(): void { $this->runPostBatch('posts published', fn (BlogPost $post) => app(BlogEditorialService::class)->publish($post)); }
    public function archiveSelectedPosts(): void { $this->runPostBatch('posts archived', fn (BlogPost $post) => app(BlogEditorialService::class)->archive($post)); }

    public function unpublishSelectedPosts(): void
    {
        $this->runPostBatch('posts unpublished', function (BlogPost $post): bool {
            if ($post->getAttribute('state') !== 'published') { return false; }
            app(BlogEditorialService::class)->unpublish($post);
            return true;
        });
    }

    public function restoreSelectedPosts(): void
    {
        $this->runPostBatch('posts restored to draft', function (BlogPost $post): bool {
            if (! in_array((string) $post->getAttribute('state'), ['scheduled', 'unpublished', 'archived'], true)) { return false; }
            app(BlogEditorialService::class)->restoreDraft($post);
            return true;
        });
    }

    public function journalSettingsAction(): Action
    {
        return Action::make('journalSettings')
            ->label('Settings')
            ->fillForm(function (): array {
                $section = $this->section();
                return [
                    'template' => (string) $section->getAttribute('template'),
                    'confirm_template_change' => false,
                    'title' => $section->getAttribute('title'),
                    'navigation_label' => $section->getAttribute('navigation_label'),
                    'slug' => $section->getAttribute('slug'),
                ];
            })
            ->schema([
                AdminForm::section('Journal')->schema([
                    Select::make('template')->label('Template')->options(JournalTemplate::options())->required()->live(),
                    Checkbox::make('confirm_template_change')
                        ->label('Confirm template switch')
                        ->helperText('Existing entries are kept intact. Entries from the inactive template are hidden until you switch back.')
                        ->visible(fn (Get $get): bool => (string) $get('template') !== $this->template),
                    TextInput::make('title')->label('Journal title')->required()->maxLength(160),
                    TextInput::make('navigation_label')->label('Navigation label')->required()->maxLength(120),
                    TextInput::make('slug')->label('Public URL slug')->required()->maxLength(80)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->helperText('Changing this changes the public Journal URL.'),
                ])->columns(2),
            ])
            ->modalHeading('Journal settings')
            ->modalSubmitActionLabel('Save')
            ->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $data): void {
                $newTemplate = (string) ($data['template'] ?? '');
                if ($newTemplate !== $this->template && ! (bool) ($data['confirm_template_change'] ?? false)) {
                    $this->notifyValidationFailure('Journal settings unchanged', ValidationException::withMessages([
                        'template' => 'Confirm the template switch before saving.',
                    ]));
                    $action->halt();
                    return;
                }
                unset($data['confirm_template_change']);
                $updated = app(JournalSettingsService::class)->update($this->section(), $data);
                $template = $updated->journalTemplate();
                abort_unless($template instanceof JournalTemplate, 404);
                $this->template = $template->value;
                $this->search = '';
                $this->statusFilter = 'any';
                $this->timingFilter = 'any';
                $this->selectedPostIds = [];
                $this->selectedExhibitionIds = [];
                $this->page = 1;
                $this->loadJournalContext($updated);
                $this->reloadEntries();
                Notification::make()->title('Journal settings saved')->success()->send();
            });
    }

    public function addPostAction(): Action
    {
        return Action::make('addPost')->label('Add post')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::blog($schema))
            ->modalHeading('Add post')->modalSubmitActionLabel('Create draft')->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $data): void {
                $data['site_section_id'] = $this->sectionId;
                try { app(BlogEditorialService::class)->createDraft($data); }
                catch (ValidationException $exception) { $this->notifyValidationFailure('Post was not created', $exception); $action->halt(); return; }
                $this->loadPosts(); Notification::make()->title('Post draft created')->success()->send();
            });
    }

    public function editPostAction(): Action
    {
        return Action::make('editPost')->label('Edit')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->fillForm(function (array $arguments): array {
                $post = $this->post((int) ($arguments['post'] ?? 0));
                return [...$post->attributesToArray(), ...app(JournalEntryEditorState::class)->for($post)];
            })
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::blog($schema))
            ->modalHeading('Edit post')->modalSubmitActionLabel('Save')->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $data, array $arguments): void {
                $post = $this->post((int) ($arguments['post'] ?? 0));
                $data = [...$data, 'site_section_id' => $this->sectionId, 'state' => $post->getAttribute('state'), 'position' => $post->getAttribute('position'), 'published_at' => $post->getAttribute('published_at'), 'scheduled_at' => $post->getAttribute('scheduled_at')];
                try { app(BlogEditorialService::class)->update($post, $data); }
                catch (ValidationException $exception) { $this->notifyValidationFailure('Post unchanged', $exception); $action->halt(); return; }
                $this->loadPosts(false); Notification::make()->title('Post saved')->success()->send();
            });
    }

    public function schedulePostAction(): Action
    {
        return Action::make('schedulePost')->label('Schedule publication')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->schema([DateTimePicker::make('scheduled_at')->label('Publish at')->seconds(false)->required()])
            ->modalHeading('Schedule publication')->modalSubmitActionLabel('Schedule')->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::Large)->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $data, array $arguments): void {
                try { app(BlogEditorialService::class)->schedule($this->post((int) ($arguments['post'] ?? 0)), $data['scheduled_at'] ?? null); }
                catch (ValidationException $exception) { $this->notifyValidationFailure('Post was not scheduled', $exception); $action->halt(); return; }
                $this->loadPosts(); Notification::make()->title('Publication scheduled')->success()->send();
            });
    }

    public function addExhibitionAction(): Action
    {
        return Action::make('addExhibition')->label('Add exhibition')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Exhibitions)
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::exhibition($schema))
            ->modalHeading('Add exhibition')->modalSubmitActionLabel('Create exhibition')->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $data): void {
                $data['site_section_id'] = $this->sectionId;
                try { app(ExhibitionEditorialService::class)->createDraft($data); }
                catch (ValidationException $exception) { $this->notifyValidationFailure('Exhibition was not created', $exception); $action->halt(); return; }
                $this->loadExhibitions(); Notification::make()->title('Exhibition created')->success()->send();
            });
    }

    public function editExhibitionAction(): Action
    {
        return Action::make('editExhibition')->label('Edit')->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Exhibitions)
            ->fillForm(function (array $arguments): array {
                $entry = $this->exhibition((int) ($arguments['exhibition'] ?? 0));
                return [...$entry->attributesToArray(), ...app(JournalEntryEditorState::class)->for($entry)];
            })
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::exhibition($schema))
            ->modalHeading('Edit exhibition')->modalSubmitActionLabel('Save')->modalCancelActionLabel('Cancel')
            ->modalWidth(Width::SevenExtraLarge)->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $data, array $arguments): void {
                $entry = $this->exhibition((int) ($arguments['exhibition'] ?? 0));
                $data['site_section_id'] = $this->sectionId;
                try { app(ExhibitionEditorialService::class)->update($entry, $data); }
                catch (ValidationException $exception) { $this->notifyValidationFailure('Exhibition unchanged', $exception); $action->halt(); return; }
                $this->loadExhibitions(false); Notification::make()->title('Exhibition saved')->success()->send();
            });
    }

    public function deletePostAction(): Action { return $this->deleteAction('deletePost', 'post'); }
    public function deleteExhibitionAction(): Action { return $this->deleteAction('deleteExhibition', 'exhibition'); }

    private function deleteAction(string $name, string $type): Action
    {
        return Action::make($name)->label('Delete')->color('danger')->requiresConfirmation()
            ->modalHeading('Delete this '.$type.'?')->modalDescription('Media Files are preserved. Only this Journal entry and its references are removed.')
            ->modalSubmitActionLabel('Delete')->modalCancelActionLabel('Cancel')->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $arguments) use ($type): void {
                $id = (int) ($arguments[$type] ?? 0);
                try {
                    if ($type === 'post') { app(BlogEditorialService::class)->delete($this->post($id)); $this->selectedPostIds = array_values(array_filter($this->selectedPostIds, fn (mixed $selected): bool => (int) $selected !== $id)); $this->loadPosts(); }
                    else { app(ExhibitionEditorialService::class)->delete($this->exhibition($id)); $this->selectedExhibitionIds = array_values(array_filter($this->selectedExhibitionIds, fn (mixed $selected): bool => (int) $selected !== $id)); $this->loadExhibitions(); }
                } catch (ValidationException $exception) { $this->notifyValidationFailure(ucfirst($type).' was not deleted', $exception); $action->halt(); return; }
                Notification::make()->title(ucfirst($type).' deleted')->success()->send();
            });
    }

    public function deleteSelectedPostsAction(): Action
    {
        return Action::make('deleteSelectedPosts')->label('Delete selected')->color('danger')->requiresConfirmation()
            ->modalHeading('Delete selected posts?')->modalDescription('Published and scheduled posts are kept. Media Files are preserved.')
            ->modalSubmitActionLabel('Delete')->modalCancelActionLabel('Cancel')->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (): void {
                [$ok, $failed] = $this->bestEffort($this->selectedPosts(), fn (BlogPost $post): bool => tap(true, fn () => app(BlogEditorialService::class)->delete($post)));
                $this->selectedPostIds = []; $this->notifyBatch('posts deleted', $ok, $failed); $this->loadPosts();
            });
    }

    public function deleteSelectedExhibitionsAction(): Action
    {
        return Action::make('deleteSelectedExhibitions')->label('Delete selected')->color('danger')->requiresConfirmation()
            ->modalHeading('Delete selected exhibitions?')->modalDescription('Published exhibitions are kept. Media Files are preserved.')
            ->modalSubmitActionLabel('Delete')->modalCancelActionLabel('Cancel')->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (): void {
                [$ok, $failed] = $this->bestEffort($this->selectedExhibitions(), fn (Exhibition $entry): bool => tap(true, fn () => app(ExhibitionEditorialService::class)->delete($entry)));
                $this->selectedExhibitionIds = []; $this->notifyBatch('exhibitions deleted', $ok, $failed); $this->loadExhibitions();
            });
    }

    private function refreshFromFirstPage(): void { $this->page = 1; $this->reloadEntries(false); }

    private function loadJournalContext(?SiteSection $section = null): void
    {
        $section ??= $this->section();
        $this->journalTitle = (string) $section->getAttribute('title');
        $this->journalSlug = (string) $section->getAttribute('slug');
        $this->journalPublicUrl = $section->getAttribute('state') === 'published' ? app(SiteNodeRoute::class)->url($section) : null;
    }

    private function loadPosts(bool $refreshMetrics = true): void
    {
        if ($refreshMetrics) { $this->loadPostMetrics(); }
        $query = BlogPost::query()->where('site_section_id', $this->sectionId);
        if ($this->statusFilter !== 'any') { $query->where('state', $this->statusFilter); }
        $term = trim($this->search);
        if ($term !== '') { $query->where(fn (Builder $search) => $search->where('title', 'ilike', '%'.$term.'%')->orWhere('excerpt', 'ilike', '%'.$term.'%')); }
        $this->total = (clone $query)->count(); $this->setPagination($this->total);

        $canonicalIds = BlogPost::query()->where('site_section_id', $this->sectionId)->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $ranks = $canonicalIds->flip();
        $query->with(['mediaUsages' => function ($usages): void { $usages->where('role', JournalEntryMedia::ROLE_COVER)->with('mediaAsset.variants'); }]);
        /** @var EloquentCollection<int, BlogPost> $records */
        $records = $query->orderBy('position')->orderBy('id')->forPage($this->page, $this->pageSize)->get();
        $now = now(); $count = $canonicalIds->count();
        $this->posts = $records->map(function (BlogPost $post) use ($ranks, $count, $now): array {
            $state = (string) $post->getAttribute('state'); $published = $post->getAttribute('published_at'); $scheduled = $post->getAttribute('scheduled_at');
            $publication = match (true) {
                $state === 'scheduled' && $scheduled instanceof DateTimeInterface => 'Scheduled '.$scheduled->format('M j, Y').' · '.$scheduled->format('H:i'),
                $published instanceof DateTimeInterface => $published->format('M j, Y'),
                default => 'Not published',
            };
            $rank = ((int) ($ranks[(int) $post->getKey()] ?? 0)) + 1;
            return [
                'id' => (int) $post->getKey(), 'rank' => $rank, 'title' => (string) $post->getAttribute('title'),
                'excerpt' => filled($post->getAttribute('excerpt')) ? Str::limit(trim((string) $post->getAttribute('excerpt')), 140) : null,
                'publication' => $publication, 'state' => $state, 'thumbnail_url' => $this->coverThumbnailUrl($post),
                'public_url' => $this->journalPublicUrl !== null && $this->postIsPublic($post, $now) ? route('journal.show', ['section' => $this->journalSlug, 'slug' => $post->getAttribute('slug')]) : null,
                'can_move_up' => $rank > 1, 'can_move_down' => $rank < $count,
                'can_delete' => ! in_array($state, ['published', 'scheduled'], true),
                'delete_help' => in_array($state, ['published', 'scheduled'], true) ? 'Unpublish or cancel schedule before deleting' : null,
            ];
        })->all();
    }

    private function loadExhibitions(bool $refreshMetrics = true): void
    {
        if ($refreshMetrics) { $this->loadExhibitionMetrics(); }
        $query = Exhibition::query()->where('site_section_id', $this->sectionId);
        if ($this->statusFilter === 'published') { $query->where('state', 'published'); }
        elseif ($this->statusFilter === 'unpublished') { $query->where('state', '!=', 'published'); }
        $this->applyTimingFilter($query);
        $term = trim($this->search);
        if ($term !== '') {
            $query->where(function (Builder $search) use ($term): void {
                $search->where('title', 'ilike', '%'.$term.'%')->orWhere('venue', 'ilike', '%'.$term.'%')
                    ->orWhere('location_text', 'ilike', '%'.$term.'%')->orWhere('city', 'ilike', '%'.$term.'%')
                    ->orWhere('country', 'ilike', '%'.$term.'%')->orWhere('date_text', 'ilike', '%'.$term.'%');
            });
        }
        $this->total = (clone $query)->count(); $this->setPagination($this->total);
        $canonicalIds = Exhibition::query()->where('site_section_id', $this->sectionId)->orderBy('position')->orderBy('id')->pluck('id')->map(fn ($id): int => (int) $id)->values();
        $ranks = $canonicalIds->flip(); $count = $canonicalIds->count();
        $query->with(['mediaUsages' => function ($usages): void { $usages->where('role', JournalEntryMedia::ROLE_COVER)->with('mediaAsset.variants'); }]);
        /** @var EloquentCollection<int, Exhibition> $records */
        $records = $query->orderBy('position')->orderBy('id')->forPage($this->page, $this->pageSize)->get(); $now = now();
        $this->exhibitions = $records->map(function (Exhibition $entry) use ($ranks, $count, $now): array {
            $internalState = (string) $entry->getAttribute('state');
            $state = $internalState === 'published' ? 'published' : 'unpublished';
            $rank = ((int) ($ranks[(int) $entry->getKey()] ?? 0)) + 1;
            $location = collect([$entry->getAttribute('venue'), $entry->getAttribute('city')])->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')->map(fn (string $value): string => trim($value))->unique()->implode(' · ');
            return [
                'id' => (int) $entry->getKey(), 'rank' => $rank, 'title' => (string) $entry->getAttribute('title'), 'location' => $location !== '' ? $location : null,
                'state' => $state, 'timing' => $entry->temporalState($now), 'vernissage' => $entry->vernissageDisplay(), 'date_text' => $entry->displayDate() ?? '',
                'thumbnail_url' => $this->coverThumbnailUrl($entry),
                'public_url' => $this->journalPublicUrl !== null && $internalState === 'published' ? $this->journalPublicUrl : null,
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
            ['label' => 'Archived', 'value' => $records->where('state', 'archived')->count(), 'description' => 'Retained posts'],
        ];
    }

    private function loadExhibitionMetrics(): void
    {
        $records = Exhibition::query()->where('site_section_id', $this->sectionId)->get(['id', 'state', 'starts_on', 'ends_on']);
        $this->unfilteredEntryCount = $records->count(); $now = now(); $timing = $records->map(fn (Exhibition $entry): string => $entry->temporalState($now));
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

    private function applyTimingFilter(Builder $query): void
    {
        $today = now()->toDateString();
        if ($this->timingFilter === 'upcoming') { $query->whereDate('starts_on', '>', $today); return; }
        if ($this->timingFilter === 'unknown') { $query->whereNull('starts_on'); return; }
        if ($this->timingFilter === 'current') {
            $query->whereNotNull('starts_on')->whereDate('starts_on', '<=', $today)->where(function (Builder $current) use ($today): void {
                $current->where(fn (Builder $range) => $range->whereNotNull('ends_on')->whereDate('ends_on', '>=', $today))
                    ->orWhere(fn (Builder $single) => $single->whereNull('ends_on')->whereDate('starts_on', '=', $today));
            }); return;
        }
        if ($this->timingFilter === 'past') {
            $query->whereNotNull('starts_on')->where(function (Builder $past) use ($today): void {
                $past->where(fn (Builder $range) => $range->whereNotNull('ends_on')->whereDate('ends_on', '<', $today))
                    ->orWhere(fn (Builder $single) => $single->whereNull('ends_on')->whereDate('starts_on', '<', $today));
            });
        }
    }

    private function coverThumbnailUrl(BlogPost|Exhibition $entry): ?string
    {
        $usage = $entry->getRelationValue('mediaUsages')->first(); if (! $usage instanceof JournalEntryMedia) { return null; }
        $asset = $usage->getRelationValue('mediaAsset'); if (! $asset instanceof MediaAsset) { return null; }
        $variant = $asset->getRelationValue('variants')->first(fn (MediaVariant $candidate): bool => $candidate->getAttribute('variant_kind') === PublicMedia::THUMBNAIL_KIND && $candidate->getAttribute('transform_profile') === PublicMedia::PUBLIC_TRANSFORM_PROFILE && $candidate->getAttribute('state') === 'available');
        return $variant instanceof MediaVariant ? route('admin.media.variant', $variant) : null;
    }

    private function postIsPublic(BlogPost $post, CarbonInterface $now): bool
    {
        $state = (string) $post->getAttribute('state'); $published = $post->getAttribute('published_at');
        if ($state === 'published' && $published instanceof CarbonInterface) { return $published->lessThanOrEqualTo($now); }
        $scheduled = $post->getAttribute('scheduled_at');
        return $state === 'scheduled' && $scheduled instanceof CarbonInterface && $scheduled->lessThanOrEqualTo($now);
    }

    private function reloadEntries(bool $refreshMetrics = true): void
    {
        $this->journalTemplate() === JournalTemplate::Blog ? $this->loadPosts($refreshMetrics) : $this->loadExhibitions($refreshMetrics);
    }

    private function setPagination(int $total): void
    {
        $this->pageSize = $this->normalizePageSize($this->pageSize); $this->total = $total;
        $this->pages = max(1, (int) ceil($total / $this->pageSize)); $this->page = min(max(1, $this->page), $this->pages);
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

    private function runEntryAction(string $successTitle, callable $action): void
    {
        try { $action(); Notification::make()->title($successTitle)->success()->send(); }
        catch (ValidationException $exception) { $this->notifyValidationFailure('Journal entry unchanged', $exception); }
        $this->reloadEntries();
    }

    private function runPostBatch(string $label, callable $action): void
    {
        [$ok, $failed] = $this->bestEffort($this->selectedPosts(), $action); $this->selectedPostIds = [];
        $this->notifyBatch($label, $ok, $failed); $this->loadPosts();
    }

    private function bestEffort(iterable $records, callable $action): array
    {
        $ok = 0; $failed = 0;
        foreach ($records as $record) {
            try { $action($record) === false ? $failed++ : $ok++; }
            catch (ValidationException) { $failed++; }
            catch (Throwable $exception) { report($exception); $failed++; }
        }
        return [$ok, $failed];
    }

    private function notifyBatch(string $label, int $ok, int $failed): void
    {
        $notification = Notification::make()->title(ucfirst($label))->body($ok.' succeeded'.($failed > 0 ? ' · '.$failed.' failed' : ''));
        $failed > 0 ? $notification->warning() : $notification->success(); $notification->send();
    }

    private function notifyValidationFailure(string $title, ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first();
        Notification::make()->title($title)->body(is_string($message) ? $message : 'The requested Journal change is not valid.')->danger()->send();
    }

    private function selectedPosts(): EloquentCollection
    {
        $ids = collect($this->selectedPostIds)->map(fn (mixed $id): int => (int) $id)->unique()->all();
        return BlogPost::query()->where('site_section_id', $this->sectionId)->whereKey($ids)->orderBy('position')->orderBy('id')->get();
    }

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
        return SiteSection::query()->whereKey($this->sectionId)->where('type', SiteNodeType::Journal->value)->where('template', $this->journalTemplate()->value)->firstOrFail();
    }

    private function journalTemplate(): JournalTemplate
    {
        $template = JournalTemplate::tryFrom($this->template); abort_unless($template instanceof JournalTemplate, 404); return $template;
    }
}
