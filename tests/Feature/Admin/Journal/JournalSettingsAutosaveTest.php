<?php

use App\Domain\Content\JournalSettingsService;
use App\Domain\Content\ExhibitionEditorialService;
use App\Domain\Content\JournalTemplate;
use App\Domain\Content\SiteSectionPathPolicy;
use App\Domain\Content\SiteSectionEditorialService;
use App\Filament\Pages\JournalWorkspace;
use App\Models\AdminNotification;
use App\Models\BlogPost;
use App\Models\Exhibition;
use App\Models\JournalSetting;
use App\Models\Redirect;
use App\Models\User;
use App\Models\AuditEvent;
use Filament\Facades\Filament;
use Livewire\Livewire;

it('saves a Journal template change with one audit and notification, and stays silent on no-op', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Editorial Journal', 'editorial-journal', JournalTemplate::Blog->value,
    );

    $beforeAudits = AuditEvent::query()->count();
    $beforeNotifications = AdminNotification::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->assertMountedActionModalSee('Template');

    $editor->set('mountedActions.0.data.template', JournalTemplate::Exhibitions->value)
        ->call('callMountedAction')
        ->assertHasNoFormErrors()
        ->assertDispatched('admin-header-notification');

    expect($section->fresh()->getAttribute('template'))->toBe(JournalTemplate::Exhibitions->value)
        ->and(AuditEvent::query()->count())->toBe($beforeAudits + 1)
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->callMountedAction()
        ->assertHasNoFormErrors()
        ->assertNotDispatched('admin-header-notification');

    expect(AuditEvent::query()->count())->toBe($beforeAudits + 1)
        ->and(AdminNotification::query()->count())->toBe($beforeNotifications + 1);
});

function journalSettingsFixture(string $template = 'blog'): \App\Models\SiteSection
{
    $thisActor = User::factory()->admin()->create();
    auth('web')->login($thisActor);
    Filament::setCurrentPanel('admin'); Filament::bootCurrentPanel();
    return app(SiteSectionEditorialService::class)->createJournal('Journal before', 'journal-before', $template);
}

it('refreshes combined Journal settings immediately with complete audit and single feedback', function (string $template): void {
    $section = journalSettingsFixture($template);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('journalSettings')
        ->fillForm(['title' => 'Journal after', 'navigation_label' => 'Journal after', 'slug' => 'journal-after'])
        ->callMountedAction()->assertHasNoFormErrors()->assertSee('Journal after');
    expect($section->fresh()->title)->toBe('Journal after')->and($section->fresh()->slug)->toBe('journal-after')
        ->and(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
})->with(['blog', 'exhibitions']);

it('keeps duplicate Journal slugs on the mounted field and permits correction after rollback', function (): void {
    $section = journalSettingsFixture();
    app(SiteSectionEditorialService::class)->createCustomPage('Reserved', 'journal-reserved');
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('journalSettings')
        ->fillForm(['title' => 'Rejected name', 'slug' => 'journal-reserved'])->callMountedAction()
        ->assertHasFormErrors(['slug'])->assertNotDispatched('admin-header-notification');
    expect($section->fresh()->title)->toBe('Journal before')->and(AuditEvent::query()->count())->toBe($before)
        ->and(AdminNotification::query()->count())->toBe(0);
    $editor->set('mountedActions.0.data.slug', 'journal-corrected')->callMountedAction()->assertHasNoFormErrors();
    expect($section->fresh()->title)->toBe('Rejected name')->and($section->fresh()->slug)->toBe('journal-corrected');
});

it('rejects stale Journal settings instead of replacing a concurrent identity change', function (): void {
    $section = journalSettingsFixture();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('journalSettings');
    app(\App\Domain\Content\JournalSettingsService::class)->update($section, [
        'template' => 'blog', 'title' => 'Concurrent journal', 'navigation_label' => 'Concurrent journal', 'slug' => 'concurrent-journal',
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor->set('mountedActions.0.data.title', 'Stale name')->callMountedAction()
        ->assertHasFormErrors(['title'])->assertNotDispatched('admin-header-notification');
    expect($section->fresh()->title)->toBe('Concurrent journal')->and($section->fresh()->slug)->toBe('concurrent-journal')
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('switches populated Journal templates while retaining entries and notifying once', function (string $template): void {
    $section = journalSettingsFixture($template);
    $model = $template === 'blog' ? new \App\Models\BlogPost : new \App\Models\Exhibition;
    $entry = $model->newQuery()->create(['site_section_id' => $section->id, 'title' => 'Existing entry', 'slug' => 'existing-entry', 'position' => 1, 'state' => 'draft']);
    $before = AuditEvent::query()->count();
    $entryBefore = $entry->fresh()->attributesToArray();
    $target = $template === 'blog' ? 'exhibitions' : 'blog';
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('journalSettings')
        ->fillForm(['template' => $target])
        ->callMountedAction()->assertHasNoFormErrors()->assertDispatched('admin-header-notification');
    expect($section->fresh()->template)->toBe($target)->and($section->fresh()->title)->toBe('Journal before')
        ->and($entry->fresh()->attributesToArray())->toBe($entryBefore)
        ->and(AuditEvent::query()->count())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe(1);
})->with(['blog', 'exhibitions']);
it('rolls back combined Journal identity and template changes if the later audit fails', function (): void {
    $section = journalSettingsFixture();
    $before = AuditEvent::query()->count();
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class); $calls = 0;
    $audit = Mockery::mock($realAudit);
    $audit->shouldReceive('record')->twice()->andReturnUsing(function (...$parameters) use (&$calls, $realAudit) {
        if (++$calls === 2) { throw new RuntimeException('Audit unavailable'); }
        return $realAudit->record(...$parameters);
    });
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    expect(fn () => Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('journalSettings')
        ->fillForm(['template' => 'exhibitions', 'title' => 'Rejected identity'])->callMountedAction())
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    expect($section->fresh()->template)->toBe('blog')->and($section->fresh()->title)->toBe('Journal before')
        ->and(AuditEvent::query()->count())->toBe($before)->and(AdminNotification::query()->count())->toBe(0);
});

it('persists Blog edits in the actual mounted editor refreshes its row and repeats silently', function (): void {
    $section = journalSettingsFixture();
    $post = \App\Models\BlogPost::query()->create(['site_section_id' => $section->id, 'title' => 'Original Blog title', 'slug' => 'blog-editor', 'body' => 'Original body', 'position' => 1, 'state' => 'draft']);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('editPost', ['post' => $post->id])
        ->set('mountedActions.0.data.title', 'Current Blog title')->callMountedAction()->assertHasNoFormErrors()->assertSee('Current Blog title');
    expect($post->fresh()->title)->toBe('Current Blog title')->and(AuditEvent::query()->count())->toBe($before + 1)
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->callMountedAction()->assertHasNoFormErrors()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 1)->and(AdminNotification::query()->count())->toBe(1);
});

it('keeps concurrent Blog content when a stale mounted editor attempts to save', function (): void {
    $section = journalSettingsFixture();
    $post = \App\Models\BlogPost::query()->create(['site_section_id' => $section->id, 'title' => 'Original Blog title', 'slug' => 'stale-blog-editor', 'body' => 'Original body', 'position' => 1, 'state' => 'draft']);
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->id])->mountAction('editPost', ['post' => $post->id]);
    app(\App\Domain\Content\BlogEditorialService::class)->update($post, [
        'site_section_id' => $section->id, 'title' => 'Concurrent Blog title', 'slug' => $post->slug,
        'body' => 'Concurrent body', 'state' => $post->state, 'position' => $post->position,
    ]);
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor->set('mountedActions.0.data.title', 'Stale Blog title')->callMountedAction()->assertHasFormErrors(['title']);
    expect($post->fresh()->title)->toBe('Concurrent Blog title')->and($post->fresh()->body)->toBe('Concurrent body')
        ->and(AuditEvent::query()->count())->toBe($before)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->where('params.notification.status', 'success')->count())->toBe(0);
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
        ->assertActionDataSet(['navigation_label' => 'Updated feedback title'])
        ->assertSet('mountedActions.0.name', 'journalSettings')
        ->assertDispatched('admin-header-notification');

    expect($section->fresh()->getAttribute('title'))->toBe('Updated feedback title')
        ->and($section->fresh()->getAttribute('navigation_label'))->toBe('Updated feedback title')
        ->and(AdminNotification::query()->count())->toBe($notifications + 1);

    $component->call('callMountedAction')->assertHasNoFormErrors();
    expect(AdminNotification::query()->count())->toBe($notifications + 1);
});

