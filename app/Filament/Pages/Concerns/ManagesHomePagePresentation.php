<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\HomeTemplate;
use App\Filament\Support\HomeSettingsDialog;
use App\Models\SiteSection;
use Illuminate\Validation\ValidationException;

trait ManagesHomePagePresentation
{
    public function changeHomeTemplate(int $sectionId, string $template): string
    {
        try {
            /** @var SiteSection $section */
            $section = SiteSection::query()->findOrFail($sectionId);
            if (! $section->isHome()) {
                throw ValidationException::withMessages(['template' => 'Only Home has a Home template.']);
            }

            $homeTemplate = HomeTemplate::tryFrom($template);
            if (! $homeTemplate instanceof HomeTemplate) {
                throw ValidationException::withMessages(['template' => 'Choose a Home content template.']);
            }

            if (app(HomeSettingsDialog::class)->changeTemplate($homeTemplate)) {
                app(AdminNotifier::class)->notification(title: 'Home template updated', status: 'success');
            }
        } catch (ValidationException $exception) {
            $this->validationNotification('Home template unchanged', $exception);
        }

        $this->loadSections();

        return app(HomeSettingsDialog::class)->tableState()['template'];
    }
}
