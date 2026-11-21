<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\BlogEditorialService;
use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalSettingsService;
use App\Domain\Content\JournalTemplate;
use App\Filament\Support\JournalEntryEditorSchema;
use Filament\Facades\Filament;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait JournalWorkspaceStage
{
    /** @var list<array{key:string,form:array<string,mixed>}> */
    public array $stagedJournalDrafts = [];

    public ?string $selectedStagedJournalDraftKey = null;

    /** @var array<string,mixed> */
    public array $stagedJournalFormData = [];

    public string $journalStagePanel = 'new';

    public function journalStagedForm(Schema $schema): Schema
    {
        return JournalEntryEditorSchema::staged($schema, $this->journalTemplate())
            ->statePath('stagedJournalFormData');
    }

    private function stagedJournalSessionKey(): string
    {
        return 'journal.stage.drafts.'.(int) Filament::auth()->id().'.'.$this->sectionId;
    }

    private function restoreStagedJournalDrafts(): void
    {
        $saved = session()->get($this->stagedJournalSessionKey(), []);
        if (! is_array($saved) || ($saved['template'] ?? null) !== $this->template) {
            return;
        }
        $items = $saved['items'] ?? [];
        if (! is_array($items)) {
            return;
        }
        $this->stagedJournalDrafts = array_values(array_filter($items, static fn (mixed $row): bool =>
            is_array($row) && is_string($row['key'] ?? null) && is_array($row['form'] ?? null)
        ));
        if ($this->stagedJournalDrafts === []) {
            return;
        }

        $preferred = $saved['selected'] ?? null;
        $this->selectedStagedJournalDraftKey = is_string($preferred) && $this->stagedDraftIndex($preferred) !== null
            ? $preferred : $this->stagedJournalDrafts[0]['key'];
        $this->journalStagePanel = 'staged';
        $this->fillSelectedStagedJournalDraft();
        $this->publishStagedJournalPreview();
    }

    private function persistStagedJournalDrafts(): void
    {
        if ($this->stagedJournalDrafts === []) {
            session()->forget($this->stagedJournalSessionKey());

            return;
        }

        session()->put($this->stagedJournalSessionKey(), [
            'template' => $this->template,
            'selected' => $this->selectedStagedJournalDraftKey,
            'items' => $this->stagedJournalDrafts,
        ]);
    }

    private function stagedDraftIndex(?string $key): ?int
    {
        if ($key === null) {
            return null;
        }
        foreach ($this->stagedJournalDrafts as $index => $row) {
            if ($row['key'] === $key) {
                return $index;
            }
        }

        return null;
    }

    public function stagedJournalPosition(): int
    {
        $index = $this->stagedDraftIndex($this->selectedStagedJournalDraftKey);

        return $index === null ? 0 : $index + 1;
    }

    public function createStagedJournalDraft(): void
    {
        $this->rememberStagedJournalDraft();
        $key = Str::random(12);
        $form = [
            ...app(JournalSettingsService::class)->editorDefaults($this->section()),
            'title' => '',
            'stage_publication_mode' => 'publish',
        ];
        $this->stagedJournalDrafts[] = ['key' => $key, 'form' => $form];
        $this->selectedStagedJournalDraftKey = $key;
        $this->journalStagePanel = 'staged';
        $this->fillSelectedStagedJournalDraft();
        $this->persistStagedJournalDrafts();
        $this->publishStagedJournalPreview();
    }

    public function showJournalStagePanel(string $panel): void
    {
        if (! in_array($panel, ['new', 'staged', 'settings'], true)) {
            return;
        }
        if ($panel === 'staged' && $this->stagedJournalDrafts === []) {
            return;
        }
        $this->rememberStagedJournalDraft();
        $this->journalStagePanel = $panel;
        $this->refreshJournalPreview();
    }

    public function navigateStagedJournalDraft(int $direction): void
    {
        if ($this->stagedJournalDrafts === []) {
            return;
        }
        $this->rememberStagedJournalDraft();
        $count = count($this->stagedJournalDrafts);
        $current = $this->stagedDraftIndex($this->selectedStagedJournalDraftKey) ?? 0;
        $next = ($current + ($direction < 0 ? -1 : 1) + $count) % $count;
        $this->selectedStagedJournalDraftKey = $this->stagedJournalDrafts[$next]['key'];
        $this->journalStagePanel = 'staged';
        $this->fillSelectedStagedJournalDraft();
        $this->persistStagedJournalDrafts();
        $this->publishStagedJournalPreview();
    }

    private function fillSelectedStagedJournalDraft(): void
    {
        $index = $this->stagedDraftIndex($this->selectedStagedJournalDraftKey);
        if ($index === null) {
            return;
        }
        $form = $this->stagedJournalDrafts[$index]['form'];
        $assetIds = collect([
            $form['cover_media_asset_id'] ?? null,
            ...array_map(static fn (mixed $row): mixed => is_array($row) ? ($row['media_asset_id'] ?? null) : null,
                is_array($form['gallery_images'] ?? null) ? $form['gallery_images'] : []),
        ])->filter(static fn (mixed $id): bool => is_numeric($id))->map(static fn (mixed $id): int => (int) $id)->unique()->values();
        if ($assetIds->isNotEmpty()) {
            foreach (\App\Models\MediaAsset::query()->whereIn('id', $assetIds)->get() as $asset) {
                \App\Filament\Support\MediaAssetSelect::primeOptionLabel($asset);
            }
        }

        $this->journalStagedForm->fill($form);
    }

    /** Persist the current Stage form, including rich text and media ordering. */
    public function rememberStagedJournalDraft(): void
    {
        $index = $this->stagedDraftIndex($this->selectedStagedJournalDraftKey);
        if ($index === null) {
            return;
        }
        $form = $this->journalStagedForm->getRawState();
        if (! is_array($form) || $form === $this->stagedJournalDrafts[$index]['form']) {
            return;
        }

        $this->stagedJournalDrafts[$index]['form'] = $form;
        $this->persistStagedJournalDrafts();
        $this->publishStagedJournalPreview();
    }

    /** Only the authenticated Live Preview can resolve this browser-session snapshot. */
    private function publishStagedJournalPreview(): void
    {
        $index = $this->stagedDraftIndex($this->selectedStagedJournalDraftKey);
        if ($index === null) {
            session()->forget('journal.stage.preview.'.$this->getId());

            return;
        }
        session()->put('journal.stage.preview.'.$this->getId(), [
            'section_id' => $this->sectionId,
            'template' => $this->template,
            'form' => $this->stagedJournalDrafts[$index]['form'],
        ]);
        if ($this->journalStagePanel === 'staged') {
            $this->refreshJournalPreview();
        }
    }

    public function discardStagedJournalDraft(): void
    {
        $index = $this->stagedDraftIndex($this->selectedStagedJournalDraftKey);
        if ($index === null) {
            return;
        }

        array_splice($this->stagedJournalDrafts, $index, 1);
        $this->selectedStagedJournalDraftKey = $this->stagedJournalDrafts === []
            ? null : $this->stagedJournalDrafts[min($index, count($this->stagedJournalDrafts) - 1)]['key'];
        if ($this->selectedStagedJournalDraftKey === null) {
            $this->journalStagePanel = 'new';
        } else {
            $this->fillSelectedStagedJournalDraft();
        }
        $this->persistStagedJournalDrafts();
        $this->publishStagedJournalPreview();
        $this->refreshJournalPreview();
    }

    public function discardAllStagedJournalDrafts(): void
    {
        if ($this->stagedJournalDrafts === []) {
            return;
        }

        $this->stagedJournalDrafts = [];
        $this->selectedStagedJournalDraftKey = null;
        $this->journalStagePanel = 'new';
        $this->persistStagedJournalDrafts();
        $this->publishStagedJournalPreview();
        $this->refreshJournalPreview();
    }

    public function submitStagedJournalDraft(): void
    {
        $this->submitStagedJournalDrafts(false);
    }

    public function submitAllStagedJournalDrafts(): void
    {
        $this->submitStagedJournalDrafts(true);
    }

    private function submitStagedJournalDrafts(bool $all): void
    {
        $this->rememberStagedJournalDraft();
        $index = $this->stagedDraftIndex($this->selectedStagedJournalDraftKey);
        if ($index === null) {
            return;
        }
        $selected = $all ? $this->stagedJournalDrafts : [$this->stagedJournalDrafts[$index]];

        try {
            // A failed member rolls back the entire Submit All batch.
            DB::transaction(function () use ($selected): void {
                foreach ($selected as $row) {
                    $data = [...$row['form'], 'site_section_id' => $this->sectionId];
                    if ($this->journalTemplate() === JournalTemplate::Blog) {
                        $service = app(BlogEditorialService::class);
                        $post = $service->createDraft($data);
                        $mode = (string) ($data['stage_publication_mode'] ?? 'publish');
                        match ($mode) {
                            'draft' => null,
                            'schedule' => $service->schedule($post, $data['stage_scheduled_at'] ?? null),
                            'publish' => $service->publish($post),
                            default => throw ValidationException::withMessages([
                                'stage_publication_mode' => 'Choose a supported publication action.',
                            ]),
                        };
                    } else {
                        $service = app(ExhibitionEditorialService::class);
                        $entry = $service->createDraft($data);
                        $service->publish($entry);
                    }
                }
            });
        } catch (ValidationException $exception) {
            $this->notifyValidationFailure($all ? 'Staged entries were not submitted' : 'Staged entry was not submitted', $exception);

            return;
        }

        $submittedKeys = array_column($selected, 'key');
        $this->stagedJournalDrafts = array_values(array_filter($this->stagedJournalDrafts,
            static fn (array $row): bool => ! in_array($row['key'], $submittedKeys, true)));
        $this->selectedStagedJournalDraftKey = $this->stagedJournalDrafts[0]['key'] ?? null;
        $this->journalStagePanel = $this->selectedStagedJournalDraftKey === null ? 'new' : 'staged';
        if ($this->selectedStagedJournalDraftKey !== null) {
            $this->fillSelectedStagedJournalDraft();
        }
        $this->persistStagedJournalDrafts();
        $this->publishStagedJournalPreview();
        $this->reloadEntries();
        $this->refreshJournalPreview();
        app(AdminNotifier::class)->notification(
            title: count($selected) === 1 ? 'Journal entry submitted' : count($selected).' Journal entries submitted',
            status: 'success',
        );
    }
}
