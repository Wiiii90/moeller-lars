<div class="media-file-dialog__content">
    <div class="media-file-dialog__preview">
        @if ($asset['preview_url'] !== null && $asset['kind'] === 'image')
            <img
                src="{{ $asset['preview_url'] }}"
                alt="{{ $asset['alt_text'] }}"
                decoding="async"
            >
        @elseif ($asset['preview_url'] !== null && $asset['kind'] === 'video')
            <video controls playsinline preload="metadata">
                <source src="{{ $asset['preview_url'] }}" type="{{ $asset['mime'] }}">
            </video>
        @else
            <p>{{ ucfirst($asset['state']) }} media is not available for preview.</p>
        @endif
    </div>

    <nav class="media-file-dialog__sequence" aria-label="Preview result navigation">
        <button
            class="admin-action"
            type="button"
            @if ($previousId !== null)
                wire:click="replaceMountedAction('preview', { asset: {{ $previousId }} })"
            @else
                disabled
            @endif
        >Previous</button>
        <span>
            @if ($resultPosition !== null)
                {{ $resultPosition }} of {{ $resultTotal }} filtered results
            @else
                Current file
            @endif
        </span>
        <button
            class="admin-action"
            type="button"
            @if ($nextId !== null)
                wire:click="replaceMountedAction('preview', { asset: {{ $nextId }} })"
            @else
                disabled
            @endif
        >Next</button>
    </nav>

    <div class="media-file-dialog__details">
        <section aria-labelledby="media-dialog-metadata-{{ $asset['id'] }}">
            <h3 id="media-dialog-metadata-{{ $asset['id'] }}">Metadata</h3>
            <dl>
                <div><dt>File</dt><dd>{{ $asset['filename'] }}</dd></div>
                <div><dt>Type</dt><dd>{{ $asset['type_label'] }} · {{ $asset['mime'] }}</dd></div>
                <div><dt>Dimensions</dt><dd>{{ $asset['dimensions'] }}</dd></div>
                <div><dt>Size</dt><dd>{{ $asset['size'] }}</dd></div>
                <div><dt>Status</dt><dd>{{ ucfirst($asset['state']) }}</dd></div>
                <div><dt>Created</dt><dd>{{ $asset['created'] }}</dd></div>
                <div><dt>Checksum</dt><dd class="media-file-dialog__checksum">{{ $asset['checksum'] }}</dd></div>
                <div><dt>ALT text</dt><dd>{{ $asset['alt_text'] !== '' ? $asset['alt_text'] : '—' }}</dd></div>
                <div><dt>Credit</dt><dd>{{ $asset['credit'] !== '' ? $asset['credit'] : '—' }}</dd></div>
                <div><dt>Copyright</dt><dd>{{ $asset['copyright_notice'] !== '' ? $asset['copyright_notice'] : '—' }}</dd></div>
            </dl>
        </section>

        <section aria-labelledby="media-dialog-usage-{{ $asset['id'] }}">
            <h3 id="media-dialog-usage-{{ $asset['id'] }}">Used in</h3>
            @if ($asset['references'] === [])
                <p class="media-file-dialog__empty">No canonical references.</p>
            @else
                <div class="media-file-dialog__references">
                    @foreach ($asset['references'] as $reference)
                        @if ($reference['url'])
                            <a href="{{ $reference['url'] }}">
                                <strong>{{ $reference['type'] }}</strong>
                                <span>{{ $reference['label'] }}</span>
                            </a>
                        @else
                            <div>
                                <strong>{{ $reference['type'] }}</strong>
                                <span>{{ $reference['label'] }}</span>
                            </div>
                        @endif
                    @endforeach
                </div>
            @endif
        </section>
    </div>
</div>
