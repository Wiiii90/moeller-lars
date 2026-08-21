<x-filament-widgets::widget>
    <div class="artist-workspace artist-dashboard">
        <header class="artist-workspace__head artist-dashboard__head">
            <div>
                <p class="artist-workspace__kicker">Editorial overview</p>
                <h2>Website at a glance</h2>
                <div class="artist-dashboard__quick-actions" aria-label="Quick actions">
                    {{ $this->addArtworkAction }}
                    {{ $this->addExhibitionAction }}
                    {{ $this->addCvEntryAction }}
                    {{ $this->addBlogPostAction }}
                    {{ $this->managePagesAction }}
                    {{ $this->openSiteAction }}
                </div>
                <p>Traffic, recent editorial work and the few site conditions that need attention.</p>
            </div>
        </header>

        @if ($quickActions !== [])
            <nav class="artist-dashboard__adaptive-actions" aria-label="Personalized quick actions">
                <span>For you · Based on repeated admin work</span>
                @foreach ($quickActions as $action)
                    <a href="{{ $action['url'] }}" title="{{ $action['reason'] }}">{{ $action['label'] }}</a>
                @endforeach
            </nav>
        @endif

        <section class="artist-dashboard__analytics" aria-label="Traffic and engagement">
            <div class="artist-dashboard__section-head">
                <span>Traffic &amp; engagement</span>
                <span class="artist-dashboard__section-meta">
                    {{ $analytics['range'] }}
                    <a class="artist-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Analytics</a>
                </span>
            </div>

            @if (in_array($analytics['status'], ['available', 'stale'], true))
                <div class="artist-dashboard__analytics-grid">
                    <div class="artist-dashboard__traffic">
                        <div class="artist-dashboard__traffic-heading">
                            <div>
                                <span>Visits</span>
                                <strong>{{ $analytics['visits_display'] }}</strong>
                            </div>
                            <div class="artist-dashboard__traffic-context">
                                <span class="artist-dashboard__availability is-{{ $analytics['status'] }}">{{ $analytics['status_label'] }}</span>
                                @if ($analytics['visits_delta'] !== null)
                                    <span>{{ $analytics['visits_delta'] }} vs previous period</span>
                                @else
                                    <span>No comparable previous period</span>
                                @endif
                            </div>
                        </div>

                        @if ($analytics['trend'] !== [])
                            <div class="artist-dashboard__trend">
                                <svg viewBox="0 0 680 150" role="img" aria-label="Visit trend for the last 30 days" preserveAspectRatio="none">
                                    <line x1="4" y1="146" x2="676" y2="146" />
                                    <polyline points="{{ $analytics['trend']['points'] }}" />
                                </svg>
                                <div>
                                    <time>{{ $analytics['trend']['start'] }}</time>
                                    @if (! $analytics['trend']['has_visits'])
                                        <span>No visits recorded in this period</span>
                                    @endif
                                    <time>{{ $analytics['trend']['end'] }}</time>
                                </div>
                            </div>
                        @else
                            <p class="artist-dashboard__quiet">Traffic time-series data is unavailable for this period.</p>
                        @endif

                        <dl class="artist-dashboard__engagement">
                            <div><dt>Visitors</dt><dd>{{ $analytics['visitors_display'] }}</dd></div>
                            <div><dt>Actions / visit</dt><dd>{{ $analytics['actions_per_visit'] }}</dd></div>
                            <div><dt>Average visit</dt><dd>{{ $analytics['average_visit'] }}</dd></div>
                            <div><dt>Bounce rate</dt><dd>{{ $analytics['bounce_rate'] }}</dd></div>
                        </dl>
                    </div>

                    <div class="artist-dashboard__ranked">
                        <div class="artist-dashboard__minor-head"><span>Most viewed content</span><span>Views / actions</span></div>
                        @if ($analytics['content_state'] === 'available')
                            <ol>
                                @foreach ($analytics['content'] as $row)
                                    <li>
                                        <span class="artist-dashboard__rank">{{ $loop->iteration }}</span>
                                        <span title="{{ $row['label'] }}">{{ $row['label'] }}</span>
                                        <strong>{{ number_format($row['value']) }}</strong>
                                    </li>
                                @endforeach
                            </ol>
                        @elseif ($analytics['content_state'] === 'empty')
                            <p class="artist-dashboard__quiet">No viewed content recorded in this period.</p>
                        @else
                            <p class="artist-dashboard__quiet">Content-level analytics is unavailable.</p>
                        @endif
                    </div>
                </div>

                @if ($analytics['status'] === 'stale' && $analytics['message'])
                    <p class="artist-dashboard__data-note">{{ $analytics['message'] }}</p>
                @endif
            @else
                <div class="artist-dashboard__unavailable">
                    <span class="artist-dashboard__availability is-unavailable">{{ $analytics['status_label'] }}</span>
                    <strong>Traffic data is not available.</strong>
                    <p>{{ $analytics['message'] }}</p>
                    <a class="artist-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Open Analytics</a>
                </div>
            @endif
        </section>

        <section class="artist-dashboard__status" aria-label="Editorial publication state">
            @foreach ($editorialStatus as $status)
                <div class="is-{{ $status['tone'] }}">
                    <strong>{{ number_format($status['value']) }}</strong>
                    <span>{{ $status['label'] }}</span>
                </div>
            @endforeach
        </section>

        <div class="artist-dashboard__lower-grid">
            <section class="artist-dashboard__activity" aria-label="Recent editorial activity">
                <div class="artist-dashboard__section-head">
                    <span>Recent activity</span>
                    <a class="artist-action" href="{{ $activityUrl }}">All activity</a>
                </div>

                <div class="artist-dashboard__timeline">
                    @forelse ($activity as $event)
                        <div class="artist-dashboard__activity-row">
                            <span class="artist-dashboard__timeline-mark" aria-hidden="true"></span>
                            <span class="artist-dashboard__activity-copy">
                                <strong>{{ $event['action'] }}</strong>
                                <small>{{ $event['area'] }} · {{ $event['target'] }}</small>
                            </span>
                            <time title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time>
                        </div>
                    @empty
                        <p class="artist-dashboard__quiet">No editorial activity recorded yet.</p>
                    @endforelse
                </div>
            </section>

            <aside class="artist-dashboard__health" aria-label="Website health and attention">
                <section class="artist-dashboard__attention" aria-label="Needs attention">
                    <div class="artist-dashboard__section-head"><span>Needs attention</span></div>
                    @forelse ($attention as $item)
                        @if ($item['url'])
                            <a class="artist-dashboard__notice" href="{{ $item['url'] }}">
                                <span>
                                    <strong>{{ $item['label'] }}</strong>
                                    @if ($item['detail'])<small>{{ $item['detail'] }}</small>@endif
                                </span>
                                @if ($item['value'] !== null)
                                    <b>{{ number_format($item['value']) }}{{ $item['value_suffix'] ?? '' }}</b>
                                @endif
                            </a>
                        @else
                            <div class="artist-dashboard__notice">
                                <span>
                                    <strong>{{ $item['label'] }}</strong>
                                    @if ($item['detail'])<small>{{ $item['detail'] }}</small>@endif
                                </span>
                                @if ($item['value'] !== null)
                                    <b>{{ number_format($item['value']) }}{{ $item['value_suffix'] ?? '' }}</b>
                                @endif
                            </div>
                        @endif
                    @empty
                        <p class="artist-dashboard__quiet">No current content or integration warnings detected.</p>
                    @endforelse
                </section>

                <section class="artist-dashboard__storage" aria-label="Storage headroom">
                    <div class="artist-dashboard__minor-head"><span>Storage headroom</span><a class="artist-action" href="{{ $storage['url'] }}">Storage</a></div>
                    <div class="artist-dashboard__storage-row">
                        <span>
                            <strong>{{ $storage['label'] }}</strong>
                            @if ($storage['remaining'] !== null)<small>{{ $storage['remaining'] }} remaining</small>@endif
                            @if ($storage['detail'])<small>{{ $storage['detail'] }}</small>@endif
                        </span>
                        @if ($storage['percent'] !== null)<b>{{ $storage['percent'] }}%</b>@endif
                    </div>
                </section>
            </aside>
        </div>
    </div>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
