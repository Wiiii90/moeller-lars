<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalSettingsService;
use App\Domain\Content\JournalTemplate;
use App\Filament\Support\JournalEntryEditorSchema;
use App\Filament\Support\JournalEntryEditorState;
use App\Models\BlogPost;
use App\Models\Exhibition;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

trait JournalWorkspaceStage
{
    /** The only unsaved creation form; it submits directly to the Journal table. */
    public array $newJournalFormData = [];

    /** @var array<int,array{form:array<string,mixed>,original:array<string,mixed>,dirty:bool}> */
    public array $selectedJournalEdits = [];

    public ?int $selectedJournalEntryId = null;

    public array $selectedJournalFormData = [];

    public string $journalStagePanel = 'new';

    public function journalNewForm(Schema $schema): Schema
    {
        return JournalEntryEditorSchema::stageEditor($schema, $this->journalTemplate(), creating: true)
            ->statePath('newJournalFormData');
    }

    public function journalSelectedForm(Schema $schema): Schema
    {
        return JournalEntryEditorSchema::stageEditor($schema, $this->journalTemplate(), creating: false)
            ->statePath('selectedJournalFormData');
    }

    private function defaultNewJournalEntry(): array
    {
        return [
            ...app(JournalSettingsService::class)->editorDefaults($this->section()),
            'title' => '',
            'stage_publication_mode' => 'publish',
        ];
    }

    private function journalStageSessionKey(): string
    {
        return 'journal.stage.editor.'.(int) Filament::auth()->id().'.'.$this->sectionId;
    }

    private function restoreJournalStageEditor(): void
    {
        // Old session-only staged forms are left intact for manual recovery.
        // The editor no longer consumes or mutates that rejected queue.
        $saved = session()->get($this->journalStageSessionKey(), []);
        $saved = is_array($saved) && ($saved['template'] ?? null) === $this->template ? $saved : [];
        $legacy = session()->get('journal.stage.drafts.'.(int) Filament::auth()->id().'.'.$this->sectionId, []);
        $legacyNew = is_array($legacy) && ($legacy['template'] ?? null) === $this->template
            ? ($legacy['new'] ?? []) : [];
        $new = $saved['new'] ?? $legacyNew;
        $this->journalNewForm->fill(is_array($new) && $new !== [] ? $new : $this->defaultNewJournalEntry());

        $this->selectedJournalEntryId = null;
        $this->selectedJournalEdits = [];
        $this->journalStagePanel = ($saved['panel'] ?? 'new') === 'settings' ? 'settings' : 'new';
        $this->publishJournalEditorPreview();
    }

    private function persistJournalStage(): void
    {
        session()->put($this->journalStageSessionKey(), [
            'template' => $this->template,
            'panel' => $this->journalStagePanel,
            'new' => $this->newJournalFormData,
        ]);
    }

    public function updatedNewJournalFormData(): void
    {
        if ($this->journalStagePanel === 'new') {
            $this->rememberNewJournalEntry();
        }
    }

    public function updatedSelectedJournalFormData(): void
    {
        if ($this->journalStagePanel === 'selected') {
            $this->rememberSelectedJournalEdit();
        }
    }

    public function rememberNewJournalEntry(bool $refreshPreview = true): void
    {
        $form = $this->journalNewForm->getRawState();
        if (! is_array($form)) {
            return;
        }
        // Livewire has already written a live-bound field into the public
        // property before invoking this callback. Equality to the property
        // therefore cannot tell us whether the private preview is stale.
        $this->newJournalFormData = $form;
        $this->persistJournalStage();
        if ($refreshPreview) {
            $this->publishJournalEditorPreview();
        }
    }

    /** @return list<int> */
    public function journalStageSelectedIds(): array
    {
        $raw = $this->journalTemplate() === JournalTemplate::Blog
            ? $this->selectedPostIds : $this->selectedExhibitionIds;

        return collect($raw)->map(static fn (mixed $id): int => (int) $id)
            ->filter(static fn (int $id): bool => $id > 0)->unique()->values()->all();
    }

    public function journalStageSelectedPosition(): int
    {
        $index = array_search($this->selectedJournalEntryId, $this->journalStageSelectedIds(), true);

        return $index === false ? 0 : $index + 1;
    }

    private function stageEntry(int $id): BlogPost|Exhibition
    {
        return $this->journalTemplate() === JournalTemplate::Blog
            ? $this->post($id) : $this->exhibition($id);
    }

    private function stageEntryForm(BlogPost|Exhibition $entry): array
    {
        return [
            ...$entry->attributesToArray(),
            ...app(JournalEntryEditorState::class)->for($entry),
        ];
    }

