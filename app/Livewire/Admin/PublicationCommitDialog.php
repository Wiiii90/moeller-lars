<?php

namespace App\Livewire\Admin;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Publication\PublicationService;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Illuminate\Contracts\View\View;
use Livewire\Component;

final class PublicationCommitDialog extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    public function openCommit(): void
    {
        if (! app(PublicationService::class)->hasPendingChanges()) {
            return;
        }

        $this->mountAction('commitPublication');
    }

    public function commitPublicationAction(): Action
    {
        return Action::make('commitPublication')
            ->label('Commit')
            ->disabled(fn (): bool => ! app(PublicationService::class)->hasPendingChanges())
            ->modalHeading('Commit pending changes')
            ->modalSubmitActionLabel('Commit')
            ->schema([
                SchemaView::make('filament.schemas.components.publication-summary'),
                Textarea::make('message')
                    ->label('Message')
                    ->helperText('Optional')
                    ->rows(2)
                    ->maxLength(240),
            ])
            ->action(function (array $data): void {
                $actor = app(AdminAuditService::class)->requireActor();
                $checkpoint = app(PublicationService::class)->commit(
                    $actor,
                    is_string($data['message'] ?? null) ? $data['message'] : null,
                );

                if ($checkpoint === null) {
                    Notification::make()
                        ->title('No pending changes')
                        ->send();

                    return;
                }

                Notification::make()
                    ->title('Website committed')
                    ->success()
                    ->send();
            });
    }

    public function render(): View
    {
        return view('livewire.admin.publication-commit-dialog');
    }
}
