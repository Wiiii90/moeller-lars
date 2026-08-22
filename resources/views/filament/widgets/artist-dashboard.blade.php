<x-filament-widgets::widget>
    <x-admin.workspace kicker="Editorial overview" title="Website at a glance" class="admin-dashboard">
        <x-slot:actions>
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
        </x-slot:actions>

        <div class="admin-dashboard__layout">
            <x-admin.section kicker="Traffic" title="Traffic & engagement" class="admin-dashboard__section">
                @if (in_array($analytics['status'], ['available', 'stale'], true))
                    <x-admin.list>
                        <div class="admin-list__row">
                            <div class="admin-list__identity">
                                <strong>{{ $analytics['visits_display'] }} visits</strong>
                                <span>
                                    {{ $analytics['visitors_display'] }} visitors
                                    @if ($analytics['visits_delta'] !== null)
                                        · {{ $analytics['visits_delta'] }} vs previous period
                                    @endif
                                    · {{ $analytics['status_label'] }}
                                </span>
                            </div>
                            <div class="admin-list__meta"><span>{{ $analytics['range'] }}</span></div>
                            <div></div>
                            <x-admin.toolbar><a class="admin-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Analytics</a></x-admin.toolbar>
                        </div>
                    </x-admin.list>

                    @if ($analytics['trend'] !== [])
                        <div class="admin-dashboard__trend">
                            <div><strong>Visit trend</strong><small>{{ $analytics['trend']['start'] }} → {{ $analytics['trend']['end'] }}</small></div>
                            <svg width="260" height="58" viewBox="0 0 680 150" role="img" aria-label="Visit trend for the last 30 days" preserveAspectRatio="none">
                                <line x1="4" y1="146" x2="676" y2="146" stroke="currentColor" opacity="0.18" />
                                <polyline points="{{ $analytics['trend']['points'] }}" fill="none" stroke="currentColor" stroke-width="5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                        </div>
                    @endif

                    <x-admin.metrics :columns="3" aria-label="Engagement metrics">
                        <x-admin.metric label="Actions / visit" :value="$analytics['actions_per_visit']" />
                        <x-admin.metric label="Average visit" :value="$analytics['average_visit']" />
                        <x-admin.metric label="Bounce rate" :value="$analytics['bounce_rate']" />
                    </x-admin.metrics>

                    @if ($analytics['status'] === 'stale' && $analytics['message'])
                        <p class="admin-workspace__footnote">{{ $analytics['message'] }}</p>
                    @endif
                @else
                    <x-admin.empty-state kicker="Analytics unavailable" title="Traffic data is not available">
                        <p>{{ $analytics['message'] }}</p>
                        <x-slot:actions><a class="admin-action" href="{{ \App\Filament\Pages\Analytics::getUrl() }}">Open Analytics</a></x-slot:actions>
                    </x-admin.empty-state>
                @endif
            </x-admin.section>

            <x-admin.section kicker="Attention" title="Most viewed content" class="admin-dashboard__section">
                @if ($analytics['content_state'] === 'available')
                    <x-admin.list>
                        @foreach ($analytics['content'] as $row)
                            <div class="admin-list__row admin-list__row--compact">
                                <div class="admin-list__identity"><strong>{{ $row['label'] }}</strong></div>
                                <div></div>
                                <div class="admin-list__count"><strong>{{ number_format($row['value']) }}</strong><span>Views</span></div>
                                <div></div>
                            </div>
                        @endforeach
                    </x-admin.list>
                @elseif ($analytics['content_state'] === 'empty')
                    <x-admin.empty-state kicker="No data" title="No viewed content recorded" />
                @else
                    <x-admin.empty-state kicker="Unavailable" title="Content-level analytics is unavailable" />
                @endif
            </x-admin.section>
        </div>

        <x-admin.section kicker="Publication" title="Current content state">
            <x-admin.metrics :columns="4">
                @foreach ($editorialStatus as $status)
                    <x-admin.metric :label="$status['label']" :value="number_format($status['value'])" />
                @endforeach
            </x-admin.metrics>
        </x-admin.section>

        <div class="admin-dashboard__layout">
            <x-admin.section kicker="History" title="Recent activity" class="admin-dashboard__section">
                <x-slot:actions><a class="admin-action" href="{{ $activityUrl }}">All activity</a></x-slot:actions>
                @if ($activity !== [])
                    <x-admin.list>
                        @foreach ($activity as $event)
                            <div class="admin-list__row admin-list__row--compact">
                                <div class="admin-list__identity"><strong>{{ $event['action'] }}</strong><span>{{ $event['area'] }} · {{ $event['target'] }}</span></div>
                                <div class="admin-list__meta"><time title="{{ $event['timestamp'] }}">{{ $event['when'] }}</time></div>
                                <div></div><div></div>
                            </div>
                        @endforeach
                    </x-admin.list>
                @else
                    <x-admin.empty-state kicker="No history" title="No editorial activity recorded yet" />
                @endif
            </x-admin.section>

            <aside class="admin-dashboard__side" aria-label="Website health and attention">
                <x-admin.section kicker="Health" title="Needs attention" class="admin-dashboard__section">
                    @if ($attention !== [])
                        <x-admin.list>
                            @foreach ($attention as $item)
                                @if ($item['url'])
                                    <a class="admin-list__row admin-list__row--compact" href="{{ $item['url'] }}">
                                        <div class="admin-list__identity"><strong>{{ $item['label'] }}</strong>@if ($item['detail'])<span>{{ $item['detail'] }}</span>@endif</div>
                                        <div></div>
                                        <div class="admin-list__count">@if ($item['value'] !== null)<strong>{{ number_format($item['value']) }}{{ $item['value_suffix'] ?? '' }}</strong>@endif</div>
                                        <div></div>
                                    </a>
                                @else
                                    <div class="admin-list__row admin-list__row--compact">
                                        <div class="admin-list__identity"><strong>{{ $item['label'] }}</strong>@if ($item['detail'])<span>{{ $item['detail'] }}</span>@endif</div>
                                        <div></div>
                                        <div class="admin-list__count">@if ($item['value'] !== null)<strong>{{ number_format($item['value']) }}{{ $item['value_suffix'] ?? '' }}</strong>@endif</div>
                                        <div></div>
                                    </div>
                                @endif
                            @endforeach
                        </x-admin.list>
                    @else
                        <x-admin.empty-state kicker="Healthy" title="No current content or integration warnings" />
                    @endif
                </x-admin.section>

                <x-admin.section kicker="Capacity" title="Storage headroom" class="admin-dashboard__section">
                    <a class="admin-dashboard__storage" href="{{ $storage['url'] }}">
                        <span><strong>{{ $storage['label'] }}</strong>@if ($storage['remaining'] !== null)<small>{{ $storage['remaining'] }} remaining</small>@endif @if ($storage['detail'])<small>{{ $storage['detail'] }}</small>@endif</span>
                        @if ($storage['percent'] !== null)<strong>{{ $storage['percent'] }}%</strong>@endif
                    </a>
                </x-admin.section>
            </aside>
        </div>
    </x-admin.workspace>

    <x-filament-actions::modals />
</x-filament-widgets::widget>
