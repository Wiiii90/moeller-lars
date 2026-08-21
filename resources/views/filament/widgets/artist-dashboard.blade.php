@once
    <link rel="stylesheet" href="{{ asset('css/filament-dashboard.css') }}">
@endonce

<x-filament-widgets::widget>
    <div class="artist-dashboard" wire:init="loadAnalytics">
        <section class="artist-dashboard__intro" aria-label="Website at a glance">
            <div>
                <p class="artist-dashboard__kicker">Website at a glance</p>
                <p>Traffic, editorial activity and the few site conditions that need attention.</p>
            </div>

            <nav class="artist-dashboard__quick-actions" aria-label="Quick actions">
                <span>Quick actions</span>
                <a href="{{ $publicSiteUrl }}" target="_blank" rel="noopener noreferrer">Open public site</a>
                @foreach ($quickActions as $action)
                    <a href="{{ $action['url'] }}" title="{{ $action['reason'] }}">{{ $action['label'] }}</a>
                @endforeach
            </nav>
        </section>

        <section class="artist-dashboard__health-band" aria-label="Current site and admin health">
            @foreach ($health as $item)
                <div class="artist-dashboard__health-item is-{{ $item['state'] }}">
                    <span class="artist-dashboard__health-label">{{ $item['label'] }}</span>
                    <strong>{{ $item['value'] }}</strong>
                    <small>{{ $item['detail'] }}</small>
                </div>
            @endforeach
        </section>

        <section class="artist-dashboard__analytics" aria-label="Traffic and engagement">
            <div class="artist-dashboard__section-head">
                <h3>Traffic &amp; engagement</h3>
                <div>
                    <span>Last 30 days</span>
                    <a href="{{ $analyticsUrl }}">Full analytics</a>
                </div>
            </div>

            @if (! ($analytics['loaded'] ?? false))
                <div class="artist-dashboard__analytics-loading" role="status">
                    <span class="artist-dashboard__pulse" aria-hidden="true"></span>
                    <div>
                        <strong>Loading visitor signals…</strong>
                        <p>The rest of the dashboard is already available while the shared Matomo report resolves.</p>
                    </div>
                </div>
            @elseif (in_array($analytics['status'], ['available', 'stale'], true))
                <div class="artist-dashboard__analytics-main">
                    <div class="artist-dashboard__trend-block">
                        <div class="artist-dashboard__analytics-statusline">
                            <span class="artist-dashboard__availability is-{{ $analytics['status'] }}">{{ $analytics['status_label'] }}</span>
                            @if ($analytics['status'] === 'stale')
                                <span>Showing the most recent cached aggregate</span>
                            @else
                                <span>Shared Matomo reporting cache</span>
                            @endif
                        </div>

                        <dl class="artist-dashboard__metrics">
                            @foreach ($analytics['metrics'] as $metric)
                                <div>
                                    <dt>{{ $metric['label'] }}</dt>
                                    <dd>{{ $metric['value'] }}</dd>
                                    <small>{{ $metric['comparison'] }}</small>
                                </div>
                            @endforeach
                        </dl>

                        @if ($analytics['series_state'] === 'available')
                            <figure class="artist-dashboard__trend">
                                <svg viewBox="0 0 720 180" role="img" aria-label="Visits and tracked actions trend for the last 30 days" preserveAspectRatio="none">
                                    <line x1="8" y1="172" x2="712" y2="172"></line>
                                    <polyline class="is-actions" points="{{ $analytics['chart']['actions_points'] }}"></polyline>
                                    <polyline class="is-visits" points="{{ $analytics['chart']['visits_points'] }}"></polyline>
                                </svg>
                                <figcaption>
                                    <time>{{ $analytics['chart']['start'] }}</time>
                                    <span><i class="is-visits"></i>Visits <i class="is-actions"></i>Actions</span>
                                    <time>{{ $analytics['chart']['end'] }}</time>
                                </figcaption>
                            </figure>
                        @elseif ($analytics['series_state'] === 'no_data')
                            <p class="artist-dashboard__empty">No traffic-series data was returned for this period.</p>
                        @else
                            <p class="artist-dashboard__empty">Traffic trend is unavailable while the headline aggregates remain usable.</p>
                        @endif
                    </div>

                    <div class="artist-dashboard__ranked">
                        <div class="artist-dashboard__minor-head"><span>Most viewed content</span><span>Views / actions</span></div>
                        @if ($analytics['content_state'] === 'available')
                            <ol>
                                @foreach ($analytics['top_content'] as $row)
                                    <li>
                                        <span class="artist-dashboard__rank">{{ $loop->iteration }}</span>
                                        <span title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                                        <strong>{{ $row['value'] === null ? '—' : number_format($row['value']) }}</strong>
                                    </li>
                                @endforeach
                            </ol>
                        @elseif ($analytics['content_state'] === 'no_data')
                            <p class="artist-dashboard__empty">No content activity was returned for this period.</p>
                        @else
                            <p class="artist-dashboard__empty">Content-level analytics is unavailable.</p>
                        @endif
                    </div>
                </div>

                @if ($analytics['message'])
                    <p class="artist-dashboard__data-note">{{ $analytics['message'] }}</p>
                @endif
            @else
                <div class="artist-dashboard__analytics-unavailable">
                    <span class="artist-dashboard__availability is-unavailable">{{ $analytics['status_label'] }}</span>
                    <strong>Visitor analytics is unavailable.</strong>
                    <p>{{ $analytics['message'] }}</p>
                    <a href="{{ $analyticsUrl }}">Open Analytics</a>
                </div>
            @endif
        </section>

        <div class="artist-dashboard__lower-grid">
            <section class="artist-dashboard__activity" aria-label="Recent editorial activity">
                <div class="artist-dashboard__section-head">
                    <h3>Recent activity</h3>
                    <a href="{{ $activityUrl }}">All activity</a>
                </div>

                <div class="artist-dashboard__timeline">
                    @forelse ($activity as $event)
                        @if ($event['url'])
                            <a class="artist-dashboard__activity-row" href="{{ $event['url'] }}">
                                <span class="artist-dashboard__timeline-mark" aria-hidden="true"></span>
                                <span class="artist-dashboard__activity-copy">
                                    <strong>{{ $event['action'] }}</strong>
                                    <small>{{ $event['area'] }} · {{ $event['target'] }}</small>
                                </span>
                                <time title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                            </a>
                        @else
                            <div class="artist-dashboard__activity-row">
                                <span class="artist-dashboard__timeline-mark" aria-hidden="true"></span>
                                <span class="artist-dashboard__activity-copy">
                                    <strong>{{ $event['action'] }}</strong>
                                    <small>{{ $event['area'] }} · {{ $event['target'] }}</small>
                                </span>
                                <time title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                            </div>
                        @endif
                    @empty
                        <p class="artist-dashboard__empty">No editorial activity has been recorded in the current activity window.</p>
                    @endforelse
                </div>
            </section>

            <aside class="artist-dashboard__attention" aria-label="Needs attention">
                <div class="artist-dashboard__section-head">
                    <h3>Needs attention</h3>
                    <a href="{{ \App\Filament\Pages\StorageCapacity::getUrl() }}">Storage</a>
                </div>

                @if (($analytics['loaded'] ?? false) && $analytics['status'] === 'unavailable')
                    <a class="artist-dashboard__notice is-attention" href="{{ $analyticsUrl }}">
                        <span><strong>Analytics reporting unavailable</strong><small>{{ $analytics['message'] }}</small></span>
                    </a>
                @endif

                @forelse ($attention as $item)
                    @if ($item['url'])
                        <a class="artist-dashboard__notice is-{{ $item['severity'] }}" href="{{ $item['url'] }}">
                            <span><strong>{{ $item['label'] }}</strong><small>{{ $item['detail'] }}</small></span>
                            @if ($item['value'] !== null)<b>{{ number_format($item['value']) }}</b>@endif
                        </a>
                    @else
                        <div class="artist-dashboard__notice is-{{ $item['severity'] }}">
                            <span><strong>{{ $item['label'] }}</strong><small>{{ $item['detail'] }}</small></span>
                            @if ($item['value'] !== null)<b>{{ number_format($item['value']) }}</b>@endif
                        </div>
                    @endif
                @empty
                    @if (! (($analytics['loaded'] ?? false) && $analytics['status'] === 'unavailable'))
                        <p class="artist-dashboard__empty">No current issues detected by the available checks.</p>
                    @endif
                @endforelse
            </aside>
        </div>
    </div>
</x-filament-widgets::widget>
