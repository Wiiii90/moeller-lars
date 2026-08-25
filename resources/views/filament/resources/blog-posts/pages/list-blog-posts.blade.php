<x-filament-panels::page>
    @php
        $visibleIds = collect($posts)->pluck('id')->map(static fn (mixed $id): int => (int) $id)->all();
        $selectedIds = collect($selectedPostIds)->map(static fn (mixed $id): int => (int) $id)->all();
        $resultStart = $total === 0 ? 0 : (($page - 1) * $pageSize) + 1;
        $resultEnd = $total === 0 ? 0 : min($total, $page * $pageSize);
    @endphp

    <x-admin.workspace :title="$journalTitle" class="journal-workspace journal-workspace--blog">
        <x-admin.metrics :columns="6" aria-label="Blog overview">
            @foreach ($metrics as $metric)<x-admin.metric :label="$metric['label']" :value="$metric['value']" />@endforeach
        </x-admin.metrics>

        <x-admin.section class="journal-workspace__entries" aria-label="Blog entries">
            <div class="journal-workspace__controls is-blog" aria-label="Blog controls">
                <label class="journal-workspace__field journal-workspace__search"><span>Search posts</span>
                    <input type="search" wire:model.blur="search" x-on:keydown.enter.prevent="$el.blur()" placeholder="Title or excerpt" autocomplete="off">
                </label>
                <label class="journal-workspace__field"><span>Status</span>
                    <select wire:model.change="statusFilter">
                        <option value="any">Any</option><option value="draft">Draft</option><option value="scheduled">Scheduled</option>
                        <option value="published">Published</option><option value="unpublished">Unpublished</option><option value="archived">Archived</option>
                    </select>
                </label>
                <div class="journal-workspace__control-group"><span class="journal-workspace__control-label">Filter</span><button class="admin-action" type="button" wire:click="resetFilters">Reset</button></div>
                <div class="journal-workspace__control-group journal-workspace__journal-group"><span class="journal-workspace__control-label">Journal</span>
                    <div class="journal-workspace__journal-actions">
                        <button class="admin-action" type="button" wire:click="mountAction('journalSettings')">Settings</button>
                        <button class="admin-action" type="button" wire:click="mountAction('addPost')">Add post</button>
                        @if ($journalPublicUrl)<a class="admin-action" href="{{ $journalPublicUrl }}" target="_blank" rel="noopener">Preview</a>
                        @else<button class="admin-action" type="button" disabled title="Publish this Journal in Pages before previewing it">Preview</button>@endif
                    </div>
                </div>
                <div class="journal-workspace__control-group journal-workspace__selection" x-data="{ open: false }" x-on:keydown.escape.window="open = false">
                    <span class="journal-workspace__control-label">Selection</span>
                    <div class="journal-workspace__selection-anchor">
                        <button class="admin-action journal-workspace__selection-trigger" type="button" x-on:click="open = !open" x-bind:aria-expanded="open.toString()" aria-haspopup="menu" @disabled($selectedPostIds === [])>Selected posts <span class="journal-workspace__selection-count">{{ count($selectedPostIds) }}</span></button>
                        <div class="journal-workspace__selection-menu" role="menu" x-show="open" x-cloak x-on:click.outside="open = false">
                            <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('up')" x-on:click="open = false">Move selected up</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="moveSelectedEntries('down')" x-on:click="open = false">Move selected down</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="publishSelectedPosts" x-on:click="open = false">Publish selected</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="unpublishSelectedPosts" x-on:click="open = false">Unpublish selected</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="archiveSelectedPosts" x-on:click="open = false">Archive selected</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="restoreSelectedPosts" x-on:click="open = false">Restore selected to draft</button>
                            <button class="admin-action" type="button" role="menuitem" wire:click="mountAction('deleteSelectedPosts')" x-on:click="open = false">Delete selected</button>
                        </div>
                    </div>
                </div>
            </div>

            <x-admin.table class="journal-workspace__table-wrap">
                <table class="journal-workspace__table journal-workspace__table--blog">
                    <thead><tr>
                        <th scope="col" class="journal-workspace__selection-head"><input type="checkbox" x-data="{}" wire:click.prevent="toggleVisibleSelection" x-effect="const visibleIds=@js($visibleIds);const selectedIds=$wire.selectedPostIds.map(Number);const n=visibleIds.filter((id)=>selectedIds.includes(id)).length;$el.checked=visibleIds.length>0&&n===visibleIds.length;$el.indeterminate=n>0&&n<visibleIds.length;$el.setAttribute('aria-checked',$el.indeterminate?'mixed':($el.checked?'true':'false'));" @disabled($visibleIds === []) aria-label="Toggle selection for visible posts"></th>
                        <th scope="col">Post</th><th scope="col">Status</th><th scope="col">Publication</th><th scope="col">Actions</th>
                    </tr></thead>
                    <tbody>
                    @foreach ($posts as $post)
                        <tr class="{{ in_array($post['id'], $selectedIds, true) ? 'is-selected' : '' }}" wire:key="blog-post-{{ $post['id'] }}">
                            <td class="journal-workspace__selection-cell"><input type="checkbox" wire:model.live="selectedPostIds" value="{{ $post['id'] }}" aria-label="Select {{ $post['title'] }}"></td>
                            <td class="journal-workspace__identity"><strong>{{ $post['title'] }}</strong>@if ($post['excerpt'])<small>{{ $post['excerpt'] }}</small>@endif</td>
                            <td><span class="journal-workspace__state is-{{ $post['state'] }}">{{ ucfirst($post['state']) }}</span></td>
                            <td class="journal-workspace__publication">{{ $post['publication'] }}</td>
                            <td class="journal-workspace__actions"><div class="journal-workspace__row-actions">
                                <button class="admin-action journal-workspace__edit-action" type="button" wire:click="mountAction('editPost', { post: {{ $post['id'] }} })">Edit</button>
                                @if ($post['public_url'])<a class="journal-workspace__icon-action" href="{{ $post['public_url'] }}" target="_blank" rel="noopener" title="View public post"><x-filament::icon icon="heroicon-m-arrow-top-right-on-square" /></a>
                                @else<button class="journal-workspace__icon-action" type="button" disabled title="Public view unavailable"><x-filament::icon icon="heroicon-m-arrow-top-right-on-square" /></button>@endif
                                @switch($post['state'])
                                    @case('published')<button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="unpublishPost({{ $post['id'] }})">Unpublish</button>@break
                                    @case('scheduled')<button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="restorePostDraft({{ $post['id'] }})">Cancel</button>@break
                                    @case('archived')<button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="restorePostDraft({{ $post['id'] }})">Restore</button>@break
                                    @default
                                        <button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="publishPost({{ $post['id'] }})">Publish</button>
                                        <button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="mountAction('schedulePost', { post: {{ $post['id'] }} })">Schedule</button>
                                @endswitch
                                @if ($post['state'] !== 'archived')<button class="admin-action journal-workspace__lifecycle-action" type="button" wire:click="archivePost({{ $post['id'] }})">Archive</button>@endif
                                <button class="admin-action journal-workspace__order-action" type="button" wire:click="movePost({{ $post['id'] }}, 'up')" @disabled(! $post['can_move_up'])>↑</button>
                                <button class="admin-action journal-workspace__order-action" type="button" wire:click="movePost({{ $post['id'] }}, 'down')" @disabled(! $post['can_move_down'])>↓</button>
                                <button class="journal-workspace__icon-action" type="button" wire:click="mountAction('deletePost', { post: {{ $post['id'] }} })" @disabled(! $post['can_delete']) title="{{ $post['delete_help'] ?? 'Delete post' }}"><x-filament::icon icon="heroicon-m-trash" /></button>
                            </div></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
                @if ($posts === [])
                    @if ($unfilteredEntryCount > 0)<x-admin.empty-state title="No matching posts" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="resetFilters">Clear filters</button></x-slot:actions></x-admin.empty-state>
                    @else<x-admin.empty-state title="No posts added to this Blog" minimal><x-slot:actions><button class="admin-action" type="button" wire:click="mountAction('addPost')">Add post</button></x-slot:actions></x-admin.empty-state>@endif
                @endif
            </x-admin.table>
            <footer class="journal-workspace__pager">
                <label class="journal-workspace__pager-size"><span>Per page</span><select wire:model.live.number="pageSize"><option value="25">25</option><option value="50">50</option><option value="100">100</option></select></label>
                <span class="journal-workspace__pager-range">@if ($total === 0)0 of 0 @else{{ $resultStart }}–{{ $resultEnd }} of {{ $total }}@endif</span>
                <div class="journal-workspace__pager-actions admin-toolbar"><button class="admin-action" type="button" wire:click="previousPage" @disabled($page <= 1)>Previous</button><button class="admin-action" type="button" wire:click="nextPage" @disabled($page >= $pages)>Next</button></div>
            </footer>
        </x-admin.section>
    </x-admin.workspace>
</x-filament-panels::page>
