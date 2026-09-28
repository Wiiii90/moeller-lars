<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\AdminIcon;
use App\Filament\Support\Dialogs\AdminDialog;
use App\Filament\Support\Dialogs\AdminDialogSize;
use App\Filament\Support\HomeRoutingDialog;
use App\Filament\Support\HomeSettingsDialog;
use App\Models\SiteSection;
use Filament\Actions\Action;
use Illuminate\Validation\ValidationException;

trait ManagesHomePagePresentation
{
    public function changeHomeTemplate(int $sectionId, string $template): void
    {
        try {
            /** @var SiteSection $section */
            $section = SiteSection::query()->findOrFail($sectionId);
            if ($section->nodeType() !== SiteSectionType::Home) {
                throw ValidationException::withMessages(['template' => 'Only Home has a Home template.']);
            }

            $homeTemplate = HomeTemplate::tryFrom($template);
            if (! $homeTemplate instanceof HomeTemplate || ! $homeTemplate->isContentTemplate()) {
                throw ValidationException::withMessages(['template' => 'Choose a Home content template.']);
            }

            if (app(HomeSettingsDialog::class)->changeTemplate($homeTemplate)) {
                app(AdminNotifier::class)->transient()->title('Home template updated')->success()->send();
            }
        } catch (ValidationException $exception) {
            $this->validationNotification('Home template unchanged', $exception);
        }

        $this->loadSections();
    }

    public function skipHomeAction(): Action
    {
        $dialog = app(HomeRoutingDialog::class);

        return AdminDialog::command(
            Action::make('skipHome')
                ->label('Skip Home')
                ->icon(AdminIcon::SkipHome->value)
                ->modalHeading('Skip Home')
                ->modalDescription('Choose whether Home redirects and where it goes. Without an explicit target, the next eligible page in the current order is used.')
                ->fillForm(fn (): array => $dialog->fill())
                ->schema($dialog->schema())
                ->action(function (array $data) use ($dialog): void {
                    $changed = $dialog->save($data);
                    $this->loadSections();
                    if ($changed) {
                        app(AdminNotifier::class)->transient()->title('Home routing updated')->success()->send();
                    }
                }),
            'Save Skip Home',
            AdminDialogSize::Small,
        );
    }
}
