<?php

namespace App\Filament\Pages\Concerns;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Content\HomePresentationResolver;
use App\Domain\Content\HomeRoutingSettingsService;
use App\Domain\Content\HomeTemplate;
use App\Domain\Content\SiteSectionType;
use App\Filament\Support\HomeSettingsDialog;
use App\Models\SiteSection;
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
                app(AdminNotifier::class)->notification(
                    title: 'Home template updated',
                    status: 'success',
                );
            }
        } catch (ValidationException $exception) {
            $this->validationNotification('Home template unchanged', $exception);
        }

        $this->loadSections();
    }

    public function toggleSkipHome(): void
    {
        $resolver = app(HomePresentationResolver::class);
        $routing = app(HomeRoutingSettingsService::class);
        $settings = $resolver->settings();
        $enable = ! $routing->enabled($settings);

        try {
            // The automatic destination is the next eligible published page.
            // Never activate a redirect that has nowhere valid to go.
            if ($enable && ! $resolver->skipTarget() instanceof SiteSection) {
                throw ValidationException::withMessages([
                    'skip_home' => 'Add or publish an eligible top-level page before enabling Skip Home.',
                ]);
            }

            if ($routing->update($settings, $enable, null)) {
                app(AdminNotifier::class)->notification(
                    title: $enable ? 'Skip Home enabled' : 'Skip Home disabled',
                    status: 'success',
                );
            }
        } catch (ValidationException $exception) {
            $this->validationNotification('Skip Home unchanged', $exception);
        }

        $this->loadSections();
    }

    public function changeSkipHomeTarget(string $target): void
    {
        $routing = app(HomeRoutingSettingsService::class);
        $settings = app(HomePresentationResolver::class)->settings();

        try {
            if (! $routing->enabled($settings)) {
                throw ValidationException::withMessages([
                    'skip_target_section_id' => 'Enable Skip Home before choosing a destination.',
                ]);
            }

            $targetId = null;
            if ($target !== '') {
                $targetId = filter_var($target, FILTER_VALIDATE_INT);
                if ($targetId === false || $targetId <= 0) {
                    throw ValidationException::withMessages([
                        'skip_target_section_id' => 'Choose a valid destination page.',
                    ]);
                }
            }

            // The same domain service validates every explicit target and
            // persists the change in the audited Home routing configuration.
            if ($routing->update($settings, true, $targetId)) {
                app(AdminNotifier::class)->notification(
                    title: 'Skip Home destination updated',
                    status: 'success',
                );
            }
        } catch (ValidationException $exception) {
            $this->validationNotification('Skip Home destination unchanged', $exception);
        }

        $this->loadSections();
    }
}
