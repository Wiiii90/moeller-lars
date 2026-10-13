<?php

use App\Domain\Content\JournalSettingsService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\AdminNotification;
use App\Models\BlogPost;
use App\Models\JournalSetting;
use App\Models\Redirect;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('switches Journal template through native Edit autosave without an extra confirmation field or heading', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Editorial Journal', 'editorial-journal', JournalTemplate::Blog->value,
    );

    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->assertMountedActionModalSee('Template')
        ->assertDontSee('Confirm template switch')
        ->assertDontSee('Existing entries are kept intact.');

    $editor->set('mountedActions.0.data.template', JournalTemplate::Exhibitions->value)
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect($section->fresh()->getAttribute('template'))->toBe(JournalTemplate::Exhibitions->value);
});

it('keeps existing posts on both template switches from Journal Settings and Pages', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $sections = app(SiteSectionEditorialService::class);
    $section = $sections->createJournal('Preserved Journal', 'preserved-journal', JournalTemplate::Blog->value);
    $post = BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'title' => 'Retained post',
        'slug' => 'retained-post',
        'body' => 'Content must survive a template switch.',
        'state' => 'draft',
        'position' => 0,
    ]);

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->set('mountedActions.0.data.template', JournalTemplate::Exhibitions->value)
        ->call('callMountedAction')
        ->assertHasNoFormErrors();

    expect($section->fresh()->getAttribute('template'))->toBe(JournalTemplate::Exhibitions->value)
        ->and($post->fresh()->getAttribute('body'))->toBe('Content must survive a template switch.');

    // Pages Edit calls this same domain action: returning to Blog must not
    // require the owner to delete posts or re-create their data.
    $sections->updateJournalTemplate($section->fresh(), JournalTemplate::Blog->value);

    expect($section->fresh()->getAttribute('template'))->toBe(JournalTemplate::Blog->value)
        ->and(BlogPost::query()->whereKey($post->getKey())->exists())->toBeTrue();
});

it('uses canonical Journal URL redirects and synchronizes only uncustomized listing titles', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Initial Journal', 'journal-start', JournalTemplate::Blog->value,
    );
    $editor = app(JournalSettingsService::class);
    $editor->update($section, [
        'template' => JournalTemplate::Blog->value,
        'title' => 'Renamed Journal',
        'navigation_label' => '',
        'slug' => 'journal-renamed',
    ]);

    expect($section->fresh()->getAttribute('navigation_label'))->toBe('Renamed Journal')
        ->and(JournalSetting::forSection($section)->getAttribute('listing_title'))->toBe('Renamed Journal')
        ->and(Redirect::query()->where('source_path', '/journal-start')
            ->where('target_path', '/journal-renamed')
            ->where('reason', SiteSectionPathPolicy::CUSTOM_PAGE_SLUG_REDIRECT_REASON)
            ->where('status_code', 301)->where('enabled', true)->exists())->toBeTrue();

    JournalSetting::forSection($section)->update(['listing_title' => 'Curated Journal heading']);
    $editor->update($section->fresh(), [
        'template' => JournalTemplate::Blog->value,
        'title' => 'Final Journal',
        'navigation_label' => 'Nav short',
        'slug' => 'journal-final',
    ]);

    expect($section->fresh()->getAttribute('navigation_label'))->toBe('Nav short')
        ->and(JournalSetting::forSection($section)->getAttribute('listing_title'))->toBe('Curated Journal heading')
        ->and(Redirect::query()->where('source_path', '/journal-start')
            ->where('target_path', '/journal-final')->exists())->toBeTrue()
        ->and(Redirect::query()->where('source_path', '/journal-renamed')
            ->where('target_path', '/journal-final')->exists())->toBeTrue();
});

it('notifies on a real Journal Settings edit but not on an unchanged native autosave', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Journal feedback', 'journal-feedback', JournalTemplate::Blog->value,
    );
    $component = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings');
    $notifications = AdminNotification::query()->count();

    $component->call('callMountedAction')->assertHasNoFormErrors();
    expect(AdminNotification::query()->count())->toBe($notifications);

    $component->set('mountedActions.0.data.title', 'Updated feedback title')
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertSet('mountedActions.0.name', 'journalSettings')
        ->assertDispatched('admin-header-notification');

    expect($section->fresh()->getAttribute('title'))->toBe('Updated feedback title')
        ->and($section->fresh()->getAttribute('navigation_label'))->toBe('Updated feedback title')
        ->and(AdminNotification::query()->count())->toBe($notifications + 1);

    $component->call('callMountedAction')->assertHasNoFormErrors();
    expect(AdminNotification::query()->count())->toBe($notifications + 1);
});
