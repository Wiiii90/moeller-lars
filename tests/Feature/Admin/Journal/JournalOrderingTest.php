<?php

use App\Domain\Content\JournalEntryOrderService;
use App\Models\AdminActivityOrderingEvent;
use App\Models\AuditEvent;
use App\Models\BlogPost;
use App\Models\SiteSection;
use App\Models\User;

it('reserves position one for a new Journal entry and keeps existing order contiguous', function (): void {
    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => SiteSection::JOURNAL_TEMPLATE_BLOG,
        'title' => 'Blog',
        'navigation_label' => 'Blog',
        'slug' => 'blog-ordering-test',
        'state' => 'hidden',
        'position' => 100,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $first = BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'slug' => 'first-existing-post',
        'title' => 'First existing post',
        'body' => null,
        'state' => 'draft',
        'position' => 4,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]);
    $second = BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'slug' => 'second-existing-post',
        'title' => 'Second existing post',
        'body' => null,
        'state' => 'draft',
        'position' => 9,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]);

    $position = app(JournalEntryOrderService::class)->nextPosition(new BlogPost, (int) $section->getKey());

    expect($position)->toBe(1)
        ->and(BlogPost::query()->where('site_section_id', $section->getKey())->orderBy('position')->pluck('id')->all())
        ->toBe([(int) $first->getKey(), (int) $second->getKey()])
        ->and(BlogPost::query()->where('site_section_id', $section->getKey())->orderBy('position')->pluck('position')->all())
        ->toBe([2, 3]);
});


it('records one logical Activity event when a Journal reorder rewrites several positions', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $section = SiteSection::query()->create([
        'type' => SiteSection::TYPE_JOURNAL,
        'template' => SiteSection::JOURNAL_TEMPLATE_BLOG,
        'title' => 'Activity Blog',
        'navigation_label' => 'Activity Blog',
        'slug' => 'activity-blog-ordering-test',
        'state' => 'hidden',
        'position' => 100,
        'parent_id' => null,
        'artwork_category_id' => null,
    ]);

    $posts = collect(range(1, 3))->map(fn (int $position): BlogPost => BlogPost::query()->create([
        'site_section_id' => $section->getKey(),
        'slug' => 'activity-order-post-'.$position,
        'title' => 'Activity order post '.$position,
        'body' => null,
        'state' => 'draft',
        'position' => $position,
        'excerpt' => null,
        'published_at' => null,
        'scheduled_at' => null,
    ]));

    expect(app(JournalEntryOrderService::class)->moveToPosition($posts[0], 2))->toBeTrue();

    expect(BlogPost::query()
        ->where('site_section_id', $section->getKey())
        ->orderBy('position')
        ->pluck('id')
        ->map(fn (mixed $id): int => (int) $id)
        ->all())->toBe([
            (int) $posts[1]->getKey(),
            (int) $posts[2]->getKey(),
            (int) $posts[0]->getKey(),
        ]);

    $events = AuditEvent::query()->where('action', 'blog_post.reordered')->get();
    expect($events)->toHaveCount(1)
        ->and((int) $events->first()->entity_id)->toBe((int) $posts[0]->getKey())
        ->and(AdminActivityOrderingEvent::query()->whereKey($events->first()->getKey())->exists())->toBeTrue();
});

function journalOrderingFixture(string $template): array
{
    auth('web')->login(User::factory()->admin()->create());
    \Filament\Facades\Filament::setCurrentPanel('admin'); \Filament\Facades\Filament::bootCurrentPanel();
    $section = app(\App\Domain\Content\SiteSectionEditorialService::class)->createJournal('Order Journal', 'order-journal', $template);
    $model = $template === 'blog' ? new BlogPost : new \App\Models\Exhibition;
    $entries = collect(range(1, 3))->map(fn (int $position) => $model->newQuery()->create([
        'site_section_id' => $section->id, 'slug' => 'entry-'.$position, 'title' => 'Entry '.$position,
        'position' => $position, 'state' => 'draft',
    ]));
    app(\App\Domain\Admin\AdminActivityNotificationContext::class)->discard();
    return [$section, $entries];
}