    private function loadSelectedJournalEntry(int $id): void
    {
        $entry = $this->stageEntry($id);
        $this->selectedJournalEntryId = $id;
        if (! isset($this->selectedJournalEdits[$id]) || ! $this->selectedJournalEdits[$id]['dirty']) {
            $this->selectedJournalEdits[$id] = [
                'form' => $this->stageEntryForm($entry),
                'original' => $this->journalEntryMutationState($entry),
                'dirty' => false,
            ];
        }
        $this->journalSelectedForm->fill($this->selectedJournalEdits[$id]['form']);
        if (! $this->selectedJournalEdits[$id]['dirty']) {
            // Compare against the actual quick-editor fields, not unseen
            // full-record attributes discarded during form hydration.
            $this->selectedJournalEdits[$id]['form'] = $this->journalSelectedForm->getRawState();
        }
    }

    /** Selecting more rows never reloads the editor or iframe for the first row. */
    private function onJournalStageSelectionChanged(): void
    {
        $ids = $this->journalStageSelectedIds();
        $first = $ids[0] ?? null;
        if ($first === $this->selectedJournalEntryId
            && ($first === null || $this->journalStagePanel === 'selected')) {
            return;
        }
        if ($this->journalStagePanel === 'new') {
            $this->rememberNewJournalEntry(false);
        } elseif ($this->journalStagePanel === 'selected') {
            $this->rememberSelectedJournalEdit(false);
        }

        if ($first !== null) {
            if ($first !== $this->selectedJournalEntryId) {
                $this->loadSelectedJournalEntry($first);
            }
            $this->journalStagePanel = 'selected';
        } else {
            $this->selectedJournalEntryId = null;
            if ($this->journalStagePanel === 'selected') {
                $this->journalStagePanel = 'new';
            }
        }
        $this->persistJournalStage();
        $this->publishJournalEditorPreview();
    }

    public function showJournalStagePanel(string $panel): void
    {
        if (! in_array($panel, ['new', 'selected', 'settings'], true)
            || $panel === $this->journalStagePanel) {
            return;
        }
        if ($panel === 'selected' && $this->journalStageSelectedIds() === []) {
            return;
        }
        if ($this->journalStagePanel === 'new') {
            $this->rememberNewJournalEntry(false);
        } elseif ($this->journalStagePanel === 'selected') {
            $this->rememberSelectedJournalEdit(false);
        }
        $this->journalStagePanel = $panel;
        if ($panel === 'selected') {
            $first = $this->journalStageSelectedIds()[0];
            if ($this->selectedJournalEntryId !== $first) {
                $this->loadSelectedJournalEntry($first);
            }
        }
        $this->persistJournalStage();
        // Settings does not alter the edited entry and therefore must not
        // reload or resize the Public iframe.
        if ($panel !== 'settings') {
            $this->publishJournalEditorPreview();
        }
    }

    public function navigateSelectedJournalEntry(int $direction): void
    {
        $ids = $this->journalStageSelectedIds();
        if ($ids === []) {
            return;
        }
        $this->rememberSelectedJournalEdit(false);
        $index = array_search($this->selectedJournalEntryId, $ids, true);
        $current = $index === false ? 0 : $index;
        $next = ($current + ($direction < 0 ? -1 : 1) + count($ids)) % count($ids);
        $this->loadSelectedJournalEntry($ids[$next]);
        $this->journalStagePanel = 'selected';
        $this->persistJournalStage();
        $this->publishJournalEditorPreview();
    }

    public function rememberSelectedJournalEdit(bool $refreshPreview = true): void
    {
        $id = $this->selectedJournalEntryId;
        if ($id === null || ! isset($this->selectedJournalEdits[$id])) {
            return;
        }
        $form = $this->journalSelectedForm->getRawState();
        if (! is_array($form) || $form === $this->selectedJournalEdits[$id]['form']) {
            return;
        }
        $this->selectedJournalEdits[$id]['form'] = $form;
        $this->selectedJournalEdits[$id]['dirty'] = true;
        if ($refreshPreview) {
            $this->publishJournalEditorPreview();
        }
    }

    /** Reuse the protected Public renderer for both new and selected edits. */
    private function publishJournalEditorPreview(): void
    {
        $form = null;
        $id = null;
        if ($this->journalStagePanel === 'new') {
            $form = $this->newJournalFormData;
        } elseif ($this->journalStagePanel === 'selected' && $this->selectedJournalEntryId !== null) {
            $id = $this->selectedJournalEntryId;
            $form = $this->selectedJournalEdits[$id]['form'] ?? null;
        }
        $key = 'journal.stage.preview.'.$this->getId();
        $next = is_array($form) ? [
            'section_id' => $this->sectionId,
            'template' => $this->template,
            'entry_id' => $id,
            'form' => $form,
        ] : null;
        if (session()->get($key) === $next) {
            return;
        }
        if ($next === null) {
            session()->forget($key);
        } else {
            session()->put($key, $next);
        }
        $this->refreshJournalPreview();
    }

