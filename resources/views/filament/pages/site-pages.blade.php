<x-filament-panels::page>
    @php
        $published = collect($sections)->where('state', 'published')->count();
        $visible = collect($sections)->where('visible', true)->count();
        $galleries = collect($sections)->where('type', 'gallery')->count();
    @endphp

    <div class="artist-workspace">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Site structure</p>
                <h2>Public pages</h2>
                <p>One ordered view of the public site. Galleries may form one submenu level; Vita, Blog and Exhibitions remain typed sections with their own editors.</p>
            </div>

            <div class="artist-workspace__summary" aria-label="Site structure summary">
                <div>
                    <strong>{{ count($sections) }}</strong>
                    <span>Sections</span>
                </div>
                <div>
                    <strong>{{ $galleries }}</strong>
                    <span>Galleries</span>
                </div>
                <div>
                    <strong>{{ $published }}</strong>
                    <span>Published</span>
                </div>
                <div>
                    <strong>{{ $visible }}</strong>
                    <span>In menu</span>
                </div>
            </div>
        </header>

        <section aria-label="Public site sections">
            <div class="artist-section-list">
                @foreach ($sections as $section)
                    @php($path = parse_url($section['public_url'], PHP_URL_PATH) ?: '/')
                    <article class="artist-section" data-depth="{{ $section['depth'] }}" wire:key="site-section-{{ $section['id'] }}">
                        <div class="artist-section__identity">
                            <span class="artist-section__type">{{ $section['type_label'] }}</span>
                            <strong>{{ $section['navigation_label'] ?: $section['title'] }}</strong>
                            <span class="artist-section__path">{{ $path }}</span>
                        </div>

                        <div class="artist-section__state" aria-label="Publication status">
                            <span class="{{ $section['state'] === 'published' ? 'is-published' : '' }}">
                                {{ $section['state'] === 'published' ? 'Published' : 'Hidden' }}
                            </span>
                            @if ($section['type'] !== 'home')
                                <span class="{{ $section['visible'] ? 'is-visible' : '' }}">
                                    {{ $section['visible'] ? 'In navigation' : 'Not in navigation' }}
                                </span>
                            @endif
                        </div>

                        <div class="artist-section__count">
                            <strong>{{ $section['count'] }}</strong>
                            <span>{{ $section['count_label'] }}</span>
                        </div>

                        <div class="artist-section__actions">
                            <a class="is-primary" href="{{ $section['content_url'] }}">Content</a>
                            @if ($section['editor_url'])
                                <a href="{{ $section['editor_url'] }}">Settings</a>
                            @endif
                            <a href="{{ $section['public_url'] }}" target="_blank" rel="noopener">View</a>
                            <span class="artist-section__order" aria-label="Reorder {{ $section['navigation_label'] ?: $section['title'] }}">
                                <button
                                    type="button"
                                    wire:click="moveSection({{ $section['id'] }}, 'up')"
                                    aria-label="Move {{ $section['navigation_label'] ?: $section['title'] }} earlier"
                                    @disabled(! $section['can_move_up'])
                                >↑</button>
                                <button
                                    type="button"
                                    wire:click="moveSection({{ $section['id'] }}, 'down')"
                                    aria-label="Move {{ $section['navigation_label'] ?: $section['title'] }} later"
                                    @disabled(! $section['can_move_down'])
                                >↓</button>
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <footer class="artist-workspace__footnote">
            <span>Order controls operate within the current level. Child Galleries remain inside their parent submenu.</span>
            <span>Media, Analytics and Storage are global tools and intentionally do not appear as public pages.</span>
        </footer>
    </div>
</x-filament-panels::page>