it('sorts Journal entries with one audit and feedback and keeps repeated sorting silent', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $before = AuditEvent::query()->count();
    $method = $template === 'blog' ? 'sortPost' : 'sortExhibition';
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->call($method, $entries[0]->id, 2)->assertHasNoErrors();
    expect($entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->pluck('id')->all())
        ->toBe([$entries[1]->id, $entries[2]->id, $entries[0]->id])
        ->and(AuditEvent::query()->count())->toBe($before + 1)->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
    $editor->call($method, $entries[0]->id, 2)->assertNotDispatched('admin-header-notification');
    expect(AuditEvent::query()->count())->toBe($before + 1)->and(\App\Models\AdminNotification::query()->count())->toBe(1);
})->with(['blog', 'exhibitions']);

it('deletes a Journal entry and compacts remaining positions without losing other entries', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $before = AuditEvent::query()->count();
    $type = $template === 'blog' ? 'post' : 'exhibition';
    \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->mountAction($template === 'blog' ? 'deletePost' : 'deleteExhibition', [$type => $entries[1]->id])
        ->callMountedAction()->assertHasNoErrors()->assertDontSee('Entry 2');
    $remaining = $entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->get();
    expect($remaining->pluck('id')->all())->toBe([$entries[0]->id, $entries[2]->id])
        ->and($remaining->pluck('position')->all())->toBe([1, 2])
        ->and(AuditEvent::query()->count())->toBe($before + 1)->and(\App\Models\AdminNotification::query()->count())->toBe(1);
})->with(['blog', 'exhibitions']);

it('rolls back every Journal position when reorder auditing fails', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $before = AuditEvent::query()->count();
    $audit = Mockery::mock(app(\App\Domain\Admin\AdminAuditService::class));
    $audit->shouldReceive('recordOrdering')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(JournalEntryOrderService::class);
    expect(fn () => \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->call($template === 'blog' ? 'sortPost' : 'sortExhibition', $entries[0]->id, 2))->toThrow(RuntimeException::class, 'Audit unavailable');
    $current = $entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->get();
    expect($current->pluck('id')->all())->toBe($entries->pluck('id')->all())->and($current->pluck('position')->all())->toBe([1, 2, 3])
        ->and(AuditEvent::query()->count())->toBe($before)->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['blog', 'exhibitions']);

it('refuses to reorder entries from another Journal', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $other = app(\App\Domain\Content\SiteSectionEditorialService::class)->createJournal('Other Journal', 'other-journal', $template);
    $before = AuditEvent::query()->count();
    expect(fn () => \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $other->id])
        ->call($template === 'blog' ? 'sortPost' : 'sortExhibition', $entries[0]->id, 2))
        ->toThrow(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
    expect($entries[0]->fresh()->position)->toBe(1)->and(AuditEvent::query()->count())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['blog', 'exhibitions']);

it('keeps published Journal entries when deletion is requested', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $ready = $template === 'blog' ? ['body' => 'Published post body'] : ['starts_on' => '2026-01-01', 'ends_on' => '2026-12-31'];
    $entries[1]->forceFill([...$ready, 'state' => 'published', 'published_at' => now()])->save();
    $before = AuditEvent::query()->count();
    $type = $template === 'blog' ? 'post' : 'exhibition';
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->mountAction($template === 'blog' ? 'deletePost' : 'deleteExhibition', [$type => $entries[1]->id])->callMountedAction();
    expect($entries[1]->fresh()->state)->toBe('published')->and(AuditEvent::query()->count())->toBe($before)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->where('params.notification.status', 'success')->count())->toBe(0);
})->with(['blog', 'exhibitions']);

it('blocks Journal mutations after administrative access is lost', function (bool $guest): void {
    [$section, $entries] = journalOrderingFixture('blog');
    $actor = auth()->user();
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])->mountAction('journalSettings');
    $before = AuditEvent::query()->count();
    if ($guest) { \Illuminate\Support\Facades\Auth::guard('web')->logout(); }
    else { $actor->forceFill(['is_admin' => false])->save(); }
    $editor->set('mountedActions.0.data.title', 'Unauthorized')->assertForbidden();
    expect($section->fresh()->title)->toBe('Order Journal')->and(AuditEvent::query()->count())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['guest' => true, 'revoked admin' => false]);

it('moves a selected Journal group with complete audits and one notification', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $before = AuditEvent::query()->count();
    $selection = $template === 'blog' ? 'selectedPostIds' : 'selectedExhibitionIds';
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->set($selection, [$entries[0]->id, $entries[1]->id])->call('moveSelectedEntries', 'down')->assertHasNoErrors();
    expect($entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->pluck('id')->all())
        ->toBe([$entries[2]->id, $entries[0]->id, $entries[1]->id])
        ->and(AuditEvent::query()->count())->toBe($before + 2)->and(\App\Models\AdminNotification::query()->count())->toBe(1)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->count())->toBe(1);
})->with(['blog', 'exhibitions']);

