<?php

namespace App\Filament\Concerns;

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Content\SafeRichTextRenderer;
use App\Models\JournalSetting;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

trait HasJournalSettingsAction
{
    abstract protected function journalSectionId(): int;

    public function journalSettingsAction(): Action
    {
        return Action::make('journalSettings')
            ->label('Journal settings')
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->fillForm(function (): array {
                $settings = JournalSetting::forSection($this->journalSectionId());

                return [
                    'listing_title' => $settings->getAttribute('listing_title'),
                    'listing_intro' => $settings->getAttribute('listing_intro'),
                ];
            })
            ->schema([
                TextInput::make('listing_title')
                    ->label('Listing title')
                    ->maxLength(240)
                    ->nullable(),
                MarkdownEditor::make('listing_intro')
                    ->label('Introduction')
                    ->toolbarButtons([
                        ['bold', 'italic', 'link'],
                        ['bulletList', 'orderedList'],
                        ['undo', 'redo'],
                    ])
                    ->helperText('Formatting is limited to the Markdown supported by the public Journal renderer.')
                    ->maxLength(10000)
                    ->nullable()
                    ->columnSpanFull(),
            ])
            ->modalHeading('Journal settings')
            ->modalSubmitActionLabel('Save changes')
            ->action(function (array $data): void {
                if (! array_key_exists('listing_intro', $data)) {
                    throw ValidationException::withMessages(['listing_intro' => 'Journal settings form data is incomplete.']);
                }

                if (is_string($data['listing_intro']) && $data['listing_intro'] !== '') {
                    app(SafeRichTextRenderer::class)->assertValid($data['listing_intro']);
                }

                $settings = JournalSetting::forSection($this->journalSectionId());
                app(AdminSettingsService::class)->updateJournal($settings, $data);

                Notification::make()->title('Journal settings saved')->success()->send();
            });
    }
}
