<x-filament-panels::page>
    <div class="artist-workspace artist-media-viewer">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Media inspection</p>
                <h2>{{ $media['filename'] }}</h2>
                <p>{{ $media['dimensions'] }} · {{ $media['size'] }} · {{ $media['type'] }}</p>
            </div>
            @if ($sequence)
                <div class="artist-workspace__summary">
                    <div><strong>{{ $sequence['position_label'] }}</strong><span>Artwork images</span></div>
                    <div><strong>{{ $sequence['role_label'] }}</strong><span>{{ $sequence['artwork_title'] }}</span></div>
                </div>
            @endif
        </header>

        <section class="artist-media-viewer__stage" aria-label="Media preview">
            @if ($media['original_url'])
                <img src="{{ $media['original_url'] }}" alt="{{ $media['alt'] }}">
            @else
                <p>Preview unavailable for {{ $media['state'] }} media.</p>
            @endif

            @if ($sequence)
                <nav class="artist-media-viewer__sequence" aria-label="Artwork image sequence">
                    @if ($sequence['previous_url'])
                        <a class="artist-action" href="{{ $sequence['previous_url'] }}">← Previous</a>
                    @else
                        <span class="artist-action" aria-disabled="true">← Previous</span>
                    @endif
                    <span>{{ $sequence['position_label'] }} · {{ $sequence['role_label'] }}</span>
                    @if ($sequence['next_url'])
                        <a class="artist-action" href="{{ $sequence['next_url'] }}">Next →</a>
                    @else
                        <span class="artist-action" aria-disabled="true">Next →</span>
                    @endif
                </nav>
            @endif
        </section>

        <div class="artist-media-viewer__details">
            <section aria-label="Media metadata">
                <p class="artist-workspace__kicker">Metadata</p>
                <dl class="artist-media-viewer__facts">
                    <div><dt>State</dt><dd>{{ ucfirst($media['state']) }}</dd></div>
                    <div><dt>Dimensions</dt><dd>{{ $media['dimensions'] }}</dd></div>
                    <div><dt>File size</dt><dd>{{ $media['size'] }}</dd></div>
                    <div><dt>ALT text</dt><dd>{{ $media['alt_label'] }}</dd></div>
                    <div><dt>Credit</dt><dd>{{ $media['credit'] }}</dd></div>
                    <div><dt>Copyright</dt><dd>{{ $media['copyright'] }}</dd></div>
                </dl>
            </section>

            <section aria-label="Media usage">
                <p class="artist-workspace__kicker">Used by</p>
                @if ($usages !== [])
                    <div class="artist-media-viewer__usages">
                        @foreach ($usages as $usage)
                            <a href="{{ $usage['url'] }}">
                                <span>{{ $usage['type'] }}</span>
                                <strong>{{ $usage['label'] }}</strong>
                            </a>
                        @endforeach
                    </div>
                @else
                    <p class="artist-media-viewer__unused">This asset is currently unused and may be reviewed for deletion.</p>
                @endif
            </section>
        </div>

        @if ($sequence)
            <footer class="artist-workspace__footnote">
                <a class="artist-action" href="{{ $sequence['artwork_url'] }}">Edit artwork</a>
                @if ($sequence['gallery_url'])<a class="artist-action" href="{{ $sequence['gallery_url'] }}">Back to Gallery workspace</a>@endif
                <span>Primary and additional images share one inspection sequence; ordering remains managed from the Artwork editor.</span>
            </footer>
        @endif
    </div>
</x-filament-panels::page>
