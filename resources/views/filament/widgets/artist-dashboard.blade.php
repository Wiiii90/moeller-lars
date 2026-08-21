<x-filament-widgets::widget>
    <div class="artist-workspace artist-dashboard">
        <nav class="artist-dashboard__quick-actions" aria-label="Quick actions">
            @foreach ($quickActions as $quickAction)
                @switch($quickAction['key'])
                    @case('add_artwork')
                        {{ $this->addArtworkAction }}
                        @break
                    @case('pages')
                        {{ $this->managePagesAction }}
                        @break
                    @case('files')
                        {{ $this->filesAction }}
                        @break
                    @case('general')
                        {{ $this->generalAction }}
                        @break
                    @case('open_site')
                        {{ $this->openSiteAction }}
                        @break
                @endswitch
            @endforeach
        </nav>

        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Editorial overview</p>
                <h2>Website at a glance</h2>
            </div>
        </header>

        <div class="artist-dashboard__layout">
            <section class="artist-dashboard__section" aria-label="Traffic and engagement">
                <div class="artist-dashboard__section-head">
                    <span>Traffic &amp; engagement</span>
                    <span>{{ $analytics['range'] }}</span>
                </div>

                @if (in_array($analytics['status'], ['available', 'stale'], true))
                    <div class="artist-dashboard__row">
                        <span class="artist-dashboard__identity">
                            <strong>{{ $analytics['visits_display'] }} visits</strong>
                            <small>
                                {{ $analytics['visitors_display'] }} visitors
                                @if ($analytics['visits_delta'] !== null)
                                    · {{ $analytics['visits_delta'] }} vs previous period
                                @endif
                                · {{ $analytics['status_label'] }}
                            </small>
                        </span>
                        <a class="artist-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Analytics</a>
                    </div>

                    @if ($analytics['trend'] !== [])
                        <div class="artist-dashboard__row artist-dashboard__trend-row">
                            <span class="artist-dashboard__identity">
                                <strong>Visit trend</strong>
                                <small>{{ $analytics['trend']['start'] }} → {{ $analytics['trend']['end'] }}</small>
                            </span>
                            <svg width="260" height="58" viewBox="0 0 680 150" role="img" aria-label="Visit trend for the last 30 days" preserveAspectRatio="none">
                                <line x1="4" y1="146" x2="676" y2="146" stroke="currentColor" opacity="0.18" />
                                <polyline points="{{ $analytics['trend']['points'] }}" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    @else
                        <p class="artist-dashboard__quiet">Traffic time-series data is unavailable for this period.</p>
                    @endif

                    <div class="artist-kpi-strip artist-kpi-strip--3" aria-label="Engagement metrics">
                        <div class="artist-kpi"><span>Actions / visit</span><strong>{{ $analytics['actions_per_visit'] }}</strong></div>
                        <div class="artist-kpi"><span>Average visit</span><strong>{{ $analytics['average_visit'] }}</strong></div>
                        <div class="artist-kpi"><span>Bounce rate</span><strong>{{ $analytics['bounce_rate'] }}</strong></div>
                    </div>

                    @if ($analytics['status'] === 'stale' && $analytics['message'])
                        <p class="artist-dashboard__quiet">{{ $analytics['message'] }}</p>
                    @endif
                @else
                    <div class="artist-dashboard__row">
                        <span class="artist-dashboard__identity">
                            <strong>Traffic data is not available.</strong>
                            <small>{{ $analytics['message'] }}</small>
                        </span>
                        <a class="artist-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Open Analytics</a>
                    </div>
                @endif
            </section>

            <section class="artist-dashboard__section" aria-label="Most viewed content">
                <div class="artist-dashboard__section-head"><span>Most viewed content</span><span>Views</span></div>
                @if ($analytics['content_state'] === 'available')
                    @foreach ($analytics['content'] as $row)
                        <div class="artist-dashboard__row">
                            <span class="artist-dashboard__identity"><strong>{{ $row['label'] }}</strong></span>
                            <strong class="artist-dashboard__number">{{ number_format($row['value']) }}</strong>
                        </div>
                    @endforeach
                @elseif ($analytics['content_state'] === 'empty')
                    <p class="artist-dashboard__quiet">No viewed content recorded in this period.</p>
                @else
                    <p class="artist-dashboard__quiet">Content-level analytics is unavailable.</p>
                @endif
            </section>
        </div>

        <section class="artist-dashboard__section artist-dashboard__publication" aria-label="Editorial publication state">
            <div class="artist-dashboard__section-head"><span>Publication state</span><span>Current content</span></div>
            <div class="artist-kpi-strip artist-kpi-strip--4">
                @foreach ($editorialStatus as $status)
                    <div class="artist-kpi">
                        <span>{{ $status['label'] }}</span>
                        <strong>{{ number_format($status['value']) }}</strong>
                    </div>
                @endforeach
            </div>
        </section>

        <div class="artist-dashboard__layout">
            <section class="artist-dashboard__section" aria-label="Recent editorial activity">
                <div class="artist-dashboard__section-head">
                    <span>Recent activity</span>
                    <a class="artist-action" href="{{ $activityUrl }}">All activity</a>
                </div>
                @forelse ($activity as $event)
                    <div class="artist-dashboard__activity-row">
                        <span>
                            <strong>{{ $event['action'] }}</strong>
                            <small>{{ $event['area'] }} · {{ $event['target'] }}</small>
                        </span>
                        <time title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                    </div>
                @empty
                    <p class="artist-dashboard__quiet">No editorial activity recorded yet.</p>
                @endforelse
            </section>

            <aside class="artist-dashboard__side" aria-label="Website health and attention">
                <section class="artist-dashboard__section" aria-label="Needs attention">
                    <div class="artist-dashboard__section-head"><span>Needs attention</span></div>
                    @forelse ($attention as $item)
                        @if ($item['url'])
                            <a class="artist-dashboard__notice" href="{{ $item['url'] }}">
                                <span class="artist-dashboard__identity">
                                    <strong>{{ $item['label'] }}</strong>
                                    @if ($item['detail'])<small>{{ $item['detail'] }}</small>@endif
                                </span>
                                @if ($item['value'] !== null)<strong>{{ number_format($item['value']) }}{{ $item['value_suffix'] ?? '' }}</strong>@endif
                            </a>
                        @else
                            <div class="artist-dashboard__notice">
                                <span class="artist-dashboard__identity">
                                    <strong>{{ $item['label'] }}</strong>
                                    @if ($item['detail'])<small>{{ $item['detail'] }}</small>@endif
                                </span>
                                @if ($item['value'] !== null)<strong>{{ number_format($item['value']) }}{{ $item['value_suffix'] ?? '' }}</strong>@endif
                            </div>
                        @endif
                    @empty
                        <p class="artist-dashboard__quiet">No current content or integration warnings detected.</p>
                    @endforelse
                </section>

                <section class="artist-dashboard__section" aria-label="Storage headroom">
                    <div class="artist-dashboard__section-head"><span>Storage headroom</span><a class="artist-action" href="{{ $storage['url'] }}">Storage</a></div>
                    <a class="artist-dashboard__notice" href="{{ $storage['url'] }}">
                        <span class="artist-dashboard__identity">
                            <strong>{{ $storage['label'] }}</strong>
                            @if ($storage['remaining'] !== null)<small>{{ $storage['remaining'] }} remaining</small>@endif
                            @if ($storage['detail'])<small>{{ $storage['detail'] }}</small>@endif
                        </span>
                        @if ($storage['percent'] !== null)<strong>{{ $storage['percent'] }}%</strong>@endif
                    </a>
                </section>
            </aside>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