it('rolls back the complete selected Journal move if a later audit fails', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $before = AuditEvent::query()->count();
    $realAudit = app(\App\Domain\Admin\AdminAuditService::class); $calls = 0;
    $audit = Mockery::mock($realAudit);
    $audit->shouldReceive('recordOrdering')->twice()->andReturnUsing(function (...$parameters) use (&$calls, $realAudit) {
        if (++$calls === 2) { throw new RuntimeException('Audit unavailable'); }
        return $realAudit->recordOrdering(...$parameters);
    });
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    app()->forgetInstance(JournalEntryOrderService::class);
    $selection = $template === 'blog' ? 'selectedPostIds' : 'selectedExhibitionIds';
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->set($selection, [$entries[0]->id, $entries[1]->id])->call('moveSelectedEntries', 'down');
    expect($entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->pluck('id')->all())
        ->toBe($entries->pluck('id')->all())->and(AuditEvent::query()->count())->toBe($before)
        ->and(collect($editor->effects['dispatches'] ?? [])->where('name', 'admin-header-notification')->where('params.notification.status', 'success')->count())->toBe(0);
})->with(['blog', 'exhibitions']);
it('restores the deleted Journal entry and every compacted position when its audit fails', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $before = AuditEvent::query()->count();
    $audit = Mockery::mock(app(\App\Domain\Admin\AdminAuditService::class));
    $audit->shouldReceive('record')->once()->andThrow(new RuntimeException('Audit unavailable'));
    app()->instance(\App\Domain\Admin\AdminAuditService::class, $audit);
    $type = $template === 'blog' ? 'post' : 'exhibition';
    expect(fn () => \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->mountAction($template === 'blog' ? 'deletePost' : 'deleteExhibition', [$type => $entries[1]->id])->callMountedAction())
        ->toThrow(RuntimeException::class, 'Audit unavailable');
    $current = $entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->get();
    expect($current->pluck('id')->all())->toBe($entries->pluck('id')->all())->and($current->pluck('position')->all())->toBe([1, 2, 3])
        ->and(AuditEvent::query()->count())->toBe($before)->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['blog', 'exhibitions']);

it('rejects invalid native Journal positions and malformed entry identifiers', function (string $template, string $invalid): void {
    [$section, $entries] = journalOrderingFixture($template);
    $method = $template === 'blog' ? 'sortPost' : 'sortExhibition';
    $before = $entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->get(['id', 'position'])->toArray();
    $audits = AuditEvent::query()->count();
    $id = $invalid === 'identifier' ? $entries[1]->id.'invalid' : $entries[1]->id;
    $position = $invalid === 'negative' ? -1 : ($invalid === 'overflow' ? 3 : 0);
    \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id])
        ->call($method, $id, $position)->assertNotDispatched('admin-header-notification');
    expect($entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->get(['id', 'position'])->toArray())->toBe($before)
        ->and(AuditEvent::query()->count())->toBe($audits)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['blog', 'exhibitions'])->with(['negative', 'overflow', 'identifier']);

it('leaves explicit Journal boundary moves silent and protects a mounted sort after access is revoked', function (string $template): void {
    [$section, $entries] = journalOrderingFixture($template);
    $sort = $template === 'blog' ? 'sortPost' : 'sortExhibition';
    $move = $template === 'blog' ? 'movePost' : 'moveExhibition';
    $before = AuditEvent::query()->count();
    $editor = \Livewire\Livewire::test(\App\Filament\Pages\JournalWorkspace::class, ['section' => $section->id]);
    $editor->call($move, $entries[0]->id, 'up')->assertNotDispatched('admin-header-notification');
    $editor->call($move, $entries[2]->id, 'down')->assertNotDispatched('admin-header-notification');
    auth('web')->user()->forceFill(['is_admin' => false])->save();
    $editor->call($sort, $entries[0]->id, 2)->assertForbidden();
    expect($entries[0]->newQuery()->where('site_section_id', $section->id)->orderBy('position')->pluck('id')->all())->toBe($entries->pluck('id')->all())
        ->and(AuditEvent::query()->count())->toBe($before)
        ->and(\App\Models\AdminNotification::query()->count())->toBe(0);
})->with(['blog', 'exhibitions']);
