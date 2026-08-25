<?php

namespace App\Filament\Pages;

use App\Domain\Blog\BlogEditorialService;
use App\Domain\Content\ExhibitionDraftService;
use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalSettingsService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Resources\BlogPosts\BlogPostResource;
use App\Filament\Resources\Exhibitions\ExhibitionResource;
use App\Filament\Support\AdminForm;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Filament\Support\JournalEntryEditorState;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
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
        $siteSection = SiteSection::query()
            ->whereKey((int) $section)
            ->where('type', SiteNodeType::Journal->value)
            ->firstOrFail();
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

    public function commitSearch(string $value): void
    {
        $this->search = trim($value);
        $this->page = 1;
        $this->reloadEntries(false);
    }

    public function commitStatusFilter(string $value): void
    {
        $allowed = $this->journalTemplate() === JournalTemplate::Blog
            ? ['any', 'draft', 'scheduled', 'published', 'unpublished', 'archived']
            : ['any', 'draft', 'published', 'archived'];
        $this->statusFilter = in_array($value, $allowed, true) ? $value : 'any';
        $this->page = 1;
        $this->reloadEntries(false);
    }

    public function commitTimingFilter(string $value): void
    {
        $allowed = ['any', 'upcoming', 'current', 'past', 'unknown'];
        $this->timingFilter = in_array($value, $allowed, true) ? $value : 'any';
        $this->page = 1;
        $this->reloadEntries(false);
    }

    public function setPageSize(mixed $value): void
    {
        $value = (int) $value;
        $this->pageSize = in_array($value, self::PAGE_SIZES, true) ? $value : self::DEFAULT_PAGE_SIZE;
        $this->page = 1;
        $this->reloadEntries(false);
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'any';
        $this->timingFilter = 'any';
        $this->page = 1;
        $this->reloadEntries(false);
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

    public function toggleVisibleSelection(): void
    {
        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $ids = collect($this->posts)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
            $this->selectedPostIds = $this->toggledVisibleSelection($this->selectedPostIds, $ids);
            return;
        }

        $ids = collect($this->exhibitions)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all();
        $this->selectedExhibitionIds = $this->toggledVisibleSelection($this->selectedExhibitionIds, $ids);
    }

    public function movePost(int $postId, string $direction): void
    {
        if (app(BlogEditorialService::class)->move($this->post($postId), $direction)) {
            Notification::make()->title('Journal order updated')->success()->send();
        }
        $this->loadPosts(false);
    }

    public function moveExhibition(int $id, string $direction): void
    {
        if (app(ExhibitionEditorialService::class)->move($this->exhibition($id), $direction)) {
            Notification::make()->title('Exhibition order updated')->success()->send();
        }
        $this->loadExhibitions(false);
    }

    public function publishPost(int $id): void
    {
        $this->runEntryAction('Post published', fn () => app(BlogEditorialService::class)->publish($this->post($id)));
    }

    public function unpublishPost(int $id): void
    {
        $this->runEntryAction('Post unpublished', fn () => app(BlogEditorialService::class)->unpublish($this->post($id)));
    }

    public function archivePost(int $id): void
    {
        $this->runEntryAction('Post archived', fn () => app(BlogEditorialService::class)->archive($this->post($id)));
    }

    public function restorePostDraft(int $id): void
    {
        $this->runEntryAction('Post restored to draft', fn () => app(BlogEditorialService::class)->restoreDraft($this->post($id)));
    }

    public function publishExhibition(int $id): void
    {
        $this->runEntryAction('Exhibition published', fn () => app(ExhibitionEditorialService::class)->publish($this->exhibition($id)));
    }

    public function archiveExhibition(int $id): void
    {
        $this->runEntryAction('Exhibition archived', fn () => app(ExhibitionEditorialService::class)->archive($this->exhibition($id)));
    }

    public function restoreExhibition(int $id): void
    {
        $this->runEntryAction('Exhibition restored', fn () => app(ExhibitionEditorialService::class)->restore($this->exhibition($id)));
    }

    public function moveSelectedEntries(string $direction): void
    {
        if (! in_array($direction, ['up', 'down'], true)) {
            return;
        }

        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $records = $this->selectedPosts();
            if ($direction === 'down') {
                $records = $records->reverse();
            }
            [$ok, $failed] = $this->bestEffort($records, fn (BlogPost $post): bool => app(BlogEditorialService::class)->move($post, $direction));
            $this->notifyBatch('posts reordered', $ok, $failed);
            $this->loadPosts(false);
            return;
        }

        $records = $this->selectedExhibitions();
        if ($direction === 'down') {
            $records = $records->reverse();
        }
        [$ok, $failed] = $this->bestEffort($records, fn (Exhibition $entry): bool => app(ExhibitionEditorialService::class)->move($entry, $direction));
        $this->notifyBatch('exhibitions reordered', $ok, $failed);
        $this->loadExhibitions(false);
    }

    public function publishSelectedPosts(): void
    {
        $this->runPostBatch('posts published', fn (BlogPost $post) => app(BlogEditorialService::class)->publish($post));
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

    public function archiveSelectedPosts(): void
    {
        $this->runPostBatch('posts archived', fn (BlogPost $post) => app(BlogEditorialService::class)->archive($post));
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

    public function publishSelectedExhibitions(): void
    {
        $this->runExhibitionBatch('exhibitions published', fn (Exhibition $entry) => app(ExhibitionEditorialService::class)->publish($entry));
    }

    public function archiveSelectedExhibitions(): void
    {
        $this->runExhibitionBatch('exhibitions archived', fn (Exhibition $entry) => app(ExhibitionEditorialService::class)->archive($entry));
    }

    public function restoreSelectedExhibitions(): void
    {
        $this->runExhibitionBatch('exhibitions restored', function (Exhibition $entry): bool {
            if ($entry->getAttribute('state') !== 'archived') {
                return false;
            }
            app(ExhibitionEditorialService::class)->restore($entry);
            return true;
        });
    }

    public function journalSettingsAction(): Action
    {
        return Action::make('journalSettings')
            ->label('Settings')
            ->fillForm(function (): array {
                $section = $this->section();
                $settings = JournalSetting::forSection($section);
                return [
                    'title' => $section->getAttribute('title'),
                    'navigation_label' => $section->getAttribute('navigation_label'),
                    'slug' => $section->getAttribute('slug'),
                    'listing_title' => $settings->getAttribute('listing_title'),
                    'listing_intro' => $settings->getAttribute('listing_intro'),
                ];
            })
            ->schema([
                AdminForm::section('Journal')->schema([
                    TextInput::make('title')->label('Journal title')->required()->maxLength(160),
                    TextInput::make('navigation_label')->label('Navigation label')->required()->maxLength(120),
                    TextInput::make('slug')->label('Public URL slug')->required()->maxLength(80)
                        ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                        ->helperText('Changing this changes the public Journal URL.'),
                    TextInput::make('listing_title')->label('Listing title')->maxLength(240)->nullable(),
                    MarkdownEditor::make('listing_intro')->label('Listing introduction')
                        ->toolbarButtons([['bold', 'italic', 'link'], ['bulletList', 'orderedList'], ['undo', 'redo']])
                        ->maxLength(10000)->nullable()->columnSpanFull(),
                ])->columns(2),
            ])
            ->modalHeading('Journal settings')
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Save')->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (array $data): void {
                app(JournalSettingsService::class)->update($this->section(), $data);
                $this->loadJournalContext();
                $this->reloadEntries(false);
                Notification::make()->title('Journal settings saved')->success()->send();
            });
    }

    public function addPostAction(): Action
    {
        return $this->editorAction(Action::make('addPost')->label('Add post'), 'Add post', 'Create draft')
            ->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::blog($schema))
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
                Notification::make()->title('Post draft created')->success()->send();
            });
    }

    public function editPostAction(): Action
    {
        return $this->editorAction(Action::make('editPost')->label('Edit'), 'Edit post', 'Save')
            ->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->fillForm(function (array $arguments): array {
                $post = $this->post((int) ($arguments['post'] ?? 0));
                return [...$post->attributesToArray(), ...app(JournalEntryEditorState::class)->for($post)];
            })
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::blog($schema))
            ->action(function (Action $action, array $data, array $arguments): void {
                $post = $this->post((int) ($arguments['post'] ?? 0));
                $data = [...$data,
                    'site_section_id' => $this->sectionId,
                    'state' => $post->getAttribute('state'),
                    'position' => $post->getAttribute('position'),
                    'published_at' => $post->getAttribute('published_at'),
                    'scheduled_at' => $post->getAttribute('scheduled_at'),
                ];
                try {
                    app(BlogEditorialService::class)->update($post, $data);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Post unchanged', $exception);
                    $action->halt();
                    return;
                }
                $this->loadPosts(false);
                Notification::make()->title('Post saved')->success()->send();
            });
    }

    public function schedulePostAction(): Action
    {
        return Action::make('schedulePost')
            ->label('Schedule publication')
            ->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Blog)
            ->schema([DateTimePicker::make('scheduled_at')->label('Publish at')->seconds(false)->required()])
            ->modalHeading('Schedule publication')
            ->modalWidth(Width::Large)
            ->modalSubmitAction(fn (Action $action): Action => $action->label('Schedule')->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action->label('Cancel')->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $data, array $arguments): void {
                try {
                    app(BlogEditorialService::class)->schedule($this->post((int) ($arguments['post'] ?? 0)), $data['scheduled_at'] ?? null);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Post was not scheduled', $exception);
                    $action->halt();
                    return;
                }
                $this->loadPosts();
                Notification::make()->title('Publication scheduled')->success()->send();
            });
    }

    public function addExhibitionAction(): Action
    {
        return $this->editorAction(Action::make('addExhibition')->label('Add exhibition'), 'Add exhibition', 'Create draft')
            ->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Exhibitions)
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::exhibition($schema))
            ->action(function (Action $action, array $data): void {
                $data['site_section_id'] = $this->sectionId;
                try {
                    app(ExhibitionDraftService::class)->create($data);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Exhibition was not created', $exception);
                    $action->halt();
                    return;
                }
                $this->loadExhibitions();
                Notification::make()->title('Exhibition draft created')->success()->send();
            });
    }

    public function editExhibitionAction(): Action
    {
        return $this->editorAction(Action::make('editExhibition')->label('Edit'), 'Edit exhibition', 'Save')
            ->visible(fn (): bool => $this->journalTemplate() === JournalTemplate::Exhibitions)
            ->fillForm(function (array $arguments): array {
                $entry = $this->exhibition((int) ($arguments['exhibition'] ?? 0));
                return [...$entry->attributesToArray(), ...app(JournalEntryEditorState::class)->for($entry)];
            })
            ->schema(fn (Schema $schema): Schema => JournalEntryEditorSchema::exhibition($schema))
            ->action(function (Action $action, array $data, array $arguments): void {
                $entry = $this->exhibition((int) ($arguments['exhibition'] ?? 0));
                $data['site_section_id'] = $this->sectionId;
                try {
                    app(ExhibitionEditorialService::class)->update($entry, $data);
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Exhibition unchanged', $exception);
                    $action->halt();
                    return;
                }
                $this->loadExhibitions(false);
                Notification::make()->title('Exhibition saved')->success()->send();
            });
    }

    public function deletePostAction(): Action
    {
        return $this->deleteAction('deletePost', 'Delete this post?', function (Action $action, array $arguments): void {
            try {
                app(BlogEditorialService::class)->delete($this->post((int) ($arguments['post'] ?? 0)));
            } catch (ValidationException $exception) {
                $this->notifyValidationFailure('Post was not deleted', $exception);
                $action->halt();
                return;
            }
            $postId = (int) ($arguments['post'] ?? 0);
            $this->selectedPostIds = array_values(array_filter($this->selectedPostIds, fn (mixed $id): bool => (int) $id !== $postId));
            $this->loadPosts();
            Notification::make()->title('Post deleted')->success()->send();
        });
    }

    public function deleteExhibitionAction(): Action
    {
        return $this->deleteAction('deleteExhibition', 'Delete this exhibition?', function (Action $action, array $arguments): void {
            try {
                app(ExhibitionEditorialService::class)->delete($this->exhibition((int) ($arguments['exhibition'] ?? 0)));
            } catch (ValidationException $exception) {
                $this->notifyValidationFailure('Exhibition was not deleted', $exception);
                $action->halt();
                return;
            }
            $entryId = (int) ($arguments['exhibition'] ?? 0);
            $this->selectedExhibitionIds = array_values(array_filter($this->selectedExhibitionIds, fn (mixed $id): bool => (int) $id !== $entryId));
            $this->loadExhibitions();
            Notification::make()->title('Exhibition deleted')->success()->send();
        }, 'Media Files are preserved. Only Journal references are removed.');
    }

    public function deleteSelectedPostsAction(): Action
    {
        return $this->deleteAction('deleteSelectedPosts', 'Delete selected posts?', function (): void {
            [$ok, $failed] = $this->bestEffort($this->selectedPosts(), function (BlogPost $post): bool {
                app(BlogEditorialService::class)->delete($post);
                return true;
            });
            $this->selectedPostIds = [];
            $this->notifyBatch('posts deleted', $ok, $failed);
            $this->loadPosts();
        }, 'Published and scheduled posts are kept and reported as failures.');
    }

    public function deleteSelectedExhibitionsAction(): Action
    {
        return $this->deleteAction('deleteSelectedExhibitions', 'Delete selected exhibitions?', function (): void {
            [$ok, $failed] = $this->bestEffort($this->selectedExhibitions(), function (Exhibition $entry): bool {
                app(ExhibitionEditorialService::class)->delete($entry);
                return true;
            });
            $this->selectedExhibitionIds = [];
            $this->notifyBatch('exhibitions deleted', $ok, $failed);
            $this->loadExhibitions();
        }, 'Published exhibitions are kept. Media Files are preserved.');
    }

    private function loadJournalContext(?SiteSection $section = null): void
    {
        $section ??= $this->section();
        $this->journalTitle = (string) $section->getAttribute('title');
        $this->journalPublicUrl = $section->getAttribute('state') === 'published'
            ? app(SiteNodeRoute::class)->url($section)
            : null;
    }

    private function loadPosts(bool $refreshMetrics = true): void
    {
        $records = BlogPost::query()->where('site_section_id', $this->sectionId)->orderBy('position')->orderBy('id')->get();
        $this->unfilteredEntryCount = $records->count();
        $publicIds = $this->journalPublicUrl
            ? BlogEditorialService::publicQuery()->where('site_section_id', $this->sectionId)->pluck('id')->map(fn (mixed $id): int => (int) $id)->all()
            : [];

        if ($refreshMetrics) {
            $this->metrics = [
                ['label' => 'Posts', 'value' => $records->count()],
                ['label' => 'Public', 'value' => count($publicIds)],
                ['label' => 'Draft', 'value' => $records->where('state', 'draft')->count()],
                ['label' => 'Scheduled', 'value' => $records->where('state', 'scheduled')->count()],
                ['label' => 'Unpublished', 'value' => $records->where('state', 'unpublished')->count()],
                ['label' => 'Archived', 'value' => $records->where('state', 'archived')->count()],
            ];
        }

        $search = Str::lower(trim($this->search));
        $filtered = $records->filter(function (BlogPost $post) use ($search): bool {
            if ($this->statusFilter !== 'any' && $post->getAttribute('state') !== $this->statusFilter) {
                return false;
            }
            if ($search === '') {
                return true;
            }
            return Str::contains(Str::lower((string) $post->getAttribute('title').' '.(string) ($post->getAttribute('excerpt') ?? '')), $search);
        })->values();

        $this->setPagination($filtered->count());
        $last = $records->count() - 1;
        $positions = $records->values()->mapWithKeys(fn (BlogPost $post, int $index): array => [(int) $post->getKey() => $index]);
        $this->posts = $filtered->slice(($this->page - 1) * $this->pageSize, $this->pageSize)->values()
            ->map(function (BlogPost $post) use ($last, $positions, $publicIds): array {
                $id = (int) $post->getKey();
                $state = (string) $post->getAttribute('state');
                $published = $post->getAttribute('published_at');
                $scheduled = $post->getAttribute('scheduled_at');
                $publication = match (true) {
                    $state === 'scheduled' && $scheduled instanceof DateTimeInterface => 'Scheduled '.$scheduled->format('M j, Y').' · '.$scheduled->format('H:i'),
                    $published instanceof DateTimeInterface => $published->format('M j, Y'),
                    default => 'Not published',
                };
                return [
                    'id' => $id,
                    'title' => (string) $post->getAttribute('title'),
                    'excerpt' => filled($post->getAttribute('excerpt')) ? Str::limit(trim((string) $post->getAttribute('excerpt')), 140) : null,
                    'publication' => $publication,
                    'state' => $state,
                    'public_url' => in_array($id, $publicIds, true) ? BlogPostResource::publicUrl($post) : null,
                    'can_move_up' => (int) $positions->get($id, 0) > 0,
                    'can_move_down' => (int) $positions->get($id, 0) < $last,
                    'can_delete' => ! in_array($state, ['published', 'scheduled'], true),
                    'delete_help' => in_array($state, ['published', 'scheduled'], true) ? 'Unpublish or cancel schedule before deleting' : null,
                ];
            })->all();
    }

    private function loadExhibitions(bool $refreshMetrics = true): void
    {
        $records = Exhibition::query()->where('site_section_id', $this->sectionId)->orderBy('position')->orderBy('id')->get();
        $this->unfilteredEntryCount = $records->count();
        $today = now();
        $timing = $records->mapWithKeys(fn (Exhibition $entry): array => [(int) $entry->getKey() => $entry->temporalState($today)]);

        if ($refreshMetrics) {
            $this->metrics = [
                ['label' => 'Exhibitions', 'value' => $records->count()],
                ['label' => 'Published', 'value' => $records->where('state', 'published')->count()],
                ['label' => 'Draft', 'value' => $records->where('state', 'draft')->count()],
                ['label' => 'Upcoming', 'value' => $timing->filter(fn (string $state): bool => $state === 'upcoming')->count()],
                ['label' => 'Current', 'value' => $timing->filter(fn (string $state): bool => $state === 'current')->count()],
                ['label' => 'Past', 'value' => $timing->filter(fn (string $state): bool => $state === 'past')->count()],
            ];
        }

        $search = Str::lower(trim($this->search));
        $filtered = $records->filter(function (Exhibition $entry) use ($search, $timing): bool {
            if ($this->statusFilter !== 'any' && $entry->getAttribute('state') !== $this->statusFilter) {
                return false;
            }
            if ($this->timingFilter !== 'any' && $timing->get((int) $entry->getKey()) !== $this->timingFilter) {
                return false;
            }
            if ($search === '') {
                return true;
            }
            $haystack = implode(' ', [
                (string) $entry->getAttribute('title'),
                (string) ($entry->getAttribute('venue') ?? ''),
                (string) ($entry->address() ?? ''),
                (string) ($entry->displayDate() ?? ''),
                (string) ($entry->vernissageDisplay() ?? ''),
            ]);
            return Str::contains(Str::lower($haystack), $search);
        })->values();

        $this->setPagination($filtered->count());
        $last = $records->count() - 1;
        $positions = $records->values()->mapWithKeys(fn (Exhibition $entry, int $index): array => [(int) $entry->getKey() => $index]);
        $this->exhibitions = $filtered->slice(($this->page - 1) * $this->pageSize, $this->pageSize)->values()
            ->map(function (Exhibition $entry) use ($last, $positions, $timing): array {
                $id = (int) $entry->getKey();
                $state = (string) $entry->getAttribute('state');
                $location = collect([$entry->getAttribute('venue'), $entry->address()])
                    ->filter(fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                    ->unique()->implode(' · ');
                return [
                    'id' => $id,
                    'title' => (string) $entry->getAttribute('title'),
                    'location' => $location !== '' ? $location : null,
                    'state' => $state,
                    'timing' => (string) $timing->get($id, 'unknown'),
                    'vernissage' => $entry->vernissageDisplay(),
                    'date_text' => $entry->displayDate() ?? '',
                    'public_url' => $this->journalPublicUrl && $state === 'published' ? ExhibitionResource::publicUrl($entry) : null,
                    'can_move_up' => (int) $positions->get($id, 0) > 0,
                    'can_move_down' => (int) $positions->get($id, 0) < $last,
                    'can_delete' => $state !== 'published',
                    'delete_help' => $state === 'published' ? 'Archive this exhibition before deleting' : null,
                ];
            })->all();
    }

    private function editorAction(Action $action, string $heading, string $submit): Action
    {
        return $action->modalHeading($heading)
            ->modalSubmitAction(fn (Action $submitAction): Action => $submitAction->label($submit)->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $cancelAction): Action => $cancelAction->label('Cancel')->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog']);
    }

    private function deleteAction(string $name, string $heading, callable $callback, ?string $description = null): Action
    {
        $action = Action::make($name)->label('Delete')->color('danger')->requiresConfirmation()
            ->modalHeading($heading)->modalWidth(Width::Large)
            ->modalSubmitAction(fn (Action $submitAction): Action => $submitAction->label('Delete')->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $cancelAction): Action => $cancelAction->label('Cancel')->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])->action($callback);
        return $description !== null ? $action->modalDescription($description) : $action;
    }

    private function reloadEntries(bool $refreshMetrics = true): void
    {
        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $this->loadPosts($refreshMetrics);
            return;
        }
        $this->loadExhibitions($refreshMetrics);
    }

    private function setPagination(int $total): void
    {
        $this->pageSize = in_array($this->pageSize, self::PAGE_SIZES, true) ? $this->pageSize : self::DEFAULT_PAGE_SIZE;
        $this->total = $total;
        $this->pages = max(1, (int) ceil($total / $this->pageSize));
        $this->page = min(max(1, $this->page), $this->pages);
    }

    private function toggledVisibleSelection(array $selected, array $visible): array
    {
        $selectedIds = collect($selected)->map(fn (mixed $id): int => (int) $id)->unique()->values();
        $allVisibleSelected = $visible !== [] && collect($visible)->every(fn (int $id): bool => $selectedIds->containsStrict($id));
        if ($allVisibleSelected) {
            return $selectedIds->reject(fn (int $id): bool => in_array($id, $visible, true))->values()->all();
        }
        return $selectedIds->merge($visible)->unique()->values()->all();
    }

    private function runEntryAction(string $title, callable $action): void
    {
        try {
            $action();
            Notification::make()->title($title)->success()->send();
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Journal entry unchanged', $exception);
        }
        $this->reloadEntries();
    }

    private function runPostBatch(string $label, callable $action): void
    {
        [$ok, $failed] = $this->bestEffort($this->selectedPosts(), $action);
        $this->selectedPostIds = [];
        $this->notifyBatch($label, $ok, $failed);
        $this->loadPosts();
    }

    private function runExhibitionBatch(string $label, callable $action): void
    {
        [$ok, $failed] = $this->bestEffort($this->selectedExhibitions(), $action);
        $this->selectedExhibitionIds = [];
        $this->notifyBatch($label, $ok, $failed);
        $this->loadExhibitions();
    }

    private function bestEffort(iterable $records, callable $action): array
    {
        $ok = 0;
        $failed = 0;
        foreach ($records as $record) {
            try {
                if ($action($record) === false) {
                    $failed++;
                } else {
                    $ok++;
                }
            } catch (Throwable) {
                $failed++;
            }
        }
        return [$ok, $failed];
    }

    private function notifyBatch(string $label, int $ok, int $failed): void
    {
        $notification = Notification::make()->title(ucfirst($label))
            ->body($ok.' succeeded'.($failed > 0 ? ' · '.$failed.' failed' : ''));
        $failed > 0 ? $notification->warning() : $notification->success();
        $notification->send();
    }

    private function notifyValidationFailure(string $title, ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first();
        Notification::make()->title($title)
            ->body(is_string($message) ? $message : 'The requested Journal change is not valid.')
            ->danger()->send();
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
        $template = $this->journalTemplate();
        return SiteSection::query()->whereKey($this->sectionId)
            ->where('type', SiteNodeType::Journal->value)
            ->where('template', $template->value)->firstOrFail();
    }

    private function journalTemplate(): JournalTemplate
    {
        $template = JournalTemplate::tryFrom($this->template);
        abort_unless($template instanceof JournalTemplate, 404);
        return $template;
    }
}