    public function discardNewJournalEntry(): void
    {
        $this->journalNewForm->fill($this->defaultNewJournalEntry());
        $this->rememberNewJournalEntry(false);
        $this->publishJournalEditorPreview();
    }

    public function submitNewJournalEntry(): void
    {
        $this->rememberNewJournalEntry(false);
        $data = [...$this->newJournalFormData, 'site_section_id' => $this->sectionId];
        try {
            DB::transaction(function () use ($data): void {
                if ($this->journalTemplate() === JournalTemplate::Blog) {
                    $service = app(BlogEditorialService::class);
                    $entry = $service->createDraft($data);
                    match ((string) ($data['stage_publication_mode'] ?? 'publish')) {
                        'draft' => null,
                        'schedule' => $service->schedule($entry, $data['stage_scheduled_at'] ?? null),
                        'publish' => $service->publish($entry),
                        default => throw ValidationException::withMessages([
                            'stage_publication_mode' => 'Choose a supported publication action.',
                        ]),
                    };
                } else {
                    $service = app(ExhibitionEditorialService::class);
                    $entry = $service->createDraft($data);
                    $service->publish($entry);
                }
            });
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Journal entry was not submitted', $exception);

            return;
        }

        $this->journalNewForm->fill($this->defaultNewJournalEntry());
        $this->rememberNewJournalEntry(false);
        $this->persistJournalStage();
        $this->reloadEntries();
        $this->publishJournalEditorPreview();
        app(AdminNotifier::class)->notification(title: 'Journal entry submitted', status: 'success');
    }

    public function discardSelectedJournalEdit(): void
    {
        $id = $this->selectedJournalEntryId;
        if ($id === null) {
            return;
        }
        unset($this->selectedJournalEdits[$id]);
        $this->loadSelectedJournalEntry($id);
        $this->publishJournalEditorPreview();
    }

    public function discardAllSelectedJournalEdits(): void
    {
        $id = $this->selectedJournalEntryId;
        $this->selectedJournalEdits = [];
        if ($id !== null) {
            $this->loadSelectedJournalEntry($id);
        }
        $this->publishJournalEditorPreview();
    }

    public function submitSelectedJournalEdit(): void
    {
        $this->submitSelectedJournalEdits(false);
    }

    public function submitAllSelectedJournalEdits(): void
    {
        $this->submitSelectedJournalEdits(true);
    }

    private function submitSelectedJournalEdits(bool $all): void
    {
        $this->rememberSelectedJournalEdit(false);
        $ids = $all ? $this->journalStageSelectedIds() : [$this->selectedJournalEntryId];
        $edits = [];
        foreach ($ids as $id) {
            if ($id !== null && ($this->selectedJournalEdits[$id]['dirty'] ?? false)) {
                $edits[$id] = $this->selectedJournalEdits[$id];
            }
        }
        if ($edits === []) {
            return;
        }

        try {
            DB::transaction(function () use ($edits): void {
                foreach ($edits as $id => $edit) {
                    $entry = $this->stageEntry((int) $id);
                    $entry = $entry->newQuery()->where('site_section_id', $this->sectionId)
                        ->whereKey($id)->lockForUpdate()->firstOrFail();
                    if ($this->journalEntryMutationState($entry) !== $edit['original']) {
                        throw ValidationException::withMessages([
                            'title' => 'This entry was changed elsewhere. Discard these edits to reload it.',
                        ]);
                    }
                    $data = [...$edit['form'], 'site_section_id' => $this->sectionId];
                    if ($entry instanceof BlogPost) {
                        $data = [
                            ...$data,
                            'state' => $entry->getAttribute('state'),
                            'position' => $entry->getAttribute('position'),
                            'published_at' => $entry->getAttribute('published_at'),
                            'scheduled_at' => $entry->getAttribute('scheduled_at'),
                        ];
                        app(BlogEditorialService::class)->update($entry, $data);
                    } else {
                        app(ExhibitionEditorialService::class)->update($entry, $data);
                    }
                }
            });
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure('Selected Journal changes were not submitted', $exception);

            return;
        }

        foreach (array_keys($edits) as $id) {
            unset($this->selectedJournalEdits[$id]);
        }
        if ($this->selectedJournalEntryId !== null) {
            $this->loadSelectedJournalEntry($this->selectedJournalEntryId);
        }
        $this->reloadEntries();
        $this->publishJournalEditorPreview();
        app(AdminNotifier::class)->notification(
            title: count($edits) === 1 ? 'Journal entry saved' : count($edits).' Journal entries saved',
            status: 'success',
        );
    }
}
