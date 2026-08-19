<x-filament-widgets::widget>
    <div class="artist-workspace artist-dashboard">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Editorial overview</p>
                <h2>Website at a glance</h2>
                <p>Public structure, content state and recent editorial work in one compact workspace. Detailed editing stays in the relevant page or library.</p>
            </div>
        </header>

        <div class="artist-dashboard__layout">
            <section class="artist-dashboard__content" aria-label="Content overview">
                <div class="artist-dashboard__section-head"><span>Content</span><span>State</span></div>
                @foreach ($sections as $section)
                    <a class="artist-dashboard__row" href="{{ $section['url'] }}">
                        <span class="artist-dashboard__identity"><strong>{{ $section['label'] }}</strong><small>{{ $section['detail'] }}</small></span>
                        <strong class="artist-dashboard__number">{{ $section['total'] }}</strong>
                    </a>
                @endforeach
            </section>

            <aside class="artist-dashboard__side">
                <section class="artist-dashboard__attention" aria-label="Needs attention">
                    <div class="artist-dashboard__section-head"><span>Needs attention</span></div>
                    @forelse ($attention as $item)
                        <a class="artist-dashboard__notice" href="{{ $item['url'] }}">
                            <span>{{ $item['label'] }}</span>
                            @if ($item['value'] !== null)<strong>{{ $item['value'] }}</strong>@endif
                        </a>
                    @empty
                        <p class="artist-dashboard__quiet">No current publication blockers detected.</p>
                    @endforelse
                </section>

                <section class="artist-dashboard__activity" aria-label="Recent editorial activity">
                    <div class="artist-dashboard__section-head"><span>Recent activity</span><a class="artist-action" href="{{ $activityUrl }}">All activity</a></div>
                    @forelse ($activity as $event)
                        <div class="artist-dashboard__activity-row">
                            <span><strong>{{ $event['area'] }}</strong><small>{{ $event['action'] }} — {{ $event['target'] }}</small></span>
                            <time title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                        </div>
                    @empty
                        <p class="artist-dashboard__quiet">No editorial activity recorded yet.</p>
                    @endforelse
                </section>
            </aside>
        </div>
    </div>
</x-filament-widgets::widget>
