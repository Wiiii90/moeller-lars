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
            <section class="artist-section-list" aria-label="Blog posts">
                @foreach ($posts as $post)
                    <article class="artist-section" wire:key="blog-post-{{ $post['id'] }}">
                        <div class="artist-section__identity">
                            <span class="artist-section__type">Blog post</span>
                            <strong>{{ $post['title'] }}</strong>
                            <span class="artist-section__path">{{ $post['meta'] }}</span>
                        </div>
                        <div class="artist-section__state">
                            <span class="{{ $post['state'] === 'published' ? 'is-published' : '' }}">{{ ucfirst($post['state']) }}</span>
                        </div>
                        <div class="artist-section__count">
                            <strong>{{ $post['date'] }}</strong>
                            <span>Publication</span>
                        </div>
                        <div class="artist-section__actions">
                            <a class="artist-action is-primary" href="{{ $post['edit_url'] }}">Edit</a>
                            @if ($post['public_url'])<a class="artist-action" href="{{ $post['public_url'] }}" target="_blank" rel="noopener">View</a>@endif
                            <span class="artist-section__order" aria-label="Reorder {{ $post['title'] }}">
                                <button class="artist-action" type="button" wire:click="movePost({{ $post['id'] }}, 'up')" @disabled(! $post['can_move_up']) aria-label="Move {{ $post['title'] }} earlier">↑</button>
                                <button class="artist-action" type="button" wire:click="movePost({{ $post['id'] }}, 'down')" @disabled(! $post['can_move_down']) aria-label="Move {{ $post['title'] }} later">↓</button>
                            </span>
                        </div>
                    </article>
                @endforeach
            </section>
        @else
            <section class="artist-gallery-empty"><p class="artist-workspace__kicker">Empty Blog</p><h3>Write the first post</h3><p>Create a draft now; public Blog visibility remains disabled until it is enabled from Pages.</p></section>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
