<x-filament-panels::page>
    <div class="artist-workspace artist-activity">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Editorial history</p>
                <h2>Activity</h2>
                <p>A readable record of administrative and editorial changes. Visitor analytics remain separate in Analytics.</p>
            </div>
        </header>

        <form class="artist-activity__filters" method="get" action="{{ request()->url() }}">
            <label>
                <span>Area</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select name="area">
                        <option value="">All areas</option>
                        @foreach ($areaOptions as $value => $label)
                            <option value="{{ $value }}" @selected($area === $value)>{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <label>
                <span>Change</span>
                <x-filament::input.wrapper>
                    <x-filament::input.select name="family">
                        <option value="">All changes</option>
                        @foreach ($familyOptions as $value => $label)
                            <option value="{{ $value }}" @selected($family === $value)>{{ $label }}</option>
                        @endforeach
                    </x-filament::input.select>
                </x-filament::input.wrapper>
            </label>

            <div class="artist-activity__filter-actions">
                <x-filament::button type="submit" size="sm">Apply</x-filament::button>
                @if ($area !== null || $family !== null)
                    <x-filament::button tag="a" href="{{ request()->url() }}" color="gray" size="sm">Clear</x-filament::button>
                @endif
            </div>
        </form>

        <section class="artist-activity__list" aria-label="Administrative activity">
            <div class="artist-dashboard__section-head"><span>Change</span><span>When</span></div>
            @forelse ($activity as $event)
                <article class="artist-activity__row">
                    <div class="artist-activity__identity">
                        <span class="artist-activity__area">{{ $event['area'] }}</span>
                        <strong>{{ $event['action'] }}</strong>
                        <small>{{ $event['target'] }} · {{ $event['actor'] }}</small>
                    </div>
                    <div class="artist-activity__meta">
                        <time datetime="{{ str_replace(' ', 'T', $event['timestamp']) }}" title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                        @if ($event['url'] !== null)
                            <a href="{{ $event['url'] }}">Open</a>
                        @endif
                    </div>
                </article>
            @empty
                <p class="artist-dashboard__quiet">No activity matches the selected filters.</p>
            @endforelse
        </section>

        @if ($paginator->hasPages())
            <div class="artist-activity__pagination">
                {{ $paginator->links() }}
            </div>
        @endif
    </div>
</x-filament-panels::page>