it('preserves an authored navigation label across consecutive Journal autosaves and reopening', function (): void {
    $section = journalSettingsFixture();
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    $before = AuditEvent::query()->count();
    $editor = Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->set('mountedActions.0.data.navigation_label', 'Curated menu')
        ->call('callMountedAction')->assertHasNoFormErrors();
    expect($section->fresh()->navigation_label)->toBe('Curated menu')
        ->and(AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->set('mountedActions.0.data.title', 'Updated Journal heading')
        ->call('callMountedAction')->assertHasNoFormErrors()
        ->assertActionDataSet(['title' => 'Updated Journal heading', 'navigation_label' => 'Curated menu']);
    expect($section->fresh()->title)->toBe('Updated Journal heading')
        ->and($section->fresh()->navigation_label)->toBe('Curated menu')
        ->and(AuditEvent::query()->count())->toBe($before + 2)
        ->and(AdminNotification::query()->count())->toBe(2)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->call('callMountedAction')->assertHasNoFormErrors()->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 2)
        ->and(AdminNotification::query()->count())->toBe(2);
    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('journalSettings')
        ->assertActionDataSet(['title' => 'Updated Journal heading', 'navigation_label' => 'Curated menu']);
});

it('mounts Blog Create and Edit with the centralized editorial schema and header Actions', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Dialog Blog', 'dialog-blog', JournalTemplate::Blog->value,
    );
    $post = BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'title' => 'Existing article',
        'slug' => 'existing-article',
        'body' => 'Existing rich text',
        'state' => 'draft',
        'position' => 0,
    ]);

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('addPost')
        ->assertMountedActionModalSee('Add post');

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editPost', ['post' => $post->getKey()])
        ->assertMountedActionModalSee('Edit post');
});

it('mounts Exhibition Create and Edit with the centralized editorial schema and header Actions', function (): void {
    $this->actingAs(User::factory()->admin()->create(), 'web');
    Filament::setCurrentPanel('admin');
    Filament::bootCurrentPanel();

    $section = app(SiteSectionEditorialService::class)->createJournal(
        'Dialog Exhibitions', 'dialog-exhibitions', JournalTemplate::Exhibitions->value,
    );
    $entry = app(ExhibitionEditorialService::class)->createDraft([
        'site_section_id' => $section->getKey(),
        'title' => 'Existing exhibition',
        'slug' => 'existing-exhibition',
    ]);

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('addExhibition')
        ->assertMountedActionModalSee('Add exhibition');

    Livewire::test(JournalWorkspace::class, ['section' => $section->getKey()])
        ->mountAction('editExhibition', ['exhibition' => $entry->getKey()])
        ->assertMountedActionModalSee('Edit exhibition');
});
