<x-filament-panels::page>
    <x-admin.workspace :title="$media['filename']" class="media-inspector">
        <x-slot:summary>
            <div><strong>{{ $media['usage_count'] }}</strong><span>References</span></div>
            <div><strong>{{ ucfirst($media['state']) }}</strong><span>Status</span></div>
        </x-slot:summary>

        <section class="media-inspector__preview" aria-label="Media preview">
            @if ($media['original_url'])
                @if ($media['is_video'])
                    <video controls playsinline preload="metadata">
                        <source src="{{ $media['original_url'] }}" type="{{ $media['mime'] }}">
                        Your browser cannot preview this video format.
                    </video>
                @elseif ($media['is_image'])
                    <img src="{{ $media['original_url'] }}" alt="{{ $media['alt'] }}">
                @else
                    <a class="admin-action" href="{{ $media['original_url'] }}">Open media</a>
                @endif
            @else
                <span>Preview unavailable for {{ $media['state'] }} media</span>
            @endif
        </section>

        @if ($sequence)
            <nav class="media-inspector__sequence" aria-label="Artwork media sequence">
                @if ($sequence['previous_url'])<a class="admin-action" href="{{ $sequence['previous_url'] }}">← Previous</a>@else<span class="admin-action" aria-disabled="true">← Previous</span>@endif
                <span>{{ $sequence['position_label'] }} · {{ $sequence['role_label'] }} · {{ $sequence['artwork_title'] }}</span>
                @if ($sequence['next_url'])<a class="admin-action" href="{{ $sequence['next_url'] }}">Next →</a>@else<span class="admin-action" aria-disabled="true">Next →</span>@endif
            </nav>
        @endif

        <section class="media-inspector__details" aria-label="Media details and usage">
            <div>
                <span class="media-inspector__label">Editorial metadata</span>
                <dl>
                    <div><dt>Type</dt><dd>{{ $media['type'] }}</dd></div>
                    <div><dt>Dimensions</dt><dd>{{ $media['dimensions'] }}</dd></div>
                    <div><dt>File size</dt><dd>{{ $media['size'] }}</dd></div>
                    @if($media['is_image'])<div><dt>ALT text</dt><dd>{{ $media['alt_label'] }}</dd></div>@endif
                    <div><dt>Credit</dt><dd>{{ $media['credit'] }}</dd></div>
                    <div><dt>Copyright</dt><dd>{{ $media['copyright'] }}</dd></div>
                </dl>
            </div>

            <div>
                <span class="media-inspector__label">Used in</span>
                @if ($usages !== [])
                    <div class="media-inspector__usage-list">
                        @foreach ($usages as $usage)
                            @if ($usage['url'])
                                <a href="{{ $usage['url'] }}"><strong>{{ $usage['type'] }}</strong><span>{{ $usage['label'] }}</span></a>
                            @else
                                <div><strong>{{ $usage['type'] }}</strong><span>{{ $usage['label'] }}</span></div>
                            @endif
                        @endforeach
                    </div>
                    @if(count($usages) > 1)<small>Shared asset: deleting the media remains blocked until every reference has been detached explicitly.</small>@endif
                @else
                    <small>This asset is unreferenced. Deleting it is still a separate explicit action from detaching content.</small>
                @endif
            </div>
        </section>

        @if ($sequence)
            <footer class="admin-workspace__footnote">
                <x-admin.toolbar>
                    <a class="admin-action" href="{{ $sequence['artwork_url'] }}">Edit artwork</a>
                    @if ($sequence['gallery_url'])<a class="admin-action" href="{{ $sequence['gallery_url'] }}">Back to Gallery workspace</a>@endif
                </x-admin.toolbar>
            </footer>
        @endif
    </x-admin.workspace>
</x-filament-panels::page>
