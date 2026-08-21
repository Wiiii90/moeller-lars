<x-filament-panels::page>
    <div class="artist-workspace">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Editorial history</p>
                <h2>Activity</h2>
            </div>
        </header>

        <div class="artist-workspace__footnote" aria-label="Activity filters">
            <div class="artist-media-library__filters" aria-label="Time range">
                @foreach ($periodOptions as $value => $label)
                    <a
                        class="artist-action {{ $period === $value ? 'is-primary' : '' }}"
                        href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $value, 'area' => $area, 'family' => $family])) }}"
                    >{{ $label }}</a>
                @endforeach
            </div>
            <div class="artist-media-library__filters" aria-label="Editorial area">
                <a class="artist-action {{ $area === null ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'family' => $family])) }}">All areas</a>
                @foreach ($areaOptions as $value => $label)
                    <a class="artist-action {{ $area === $value ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'area' => $value, 'family' => $family])) }}">{{ $label }}</a>
                @endforeach
            </div>
            <div class="artist-media-library__filters" aria-label="Change type">
                <a class="artist-action {{ $family === null ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'area' => $area])) }}">All changes</a>
                @foreach ($familyOptions as $value => $label)
                    <a class="artist-action {{ $family === $value ? 'is-primary' : '' }}" href="{{ request()->url().'?'.http_build_query(array_filter(['period' => $period, 'area' => $area, 'family' => $value])) }}">{{ $label }}</a>
                @endforeach
            </div>
        </div>

        <section class="artist-dashboard__activity" aria-label="Administrative activity">
            <div class="artist-dashboard__section-head"><span>Change</span><span>When</span></div>
            @forelse ($activity as $event)
                <article class="artist-dashboard__activity-row">
                    <span>
                        <strong>{{ $event['area'] }}</strong>
                        <small>{{ $event['action'] }} — {{ $event['target'] }} · {{ $event['actor'] }}</small>
                    </span>
                    <div class="artist-section__actions">
                        <time datetime="{{ str_replace(' ', 'T', $event['timestamp']) }}" title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                        @if ($event['undo'] !== null)
                            <button
                                class="artist-action"
                                type="button"
                                wire:click="undo({{ $event['undo']['id'] }})"
                                wire:confirm="{{ $event['undo']['confirmation'] }}"
                            >Undo</button>
                        @endif
                        @if ($event['url'] !== null)
                            <a class="artist-action" href="{{ $event['url'] }}">Open</a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="artist-dashboard__quiet">No editorial activity matches these filters.</p>
            @endforelse
        </section>

        @if ($paginator->hasPages())
            <div class="artist-workspace__footnote">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
