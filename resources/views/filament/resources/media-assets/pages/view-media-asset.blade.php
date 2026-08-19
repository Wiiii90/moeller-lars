<x-filament-panels::page>
    <div class="artist-workspace">
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

        <section aria-label="Media preview">
            <div class="artist-contact-sheet__image">
                @if ($media['original_url'])
                    <img src="{{ $media['original_url'] }}" alt="{{ $media['alt'] }}">
                @else
                    <span>Preview unavailable for {{ $media['state'] }} media</span>
                @endif
            </div>

            @if ($sequence)
                <nav class="artist-workspace__footnote" aria-label="Artwork image sequence">
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

        <section class="artist-storage__breakdown" aria-label="Media details">
            <div>
                <span>Metadata</span>
                <div class="artist-storage__numbers">
                    <dl>
                        <div><dt>State</dt><dd>{{ ucfirst($media['state']) }}</dd></div>
                        <div><dt>Dimensions</dt><dd>{{ $media['dimensions'] }}</dd></div>
                        <div><dt>File size</dt><dd>{{ $media['size'] }}</dd></div>
                        <div><dt>ALT text</dt><dd>{{ $media['alt_label'] }}</dd></div>
                        <div><dt>Credit</dt><dd>{{ $media['credit'] }}</dd></div>
                        <div><dt>Copyright</dt><dd>{{ $media['copyright'] }}</dd></div>
                    </dl>
                </div>
            </div>

            <div>
                <span>Used by</span>
                @if ($usages !== [])
                    <nav class="artist-gallery-tools" aria-label="Media usage links">
                        @foreach ($usages as $usage)
                            <a class="artist-action" href="{{ $usage['url'] }}">{{ $usage['type'] }} · {{ $usage['label'] }}</a>
                        @endforeach
                    </nav>
                @else
                    <small>This asset is currently unused and may be reviewed for deletion.</small>
                @endif
            </div>
        </section>

        @if ($sequence)
            <footer class="artist-workspace__footnote">
                <a class="artist-action" href="{{ $sequence['artwork_url'] }}">Edit artwork</a>
                @if ($sequence['gallery_url'])
                    <a class="artist-action" href="{{ $sequence['gallery_url'] }}">Back to Gallery workspace</a>
                @endif
                <span>Primary and additional images share one inspection sequence; ordering remains managed from the Artwork editor.</span>
            </footer>
        @endif
    </div>
</x-filament-panels::page>
