@php
    $selectedArtwork = $selectedArtworkAnalyticsKey === null
        ? null
        : collect($artworkAttention)->firstWhere('analytics_key', $selectedArtworkAnalyticsKey);
@endphp

<div class="analytics-table-wrap">
    <h4>Artwork attention</h4>
    @if ($artworkAttention !== [])
        <table class="analytics-table">
            <thead>
                <tr>
                    <th>Artwork</th>
                    <th>Detail</th>
                    <th>Viewer</th>
                    <th>Zoom</th>
                    <th>Navigation</th>
                    <th>Active time</th>
                    <th>Average</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($artworkAttention as $row)
                    <tr>
                        <td>
                            <div>
                                @if ($row['thumbnail_url'])
                                    <img src="{{ $row['thumbnail_url'] }}" alt="" width="54" loading="lazy" decoding="async">
                                @endif
                                <a href="#artwork-attention-detail" wire:click.prevent="selectArtwork('{{ $row['analytics_key'] }}')">
                                    <strong>{{ $row['title'] }}</strong>
                                </a>
                                <small>{{ $row['category'] }} · {{ ucfirst($row['state']) }}</small>
                            </div>
                        </td>
                        <td>{{ number_format((int) $row['detail_views']) }}</td>
                        <td>{{ number_format((int) $row['viewer_opens']) }}</td>
                        <td>{{ number_format((int) $row['zooms']) }}</td>
                        <td>{{ number_format((int) $row['navigation']) }}</td>
                        <td>{{ $row['attention_label'] }}</td>
                        <td>{{ $row['average_attention_label'] }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p class="analytics-empty analytics-empty--section">
            No stable per-artwork interaction data is available for this period yet. Detail views, viewer opens, zooms and meaningful active viewing time will appear here after tracked public activity exists.
        </p>
    @endif
</div>

@if ($selectedArtwork !== null)
    @php
        $trend = $selectedArtwork['trend'] ?? [];
        $trendMax = max(1.0, ...array_map(static fn (array $day): float => (float) ($day['attention_seconds'] ?? 0), $trend));
    @endphp
    <div id="artwork-attention-detail" class="analytics-time-grid">
        <div>
            <div class="analytics-section__head">
                <div>
                    <p class="analytics-kicker">Selected work</p>
                    <h4>{{ $selectedArtwork['title'] }}</h4>
                </div>
                <button type="button" wire:click="clearArtworkSelection">Close detail</button>
            </div>

            <div class="analytics-interaction-strip">
                <div><span>Detail views</span><strong>{{ number_format((int) $selectedArtwork['detail_views']) }}</strong></div>
                <div><span>Viewer opens</span><strong>{{ number_format((int) $selectedArtwork['viewer_opens']) }}</strong></div>
                <div><span>Zooms</span><strong>{{ number_format((int) $selectedArtwork['zooms']) }}</strong></div>
                <div><span>Active time</span><strong>{{ $selectedArtwork['attention_label'] }}</strong></div>
                <div><span>Average active view</span><strong>{{ $selectedArtwork['average_attention_label'] }}</strong></div>
            </div>

            <div class="analytics-context">
                <a href="{{ \App\Filament\Resources\Artworks\ArtworkResource::getUrl('edit', ['record' => $selectedArtwork['id']]) }}">Edit artwork</a>
                @if ($selectedArtwork['public_url'])
                    <a href="{{ $selectedArtwork['public_url'] }}" target="_blank" rel="noopener">View on site</a>
                @endif
            </div>
        </div>

        <div>
            <h4>Active attention trend</h4>
            @forelse ($trend as $day)
                <div class="analytics-rank-row is-compact">
                    <span>{{ $day['date'] }}</span>
                    <i><b style="width: {{ number_format(min(100, ((float) $day['attention_seconds'] / $trendMax) * 100), 2, '.', '') }}%"></b></i>
                    <strong>{{ $day['attention_label'] }}</strong>
                </div>
            @empty
                <p class="analytics-empty">No daily active-attention samples in this period.</p>
            @endforelse
        </div>
    </div>
@endif
