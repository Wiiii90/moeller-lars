<x-filament-panels::page>
    <x-admin.workspace title="Home" class="admin-home-workspace">
        <x-admin.metrics :columns="6" aria-label="Home overview">
            @foreach ($metrics as $metric)
                <x-admin.metric :label="$metric['label']" :value="$metric['value']">{{ $metric['description'] }}</x-admin.metric>
            @endforeach
        </x-admin.metrics>

        <div class="home-workspace__controls" aria-label="Home controls">
            <div class="home-workspace__control-group">
                <span class="home-workspace__control-label">Home</span>
                <div class="home-workspace__actions">
                    <button class="admin-action" type="button" wire:click="mountAction('settings')">Settings</button>
                    <a class="admin-action" href="{{ $previewUrl }}" target="_blank" rel="noopener">Preview</a>
                    @if (in_array($template, ['under_construction', 'custom'], true))
                        <button class="admin-action" type="button" wire:click="mountAction('addComponent')">Add component</button>
                    @endif
                </div>
            </div>
        </div>

        @if ($template === 'artwork')
            <x-admin.section kicker="Selection" title="Current Home artwork">
                @if ($selectionIssue)
                    <x-admin.empty-state kicker="Needs attention" title="Home selection is ambiguous">
                        <p>{{ $selectionIssue }} Open one of the newest eligible artworks below and choose the explicit Home tie-breaker.</p>
                    </x-admin.empty-state>
                @elseif ($currentArtwork)
                    <x-admin.list>
                        <article class="admin-list__row admin-list__row--media">
                            <div class="admin-list__identity">
                                <span class="admin-list__eyebrow">Current hero</span>
                                <strong>{{ $currentArtwork['title'] }}</strong>
                                <span>
                                    {{ $currentArtwork['gallery'] }}
                                    @if ($currentArtwork['year']) · {{ $currentArtwork['year'] }}@endif
                                    @if ($currentArtwork['date']) · {{ $currentArtwork['date'] }}@endif
                                </span>
                            </div>
                            <div class="admin-list__meta">
                                <span>Eligible · Published</span>
                                @if ($currentArtwork['featured'])<span>Explicit tie-breaker</span>@endif
                            </div>
                            <div class="admin-list__media">
                                @if ($currentArtwork['thumbnail_url'])
                                    <img src="{{ $currentArtwork['thumbnail_url'] }}" alt="" width="76" height="58" loading="eager" decoding="async">
                                @else
                                    <span>No image</span>
                                @endif
                            </div>
                            <div class="admin-toolbar">
                                <a class="admin-action" href="{{ $currentArtwork['preview_url'] }}" target="_blank" rel="noopener">Preview</a>
                                <a class="admin-action is-primary" href="{{ $currentArtwork['edit_url'] }}">Edit artwork</a>
                                @if ($currentArtwork['gallery_url'])
                                    <a class="admin-action" href="{{ $currentArtwork['gallery_url'] }}">Open Gallery</a>
                                @endif
                            </div>
                        </article>
                    </x-admin.list>
                @else
                    <x-admin.empty-state kicker="No selection" title="No Home artwork is available">
                        <p>Publish artwork in a Gallery that is enabled as a Home source.</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>

            <x-admin.section kicker="Sources" title="Gallery Sources">
                @if ($galleries !== [])
                    <x-admin.list>
                        @foreach ($galleries as $gallery)
                            <article class="admin-list__row" wire:key="home-gallery-{{ $gallery['id'] }}">
                                <div class="admin-list__identity">
                                    <span class="admin-list__eyebrow">Gallery</span>
                                    <strong>{{ $gallery['name'] }}</strong>
                                    <span>{{ ucfirst($gallery['state']) }} · Home source {{ $gallery['eligible'] ? 'enabled' : 'disabled' }}</span>
                                </div>
                                <div class="admin-list__meta">
                                    <button
                                        type="button"
                                        class="admin-action {{ $gallery['eligible'] ? 'is-primary' : '' }}"
                                        wire:click="toggleGalleryEligibility({{ $gallery['id'] }})"
                                        aria-pressed="{{ $gallery['eligible'] ? 'true' : 'false' }}"
                                    >{{ $gallery['eligible'] ? 'Used on Home' : 'Not used on Home' }}</button>
                                </div>
                                <div class="admin-list__count">
                                    <strong>{{ $gallery['published_artworks'] }}</strong>
                                    <span>published · newest {{ $gallery['newest_year'] ?: '—' }}</span>
                                </div>
                                <div class="admin-toolbar">
                                    <a class="admin-action" href="{{ $gallery['workspace_url'] }}">Open Gallery</a>
                                </div>
                            </article>
                        @endforeach
                    </x-admin.list>
                @else
                    <x-admin.empty-state kicker="No Galleries" title="No Gallery sources exist" />
                @endif
            </x-admin.section>

            <x-admin.section kicker="Candidates" title="Hero Candidates">
                @if ($newestEligibleArtworks !== [])
                    <x-admin.list>
                        @foreach ($newestEligibleArtworks as $artwork)
                            <article class="admin-list__row admin-list__row--media" wire:key="home-candidate-{{ $artwork['id'] }}">
                                <div class="admin-list__identity">
                                    <span class="admin-list__eyebrow">Eligible artwork</span>
                                    <strong>{{ $artwork['title'] }}</strong>
                                    <span>
                                        {{ $artwork['gallery'] }}
                                        @if ($artwork['date']) · {{ $artwork['date'] }}@elseif ($artwork['year']) · {{ $artwork['year'] }}@endif
                                    </span>
                                </div>
                                <div class="admin-list__meta">
                                    <span>Candidate · Published</span>
                                    @if ($artwork['featured'])<span>Explicit tie-breaker</span>@endif
                                </div>
                                <div class="admin-list__media">
                                    @if ($artwork['thumbnail_url'])
                                        <img src="{{ $artwork['thumbnail_url'] }}" alt="" width="64" height="48" loading="lazy" decoding="async">
                                    @else
                                        <span>No image</span>
                                    @endif
                                </div>
                                <div class="admin-toolbar">
                                    <a class="admin-action" href="{{ $artwork['preview_url'] }}" target="_blank" rel="noopener">Preview</a>
                                    <a class="admin-action" href="{{ $artwork['edit_url'] }}">Edit</a>
                                </div>
                            </article>
                        @endforeach
                    </x-admin.list>
                @else
                    <x-admin.empty-state kicker="No candidates" title="No eligible artworks exist">
                        <p>Enable a published Gallery as a Home source and ensure its published artwork has a year.</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>
        @elseif (in_array($template, ['under_construction', 'custom'], true))
            <x-admin.section
                :kicker="$template === 'under_construction' ? 'Landing page' : 'Composition'"
                :title="$template === 'under_construction' ? 'Under Construction composition' : 'Custom Home composition'"
            >
                @if ($readinessWarning)
                    <p class="home-workspace__notice">{{ $readinessWarning }}</p>
                @endif

                @if ($components !== [])
                    <div class="home-workspace__components" role="list" aria-label="Home component sequence">
                        @foreach ($components as $component)
                            <article class="home-workspace__component" role="listitem" wire:key="home-component-{{ $template }}-{{ $component['index'] }}-{{ $component['type'] }}">
                                <div class="home-workspace__component-kind">
                                    <span class="admin-list__eyebrow">{{ $component['type_label'] }}</span>
                                    <strong>{{ $component['summary'] }}</strong>
                                </div>

                                <div class="home-workspace__component-preview">
                                    @if ($component['preview_url'])
                                        <img src="{{ $component['preview_url'] }}" alt="" width="96" height="72" loading="lazy" decoding="async">
                                    @elseif ($component['type'] === 'divider')
                                        <span class="home-workspace__divider-preview" aria-hidden="true"></span>
                                    @endif
                                </div>

                                <div class="home-workspace__component-actions">
                                    <button
                                        class="admin-action"
                                        type="button"
                                        wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'up')"
                                        @disabled(! $component['can_move_up'])
                                        aria-label="Move {{ $component['type_label'] }} up"
                                    >↑</button>
                                    <button
                                        class="admin-action"
                                        type="button"
                                        wire:click="moveComponent({{ $component['index'] }}, '{{ $component['type'] }}', 'down')"
                                        @disabled(! $component['can_move_down'])
                                        aria-label="Move {{ $component['type_label'] }} down"
                                    >↓</button>
                                    @if ($component['editable'])
                                        <button
                                            class="admin-action"
                                            type="button"
                                            wire:click="mountAction('editComponent', { index: {{ $component['index'] }}, type: '{{ $component['type'] }}' })"
                                        >Edit</button>
                                    @endif
                                    <button
                                        class="admin-action"
                                        type="button"
                                        wire:click="mountAction('removeComponent', { index: {{ $component['index'] }}, type: '{{ $component['type'] }}' })"
                                    >Remove</button>
                                </div>
                            </article>
                        @endforeach
                    </div>
                @else
                    <x-admin.empty-state kicker="Empty composition" title="Add the first Home component">
                        <p>Use Image, Heading, Rich Text or Divider components. Images come from Media Files.</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>
        @elseif ($template === 'skip_home')
            <x-admin.section kicker="Public behavior" title="Skip Home">
                @if ($skipTarget)
                    <div class="home-workspace__skip-status">
                        <div>
                            <span class="admin-list__eyebrow">Current target</span>
                            <strong>{{ $skipTarget['label'] }}</strong>
                            <span>{{ $skipTarget['type'] }} · {{ $skipTarget['path'] }}</span>
                        </div>
                        <div class="home-workspace__redirect-expression" aria-label="Current Home redirect">
                            <code>/</code><span aria-hidden="true">→</span><code>{{ $skipTarget['path'] }}</code>
                        </div>
                        <a class="admin-action" href="{{ $skipTarget['url'] }}" target="_blank" rel="noopener">Open target</a>
                    </div>
                @else
                    <x-admin.empty-state kicker="Needs attention" title="No redirect target is available">
                        <p>{{ $readinessWarning }} The public root renders a safe fallback instead of redirecting.</p>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
