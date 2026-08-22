<x-filament-panels::page>
    @php($published = collect($posts)->where('state', 'published')->count())
    @php($scheduled = collect($posts)->where('state', 'scheduled')->count())

    <x-admin.workspace kicker="Blog" title="Editorial queue">
        <x-slot:summary>
            <div><strong>{{ count($posts) }}</strong><span>Posts</span></div>
            <div><strong>{{ $published }}</strong><span>Published</span></div>
            <div><strong>{{ $scheduled }}</strong><span>Scheduled</span></div>
        </x-slot:summary>

        @if ($posts !== [])
            <x-admin.list aria-label="Blog posts">
                @foreach ($posts as $post)
                    <article class="admin-list__row" wire:key="blog-post-{{ $post['id'] }}">
                        <div class="admin-list__identity">
                            <span class="admin-list__eyebrow">Blog post</span>
                            <strong>{{ $post['title'] }}</strong>
                            <span>{{ $post['meta'] }}</span>
                        </div>
                        <div class="admin-list__meta">
                            <span>{{ ucfirst($post['state']) }}</span>
                        </div>
                        <div class="admin-list__count">
                            <strong>{{ $post['date'] }}</strong>
                            <span>Publication</span>
                        </div>
                        <div class="admin-toolbar">
                            <a class="admin-action is-primary" href="{{ $post['edit_url'] }}">Edit</a>
                            @if ($post['public_url'])<a class="admin-action" href="{{ $post['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="admin-toolbar" aria-label="Reorder {{ $post['title'] }}">
                                <button class="admin-action" type="button" wire:click="movePost({{ $post['id'] }}, 'up')" @disabled(! $post['can_move_up']) aria-label="Move {{ $post['title'] }} earlier">↑</button>
                                <button class="admin-action" type="button" wire:click="movePost({{ $post['id'] }}, 'down')" @disabled(! $post['can_move_down']) aria-label="Move {{ $post['title'] }} later">↓</button>
                            </span>
                        </div>
                    </article>
                @endforeach
            </x-admin.list>
        @else
            <x-admin.empty-state kicker="Empty Blog" title="Write the first post">
                <p>Create a draft now; public Blog visibility remains disabled until it is enabled from Pages.</p>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
