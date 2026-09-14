<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Content\HomeTemplate;
use App\Domain\Content\SiteNodeType;
use App\Filament\Support\HomeSettingsDialog;
use App\Models\SiteSection;
use Filament\Notifications\Notification;
use Illuminate\Validation\ValidationException;

trait ManagesHomePagePresentation
{
    public function changeHomeTemplate(int $sectionId, string $template): void
    {
        try {
            /** @var SiteSection $section */
            $section = SiteSection::query()->findOrFail($sectionId);
            if ($section->nodeType() !== SiteNodeType::Home) {
                throw ValidationException::withMessages(['template' => 'Only Home has a Home template.']);
            }

            $homeTemplate = HomeTemplate::tryFrom($template);
            if (! $homeTemplate instanceof HomeTemplate || ! $homeTemplate->isContentTemplate()) {
                throw ValidationException::withMessages(['template' => 'Choose a Home content template.']);
            }

            app(HomeSettingsDialog::class)->changeTemplate($homeTemplate);
            Notification::make()->title('Home template updated')->success()->send();
        } catch (ValidationException $exception) {
            $this->validationNotification('Home template unchanged', $exception);
        }

        $this->loadSections();
    }
}
