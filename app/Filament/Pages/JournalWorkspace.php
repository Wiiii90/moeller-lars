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
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\SiteSection;
use App\Routing\SiteNodeRoute;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
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
    /** @var list<int> */
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

    /** @var list<array{label:string,value:int}> */
    public array $metrics = [];

    /** @var list<array<string, mixed>> */
    public array $posts = [];

    /** @var list<array<string, mixed>> */
    public array $exhibitions = [];

    public int $unfilteredEntryCount = 0;

    public string $search = '';

    public string $statusFilter = 'any';

    public string $timingFilter = 'any';

    /** @var list<int|string> */
    public array $selectedPostIds = [];

    /** @var list<int|string> */
    public array $selectedExhibitionIds = [];

    public int $page = 1;

    public int $pageSize = self::DEFAULT_PAGE_SIZE;

    public int $total = 0;

    public int $pages = 1;

    public function mount(int|string $section): void
    {
        /** @var SiteSection $siteSection */
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

    public function updatedSearch(): void
    {
        $this->resetPageAndReload();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPageAndReload();
    }

    public function updatedTimingFilter(): void
    {
        $this->resetPageAndReload();
    }

    public function updatedPageSize(mixed $value): void
    {
        $value = (int) $value;
        $this->pageSize = in_array($value, self::PAGE_SIZES, true) ? $value : self::DEFAULT_PAGE_SIZE;
        $this->resetPageAndReload();
    }

    public function resetFilters(): void
    {
        $this->search = '';
        $this->statusFilter = 'any';
        $this->timingFilter = 'any';
        $this->page = 1;
        $this->reloadEntries();
    }

    public function previousPage(): void
    {
        if ($this->page <= 1) {
            return;
        }

        $this->page--;
        $this->reloadEntries();
    }

    public function nextPage(): void
    {
        if ($this->page >= $this->pages) {
            return;
        }

        $this->page++;
        $this->reloadEntries();
    }

    public function toggleVisibleSelection(): void
    {
        if ($this->journalTemplate() === JournalTemplate::Blog) {
            $visibleIds = collect($this->posts)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
            $this->selectedPostIds = $this->toggledVisibleSelection($this->selectedPostIds, $visibleIds);

            return;
        }

        $visibleIds = collect($this->exhibitions)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $this->selectedExhibitionIds = $this->toggledVisibleSelection($this->selectedExhibitionIds, $visibleIds);
    }

    public function movePost(int $postId, string $direction): void
    {
        $post = $this->post($postId);
        if (app(BlogEditorialService::class)->move($post, $direction)) {
            Notification::make()->title('Journal order updated')->success()->send();
        }

        $this->loadPosts();
    }

    public function moveExhibition(int $exhibitionId, string $direction): void
    {
        $exhibition = $this->exhibition($exhibitionId);
        if (app(ExhibitionEditorialService::class)->move($exhibition, $direction)) {
            Notification::make()->title('Exhibition order updated')->success()->send();
        }

        $this->loadExhibitions();
    }

    public function publishPost(int $postId): void
    {
        $this->runEntryAction('Post published', fn () => app(BlogEditorialService::class)->publish($this->post($postId)));
    }

    public function unpublishPost(int $postId): void
    {
        $this->runEntryAction('Post unpublished', fn () => app(BlogEditorialService::class)->unpublish($this->post($postId)));
    }

    public function archivePost(int $postId): void
    {
        $this->runEntryAction('Post archived', fn () => app(BlogEditorialService::class)->archive($this->post($postId)));
    }

    public function restorePostDraft(int $postId): void
    {
        $this->runEntryAction('Post restored to draft', fn () => app(BlogEditorialService::class)->restoreDraft($this->post($postId)));
    }

    public function publishExhibition(int $exhibitionId): void
    {
        $this->runEntryAction('Exhibition published', fn () => app(ExhibitionEditorialService::class)->publish($this->exhibition($exhibitionId)));
    }

    public function archiveExhibition(int $exhibitionId): void
    {
        $this->runEntryAction('Exhibition archived', fn () => app(ExhibitionEditorialService::class)->archive($this->exhibition($exhibitionId)));
    }

    public function restoreExhibitionDraft(int $exhibitionId): void
    {
        $this->runEntryAction('Exhibition restored to draft', fn () => app(ExhibitionEditorialService::class)->restoreDraft($this->exhibition($exhibitionId)));
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
            [$succeeded, $failed] = $this->bestEffort(
                $records,
                fn (BlogPost $post): bool => app(BlogEditorialService::class)->move($post, $direction),
            );
            $this->notifyBatch('posts reordered', $succeeded, $failed);
            $this->loadPosts();

            return;
        }

        $records = $this->selectedExhibitions();
        if ($direction === 'down') {
            $records = $records->reverse();
        }
        [$succeeded, $failed] = $this->bestEffort(
            $records,
            fn (Exhibition $exhibition): bool => app(ExhibitionEditorialService::class)->move($exhibition, $direction),
        );
        $this->notifyBatch('exhibitions reordered', $succeeded, $failed);
        $this->loadExhibitions();
    }

    public function publishSelectedPosts(): void
    {
        $this->runPostBatch('posts published', fn (BlogPost $post) => app(BlogEditorialService::class)->publish($post));
    }

    public function unpublishSelectedPosts(): void
    {
        $this->runPostBatch('posts unpublished', function (BlogPost $post): bool {
            if ((string) $post->getAttribute('state') !== 'published') {
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
        $this->runExhibitionBatch(
            'exhibitions published',
            fn (Exhibition $exhibition) => app(ExhibitionEditorialService::class)->publish($exhibition),
        );
    }

    public function archiveSelectedExhibitions(): void
    {
        $this->runExhibitionBatch(
            'exhibitions archived',
            fn (Exhibition $exhibition) => app(ExhibitionEditorialService::class)->archive($exhibition),
        );
    }

    public function restoreSelectedExhibitions(): void
    {
        $this->runExhibitionBatch(
            'exhibitions restored to draft',
            function (Exhibition $exhibition): bool {
                if ((string) $exhibition->getAttribute('state') !== 'archived') {
                    return false;
                }

                app(ExhibitionEditorialService::class)->restoreDraft($exhibition);

                return true;
            },
        );
    }

    public function journalSettingsAction(): Action
    {
        return Action::make('journalSettings')
            ->label('Settings')
            ->fillForm(function (): array {
                $section = $this->section();
                $settings = JournalSetting::forSection($section);

                return [
                    'title' => (string) $section->getAttribute('title'),
                    'navigation_label' => (string) $section->getAttribute('navigation_label'),
                    'slug' => (string) $section->getAttribute('slug'),
                    'listing_title' => $settings->getAttribute('listing_title'),
                    'listing_intro' => $settings->getAttribute('listing_intro'),
                ];
            })
            ->schema([
                AdminForm::section('Journal')
                    ->schema([
                        TextInput::make('title')
                            ->label('Journal title')
                            ->required()
                            ->maxLength(160),
                        TextInput::make('navigation_label')
                            ->label('Navigation label')
                            ->required()
                            ->maxLength(120),
                        TextInput::make('slug')
                            ->label('Public URL slug')
                            ->required()
                            ->maxLength(80)
                            ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                            ->helperText('Changing this changes the public Journal URL.'),
                        TextInput::make('listing_title')
                            ->label('Listing title')
                            ->maxLength(240)
                            ->nullable(),
                        MarkdownEditor::make('listing_intro')
                            ->label('Listing introduction')
                            ->toolbarButtons([
                                ['bold', 'italic', 'link'],
                                ['bulletList', 'orderedList'],
                                ['undo', 'redo'],
                            ])
                            ->maxLength(10000)
                            ->nullable()
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ])
            ->modalHeading('Journal settings')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save')
                ->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (array $data): void {
                app(JournalSettingsService::class)->update($this->section(), $data);
                $this->loadJournalContext();
                $this->reloadEntries();
                Notification::make()->title('Journal settings saved')->success()->send();
            });
    }

    public function addPostAction(): Action
    {
        return Action::make('addPost')
            ->label('Add post')
            ->schema(fn (Schema $schema): Schema => BlogPostResource::form($schema))
            ->modalHeading('Add post')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Create draft')
                ->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (array $data): void {
                $data['site_section_id'] = $this->sectionId;
                app(BlogEditorialService::class)->createDraft($data);
                $this->loadPosts();
                Notification::make()->title('Post draft created')->success()->send();
            });
    }

    public function addExhibitionAction(): Action
    {
        return Action::make('addExhibition')
            ->label('Add exhibition')
            ->schema([
                TextInput::make('title')
                    ->required()
                    ->maxLength(240)
                    ->live(onBlur: true)
                    ->afterStateUpdated(function (?string $state, callable $set, callable $get): void {
                        if (blank($get('slug')) && filled($state)) {
                            $set('slug', Str::slug($state));
                        }
                    }),
                TextInput::make('slug')
                    ->label('Entry URL slug')
                    ->required()
                    ->maxLength(180)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')
                    ->unique('exhibitions', 'slug'),
                TextInput::make('date_text')
                    ->label('Displayed exhibition dates')
                    ->required()
                    ->maxLength(160),
                TextInput::make('opening_text')
                    ->label('Vernissage')
                    ->maxLength(500)
                    ->nullable(),
                Select::make('kind')
                    ->options([
                        'solo' => 'Solo',
                        'group' => 'Group',
                    ])
                    ->nullable(),
                DatePicker::make('starts_on')->nullable(),
                DatePicker::make('ends_on')->nullable(),
                TextInput::make('venue')->maxLength(240)->nullable(),
                TextInput::make('city')->maxLength(160)->nullable(),
                TextInput::make('country')->maxLength(160)->nullable(),
                TextInput::make('location_text')
                    ->label('Location / address')
                    ->maxLength(500)
                    ->nullable()
                    ->columnSpanFull(),
                MarkdownEditor::make('description')
                    ->label('Description')
                    ->toolbarButtons([
                        ['bold', 'italic', 'link'],
                        ['bulletList', 'orderedList'],
                        ['undo', 'redo'],
                    ])
                    ->maxLength(10000)
                    ->nullable()
                    ->columnSpanFull(),
                TextInput::make('external_url')->url()->maxLength(2048)->nullable(),
                TextInput::make('directions_url')->label('Directions URL')->url()->maxLength(2048)->nullable(),
            ])
            ->modalHeading('Add exhibition')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Create draft')
                ->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::SevenExtraLarge)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (array $data): void {
                $data['site_section_id'] = $this->sectionId;
                app(ExhibitionDraftService::class)->create($data);
                $this->loadExhibitions();
                Notification::make()->title('Exhibition draft created')->success()->send();
            });
    }

    public function deletePostAction(): Action
    {
        return Action::make('deletePost')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this post?')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $arguments): void {
                try {
                    app(BlogEditorialService::class)->delete($this->post((int) $arguments['post']));
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Post was not deleted', $exception);
                    $action->halt();

                    return;
                }

                $this->selectedPostIds = array_values(array_filter(
                    $this->selectedPostIds,
                    static fn (mixed $id): bool => (int) $id !== (int) $arguments['post'],
                ));
                $this->loadPosts();
                Notification::make()->title('Post deleted')->success()->send();
            });
    }

    public function deleteExhibitionAction(): Action
    {
        return Action::make('deleteExhibition')
            ->label('Delete')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete this exhibition?')
            ->modalDescription('Media Files are preserved. Only their Exhibition relationship is removed.')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (Action $action, array $arguments): void {
                try {
                    app(ExhibitionEditorialService::class)->delete($this->exhibition((int) $arguments['exhibition']));
                } catch (ValidationException $exception) {
                    $this->notifyValidationFailure('Exhibition was not deleted', $exception);
                    $action->halt();

                    return;
                }

                $this->selectedExhibitionIds = array_values(array_filter(
                    $this->selectedExhibitionIds,
                    static fn (mixed $id): bool => (int) $id !== (int) $arguments['exhibition'],
                ));
                $this->loadExhibitions();
                Notification::make()->title('Exhibition deleted')->success()->send();
            });
    }

    public function deleteSelectedPostsAction(): Action
    {
        return Action::make('deleteSelectedPosts')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected posts?')
            ->modalDescription('Published and scheduled posts are kept and reported as failures.')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (): void {
                [$succeeded, $failed] = $this->bestEffort(
                    $this->selectedPosts(),
                    function (BlogPost $post): bool {
                        app(BlogEditorialService::class)->delete($post);

                        return true;
                    },
                );
                $this->selectedPostIds = [];
                $this->notifyBatch('posts deleted', $succeeded, $failed);
                $this->loadPosts();
            });
    }

    public function deleteSelectedExhibitionsAction(): Action
    {
        return Action::make('deleteSelectedExhibitions')
            ->label('Delete selected')
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Delete selected exhibitions?')
            ->modalDescription('Published exhibitions are kept. Media Files are preserved when other exhibitions are deleted.')
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Delete')
                ->extraAttributes(['class' => 'admin-dialog-footer__primary']))
            ->modalCancelAction(fn (Action $action): Action => $action
                ->label('Cancel')
                ->extraAttributes(['class' => 'admin-dialog-footer__cancel']))
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => 'admin-task-dialog'])
            ->action(function (): void {
                [$succeeded, $failed] = $this->bestEffort(
                    $this->selectedExhibitions(),
                    function (Exhibition $exhibition): bool {
                        app(ExhibitionEditorialService::class)->delete($exhibition);

                        return true;
                    },
                );
                $this->selectedExhibitionIds = [];
                $this->notifyBatch('exhibitions deleted', $succeeded, $failed);
                $this->loadExhibitions();
            });
    }

    private function loadJournalContext(?SiteSection $section = null): void
    {
        $section ??= $this->section();
        $this->journalTitle = (string) $section->getAttribute('title');
        $this->journalPublicUrl = (string) $section->getAttribute('state') === 'published'
            ? app(SiteNodeRoute::class)->url($section)
            : null;
    }

    private function loadPosts(): void
    {
        /** @var EloquentCollection<int, BlogPost> $records */
        $records = BlogPost::query()
            ->where('site_section_id', $this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $this->unfilteredEntryCount = $records->count();

        $publicIds = [];
        if ($this->journalPublicUrl !== null) {
            $publicIds = BlogEditorialService::publicQuery()
                ->where('site_section_id', $this->sectionId)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->all();
        }

        $this->metrics = [
            ['label' => 'Posts', 'value' => $records->count()],
            ['label' => 'Public', 'value' => count($publicIds)],
            ['label' => 'Draft', 'value' => $records->where('state', 'draft')->count()],
            ['label' => 'Scheduled', 'value' => $records->where('state', 'scheduled')->count()],
            ['label' => 'Unpublished', 'value' => $records->where('state', 'unpublished')->count()],
            ['label' => 'Archived', 'value' => $records->where('state', 'archived')->count()],
        ];

        $search = Str::lower(trim($this->search));
        $filtered = $records->filter(function (BlogPost $post) use ($search): bool {
            if ($this->statusFilter !== 'any' && (string) $post->getAttribute('state') !== $this->statusFilter) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            $haystack = Str::lower(implode(' ', [
                (string) $post->getAttribute('title'),
                (string) ($post->getAttribute('excerpt') ?? ''),
            ]));

            return Str::contains($haystack, $search);
        })->values();

        $this->setPagination($filtered->count());
        $lastIndex = $records->count() - 1;
        $positionById = $records->values()->mapWithKeys(
            static fn (BlogPost $post, int $index): array => [(int) $post->getKey() => $index],
        );

        $this->posts = $filtered
            ->slice(($this->page - 1) * $this->pageSize, $this->pageSize)
            ->values()
            ->map(function (BlogPost $post) use ($lastIndex, $positionById, $publicIds): array {
                $id = (int) $post->getKey();
                $state = (string) $post->getAttribute('state');
                $publishedAt = $post->getAttribute('published_at');
                $scheduledAt = $post->getAttribute('scheduled_at');
                $publication = match (true) {
                    $state === 'scheduled' && $scheduledAt instanceof DateTimeInterface => 'Scheduled '.$scheduledAt->format('M j, Y').' · '.$scheduledAt->format('H:i'),
                    $publishedAt instanceof DateTimeInterface => $publishedAt->format('M j, Y'),
                    default => 'Not published',
                };
                $excerpt = $post->getAttribute('excerpt');
                $position = (int) ($positionById->get($id) ?? 0);

                return [
                    'id' => $id,
                    'title' => (string) $post->getAttribute('title'),
                    'excerpt' => is_string($excerpt) && trim($excerpt) !== '' ? Str::limit(trim($excerpt), 140) : null,
                    'publication' => $publication,
                    'state' => $state,
                    'edit_url' => BlogPostResource::getUrl('edit', ['record' => $post]),
                    'public_url' => in_array($id, $publicIds, true) ? BlogPostResource::publicUrl($post) : null,
                    'can_move_up' => $position > 0,
                    'can_move_down' => $position < $lastIndex,
                    'can_delete' => ! in_array($state, ['published', 'scheduled'], true),
                    'delete_help' => in_array($state, ['published', 'scheduled'], true)
                        ? 'Unpublish or cancel schedule before deleting'
                        : null,
                ];
            })
            ->all();
    }

    private function loadExhibitions(): void
    {
        /** @var EloquentCollection<int, Exhibition> $records */
        $records = Exhibition::query()
            ->where('site_section_id', $this->sectionId)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
        $this->unfilteredEntryCount = $records->count();
        $today = now();
        $temporalById = $records->mapWithKeys(
            static fn (Exhibition $exhibition): array => [(int) $exhibition->getKey() => $exhibition->temporalState($today)],
        );

        $this->metrics = [
            ['label' => 'Exhibitions', 'value' => $records->count()],
            ['label' => 'Published', 'value' => $records->where('state', 'published')->count()],
            ['label' => 'Draft', 'value' => $records->where('state', 'draft')->count()],
            ['label' => 'Upcoming', 'value' => $temporalById->filter(static fn (string $state): bool => $state === 'upcoming')->count()],
            ['label' => 'Current', 'value' => $temporalById->filter(static fn (string $state): bool => $state === 'current')->count()],
            ['label' => 'Past', 'value' => $temporalById->filter(static fn (string $state): bool => $state === 'past')->count()],
        ];

        $search = Str::lower(trim($this->search));
        $filtered = $records->filter(function (Exhibition $exhibition) use ($search, $temporalById): bool {
            if ($this->statusFilter !== 'any' && (string) $exhibition->getAttribute('state') !== $this->statusFilter) {
                return false;
            }
            if ($this->timingFilter !== 'any'
                && (string) $temporalById->get((int) $exhibition->getKey()) !== $this->timingFilter) {
                return false;
            }
            if ($search === '') {
                return true;
            }

            $haystack = Str::lower(implode(' ', array_map(
                static fn (string $field): string => (string) ($exhibition->getAttribute($field) ?? ''),
                ['title', 'venue', 'city', 'country', 'location_text', 'date_text', 'opening_text'],
            )));

            return Str::contains($haystack, $search);
        })->values();

        $this->setPagination($filtered->count());
        $lastIndex = $records->count() - 1;
        $positionById = $records->values()->mapWithKeys(
            static fn (Exhibition $exhibition, int $index): array => [(int) $exhibition->getKey() => $index],
        );

        $this->exhibitions = $filtered
            ->slice(($this->page - 1) * $this->pageSize, $this->pageSize)
            ->values()
            ->map(function (Exhibition $exhibition) use ($lastIndex, $positionById, $temporalById): array {
                $id = (int) $exhibition->getKey();
                $location = collect([
                    $exhibition->getAttribute('venue'),
                    $exhibition->getAttribute('city'),
                    $exhibition->getAttribute('country'),
                ])->filter(static fn (mixed $value): bool => is_string($value) && trim($value) !== '')
                    ->map(static fn (string $value): string => trim($value))
                    ->implode(' · ');
                if ($location === '') {
                    $fallback = $exhibition->getAttribute('location_text');
                    $location = is_string($fallback) && trim($fallback) !== '' ? trim($fallback) : null;
                }

                $kind = (string) ($exhibition->getAttribute('kind') ?? '');
                $vernissage = $exhibition->getAttribute('opening_text');
                $position = (int) ($positionById->get($id) ?? 0);
                $state = (string) $exhibition->getAttribute('state');

                return [
                    'id' => $id,
                    'title' => (string) $exhibition->getAttribute('title'),
                    'location' => $location,
                    'format' => match ($kind) {
                        'solo' => 'Solo',
                        'group' => 'Group',
                        default => '—',
                    },
                    'state' => $state,
                    'timing' => (string) $temporalById->get($id, 'unknown'),
                    'vernissage' => is_string($vernissage) && trim($vernissage) !== '' ? trim($vernissage) : null,
                    'date_text' => (string) $exhibition->getAttribute('date_text'),
                    'edit_url' => ExhibitionResource::getUrl('edit', ['record' => $exhibition]),
                    'public_url' => $this->journalPublicUrl !== null && $state === 'published'
                        ? ExhibitionResource::publicUrl($exhibition)
                        : null,
                    'can_move_up' => $position > 0,
                    'can_move_down' => $position < $lastIndex,
                    'can_delete' => $state !== 'published',
                    'delete_help' => $state === 'published' ? 'Archive this exhibition before deleting' : null,
                ];
            })
            ->all();
    }

    private function resetPageAndReload(): void
    {
        $this->page = 1;
        $this->reloadEntries();
    }

    private function reloadEntries(): void
    {
        $template = $this->journalTemplate();
        if ($template === JournalTemplate::Blog) {
            $this->loadPosts();

            return;
        }

        if ($template === JournalTemplate::Exhibitions) {
            $this->loadExhibitions();

            return;
        }

        abort(404);
    }

    private function setPagination(int $total): void
    {
        $this->pageSize = in_array($this->pageSize, self::PAGE_SIZES, true)
            ? $this->pageSize
            : self::DEFAULT_PAGE_SIZE;
        $this->total = $total;
        $this->pages = max(1, (int) ceil($total / $this->pageSize));
        $this->page = min(max(1, $this->page), $this->pages);
    }

    /** @param list<int|string> $selected @param list<int> $visibleIds @return list<int> */
    private function toggledVisibleSelection(array $selected, array $visibleIds): array
    {
        $selected = collect($selected)->map(static fn (mixed $id): int => (int) $id)->unique()->values();
        $allVisibleSelected = $visibleIds !== []
            && collect($visibleIds)->every(static fn (int $id): bool => $selected->containsStrict($id));

        if ($allVisibleSelected) {
            return $selected->reject(static fn (int $id): bool => in_array($id, $visibleIds, true))->values()->all();
        }

        return $selected->merge($visibleIds)->unique()->values()->all();
    }

    private function runEntryAction(string $successTitle, callable $action): void
    {
        try {
            $action();
            Notification::make()->title($successTitle)->success()->send();
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Journal entry unchanged', $exception);
        }

        $this->reloadEntries();
    }

    private function runPostBatch(string $label, callable $action): void
    {
        [$succeeded, $failed] = $this->bestEffort($this->selectedPosts(), $action);
        $this->selectedPostIds = [];
        $this->notifyBatch($label, $succeeded, $failed);
        $this->loadPosts();
    }

    private function runExhibitionBatch(string $label, callable $action): void
    {
        [$succeeded, $failed] = $this->bestEffort($this->selectedExhibitions(), $action);
        $this->selectedExhibitionIds = [];
        $this->notifyBatch($label, $succeeded, $failed);
        $this->loadExhibitions();
    }

    /** @return array{0:int,1:int} */
    private function bestEffort(iterable $records, callable $action): array
    {
        $succeeded = 0;
        $failed = 0;

        foreach ($records as $record) {
            try {
                if ($action($record) === false) {
                    $failed++;
                } else {
                    $succeeded++;
                }
            } catch (Throwable) {
                $failed++;
            }
        }

        return [$succeeded, $failed];
    }

    private function notifyBatch(string $label, int $succeeded, int $failed): void
    {
        $notification = Notification::make()->title(ucfirst($label));
        $notification->body($succeeded.' succeeded'.($failed > 0 ? ' · '.$failed.' failed' : ''));
        $failed > 0 ? $notification->warning() : $notification->success();
        $notification->send();
    }

    private function notifyValidationFailure(string $title, ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first();
        Notification::make()
            ->title($title)
            ->body(is_string($message) ? $message : 'The requested Journal change is not valid.')
            ->danger()
            ->send();
    }

    /** @return EloquentCollection<int, BlogPost> */
    private function selectedPosts(): EloquentCollection
    {
        $ids = collect($this->selectedPostIds)->map(static fn (mixed $id): int => (int) $id)->unique()->all();

        return BlogPost::query()
            ->where('site_section_id', $this->sectionId)
            ->whereKey($ids)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    /** @return EloquentCollection<int, Exhibition> */
    private function selectedExhibitions(): EloquentCollection
    {
        $ids = collect($this->selectedExhibitionIds)->map(static fn (mixed $id): int => (int) $id)->unique()->all();

        return Exhibition::query()
            ->where('site_section_id', $this->sectionId)
            ->whereKey($ids)
            ->orderBy('position')
            ->orderBy('id')
            ->get();
    }

    private function post(int $postId): BlogPost
    {
        abort_unless($this->journalTemplate() === JournalTemplate::Blog, 404);

        /** @var BlogPost $post */
        $post = BlogPost::query()
            ->where('site_section_id', $this->sectionId)
            ->findOrFail($postId);

        return $post;
    }

    private function exhibition(int $exhibitionId): Exhibition
    {
        abort_unless($this->journalTemplate() === JournalTemplate::Exhibitions, 404);

        /** @var Exhibition $exhibition */
        $exhibition = Exhibition::query()
            ->where('site_section_id', $this->sectionId)
            ->findOrFail($exhibitionId);

        return $exhibition;
    }

    private function section(): SiteSection
    {
        $template = $this->journalTemplate();

        /** @var SiteSection $section */
        $section = SiteSection::query()
            ->whereKey($this->sectionId)
            ->where('type', SiteNodeType::Journal->value)
            ->where('template', $template->value)
            ->firstOrFail();

        return $section;
    }

    private function journalTemplate(): JournalTemplate
    {
        $template = JournalTemplate::tryFrom($this->template);
        abort_unless($template instanceof JournalTemplate, 404);

        return $template;
    }
}
