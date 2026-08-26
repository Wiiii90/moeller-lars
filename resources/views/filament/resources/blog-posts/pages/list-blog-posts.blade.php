<x-filament-panels::page>
    @php
        $visibleIds = collect($posts)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $selectedIds = collect($selectedPostIds)->map(static fn (mixed $id): int => (int) $id)->all();
        $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
        $dragEnabled = $this->canDragSort();
    @endphp

    <x-admin.workspace :title="$journalTitle" class="journal-workspace journal-workspace--blog">
        <x-admin.metrics :columns="6" aria-label="Blog overview">
            @foreach ($metrics as $metric)<x-admin.metric :label="$metric['label']" :value="$metric['value']" />@endforeach
        </x-admin.metrics>

        <x-admin.section class="journal-workspace__entries" aria-label="Blog entries">
            <div class="journal-workspace__surface">
                <div class="journal-workspace__controls is-blog" aria-label="Blog controls">
                    <label class="journal-workspace__field journal-workspace__search"><span>Search posts</span><input type="search" wire:model.live.debounce.300ms="search" placeholder="Title or excerpt" autocomplete="off"></label>
                    <label class="journal-workspace__field"><span>Status</span><select wire:model.live="statusFilter"><option value="any">Any</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option><option value="published">Published</option><option value="unpublished">Unpublished</option><option value="archived">Archived</option></select></label>
                    <div class="journal-workspace__control-group"><span class="journal-workspace__control-label">Filter</span><button class="admin-action" type="button" wire:click="resetFilters">Reset</button></div>
                    <div class="journal-workspace__control-group journal-workspace__journal-group"><span class="journal-workspace__control-label">Journal</span><div class="journal-workspace__journal-actions"><button class="admin-action" type="button" wire:click="mountAction('journalSettings')">Settings</button><button class="admin-action" type="button" wire:click="mountAction('addPost')">Add post</button>@if ($journalPublicUrl)<a class="admin-action" href="{{ $journalPublicUrl }}" target="_blank" rel="noopener">Preview</a>@else<button class="admin-action" type="button" disabled title="Publish this Journal in Pages before previewing it">Preview</button>@endif</div></div>
                    <div class="journal-workspace__control-group journal-workspace__selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false"><span class="journal-workspace__control-label">Selection</span><div class="journal-workspace__selection-anchor"><button class="admin-action journal-workspace__selection-trigger" type="button" x-on:click="open = ! open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedPostIds === [])>Selected posts <span class="journal-workspace__selection-count">{{ count($selectedPostIds) }}</span></button><div class="journal-workspace__selection-menu" role="menu" x-show="open" x-cloak x-on:click.outside="open = false"><button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('up')" x-on:click="open = false">Move selected up</button><button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('down')" x-on:click="open = false">Move selected down</button><button class="admin-action" type="button" role="menuitem" wire:click="publishSelectedPosts" x-on:click="open = false">Publish selected</button><button class="admin-action" type="button" role="menuitem" wire:click="unpublishSelectedPosts" x-on:click="open = false">Unpublish selected</button><button class="admin-action" type="button" role="menuitem" wire:click="archiveSelectedPosts" x-on:click="open = false">Archive selected</button><button class="admin-action" type="button" role="menuitem" wire:click="restoreSelectedPosts" x-on:click="open = false">Restore selected to draft</button><button class="admin-action is-danger" type="button" role="menuitem" wire:click="mountAction('deleteSelectedPosts')" x-on:click="open = false">Delete selected</button></div></div></div>
                </div>

                <x-admin.table class="journal-workspace__table-wrap">
                    <table class="journal-workspace__table journal-workspace__table--blog">
                        <thead><tr><th scope="col" class="journal-workspace__selection-head"><input type="checkbox" x-data="{}" wire:click.prevent="toggleVisibleSelection" x-effect="const visibleIds = @js($visibleIds); const selectedIds = $wire.selectedPostIds.map(Number); const count = visibleIds.filter((id) => selectedIds.includes(id)).length; $el.checked = visibleIds.length > 0 && count === visibleIds.length; $el.indeterminate = count > 0 && count < visibleIds.length; $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));" @disabled($visibleIds === []) aria-label="Toggle selection for visible posts"></th><th scope="col">Drag</th><th scope="col">Position</th><th scope="col" class="journal-workspace__thumb-head">Image</th><th scope="col">Post</th><th scope="col">Status</th><th scope="col">Publication</th><th scope="col">Actions</th></tr></thead>
                        <tbody @if ($dragEnabled) wire:sort="sortPost" @endif>
                            @foreach ($posts as $post)
                                <tr class="{{ in_array($post['id'], $selectedIds, true) ? 'is-selected' : '' }}" wire:key="blog-post-{{ $post['id'] }}" @if ($dragEnabled) wire:sort:item="{{ $post['id'] }}" @endif>
                                    <td class="journal-workspace__selection-cell"><input type="checkbox" wire:click="togglePostSelection({{ $post['id'] }})" @checked(in_array($post['id'], $selectedIds, true)) aria-label="Toggle selection for {{ $post['title'] }}"></td>
                                    <td><button class="admin-action journal-workspace__order-action" type="button" @if ($dragEnabled) wire:sort:handle title="Drag to reorder" @else disabled title="Drag reorder is available only with no search/filter and all entries on one page" @endif aria-label="Drag {{ $post['title'] }} to reorder">⠿</button></td>
                                    <td>{{ str_pad((string) $post['rank'], 2, '0', STR_PAD_LEFT) }}</td>
                                    <td class="journal-workspace__thumb">@if ($post['thumbnail_url'])<img src="{{ $post['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">@else<span>—</span>@endif</td>
                                    <td class="journal-workspace__identity"><strong>{{ $post['title'] }}</strong>@if ($post['excerpt'])<small>{{ $post['excerpt'] }}</small>@endif</td>
                                    <td><span class="journal-workspace__state is-{{ $post['state'] }}">{{ ucfirst($post['state']) }}</span></td>
                                    <td class="journal-workspace__publication">{{ $post['publication'] }}</td>
                                    <td class="journal-workspace__actions"><div class="admin-toolbar">
                                        @if ($post['public_url'])<a class="admin-action" href="{{ $post['public_url'] }}" target="_blank" rel="noopener">View</a>@else<button class="admin-action" type="button" disabled title="No protected/public preview is available for this state">View</button>@endif
                                        <button class="admin-action" type="button" wire:click="mountAction('editPost', { post: {{ $post['id'] }} })">Edit</button>
                                        @switch($post['state'])
                                            @case('published')<button class="admin-action" type="button" wire:click="unpublishPost({{ $post['id'] }})">Unpublish</button><button class="admin-action" type="button" wire:click="archivePost({{ $post['id'] }})">Archive</button>@break
                                            @case('scheduled')<button class="admin-action" type="button" wire:click="restorePostDraft({{ $post['id'] }})">Cancel schedule</button>@break
                                            @case('archived')<button class="admin-action" type="button" wire:click="restorePostDraft({{ $post['id'] }})">Restore</button>@break
                                            @default<button class="admin-action" type="button" wire:click="publishPost({{ $post['id'] }})">Publish</button><button class="admin-action" type="button" wire:click="mountAction('schedulePost', { post: {{ $post['id'] }} })">Schedule</button><button class="admin-action" type="button" wire:click="archivePost({{ $post['id'] }})">Archive</button>
                                        @endswitch
                                        <button class="admin-action journal-workspace__order-action" type="button" wire:click="movePost({{ $post['id'] }}, 'up')" @disabled(! $post['can_move_up'])>↑</button><button class="admin-action journal-workspace__order-action" type="button" wire:click="movePost({{ $post['id'] }}, 'down')" @disabled(! $post['can_move_down'])>↓</button><button class="admin-action is-danger" type="button" wire:click="mountAction('deletePost', { post: {{ $post['id'] }} })" @disabled(! $post['can_delete']) title="{{ $post['delete_help'] ?? 'Delete post' }}">Delete</button>
                                    </div></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    @if ($posts === [])@if ($unfilteredEntryCount > 0)<x-admin.empty-state title="No matching posts" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button></x-slot:actions></x-admin.empty-state>@else<x-admin.empty-state title="No posts added to this Blog" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('addPost')">Add post</button></x-slot:actions></x-admin.empty-state>@endif @endif
                </x-admin.table>

                <footer class="journal-workspace__pager"><label class="journal-workspace__pager-size"><span>Per page</span><select wire:model.live.number="pageSize"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label><span class="journal-workspace__pager-range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span><div class="journal-workspace__pager-actions admin-toolbar"><button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button><button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button></div></footer>
            </div>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>
