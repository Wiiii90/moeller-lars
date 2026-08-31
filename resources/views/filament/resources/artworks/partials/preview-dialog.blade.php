<div class="media-file-dialog__content gallery-artwork-preview">
    <div class="media-file-dialog__preview">
        @if ($primaryMedia !== null && $primaryMedia['kind'] === 'image')
            <img
                src="{{ $primaryMedia['preview_url'] }}"
                alt="{{ $primaryMedia['alt_text'] }}"
                decoding="async"
            >
        @elseif ($primaryMedia !== null && $primaryMedia['kind'] === 'video')
            <video controls playsinline preload="metadata">
                <source src="{{ $primaryMedia['preview_url'] }}" type="{{ $primaryMedia['mime'] }}">
            </video>
        @else
            <p>Primary media is not available for preview.</p>
        @endif
    </div>

    <nav class="media-file-dialog__sequence" aria-label="Artwork preview result navigation">
        <button
            class="admin-action"
            type="button"
            @if ($previousId !== null)
                wire:click="replaceMountedAction('previewArtwork', { artwork: {{ $previousId }} })"
            @else
                disabled
            @endif
        >Previous</button>
        <span>
            @if ($resultPosition !== null)
                {{ $resultPosition }} of {{ $resultTotal }} filtered artworks
            @else
                Current artwork
            @endif
        </span>
        <button
            class="admin-action"
            type="button"
            @if ($nextId !== null)
                wire:click="replaceMountedAction('previewArtwork', { artwork: {{ $nextId }} })"
            @else
                disabled
            @endif
        >Next</button>
    </nav>

    <div class="media-file-dialog__details media-file-dialog__metadata">
        <section aria-labelledby="gallery-artwork-preview-details-{{ $artwork['id'] }}">
            <h3 id="gallery-artwork-preview-details-{{ $artwork['id'] }}">Artwork</h3>
            <dl class="media-file-dialog__metadata-grid">
                <div><dt>Title</dt><dd>{{ $artwork['title'] }}</dd></div>
                <div><dt>Material</dt><dd>{{ $artwork['medium'] !== '' ? $artwork['medium'] : '—' }}</dd></div>
                <div><dt>Dimensions</dt><dd>{{ $artwork['dimensions'] !== '' ? $artwork['dimensions'] : '—' }}</dd></div>
                <div><dt>Year</dt><dd>{{ $artwork['year'] ?: '—' }}</dd></div>
                <div><dt>Status</dt><dd>{{ $artwork['state_label'] }}</dd></div>
                <div><dt>Readiness</dt><dd>{{ $artwork['readiness_label'] }}</dd></div>
            </dl>
        </section>
    </div>

    <div class="media-file-dialog__details media-file-dialog__metadata">
        <section aria-labelledby="gallery-artwork-preview-media-{{ $artwork['id'] }}">
            <h3 id="gallery-artwork-preview-media-{{ $artwork['id'] }}">Primary Media</h3>
            @if ($primaryMedia !== null)
                <dl class="media-file-dialog__metadata-grid">
                    <div><dt>File</dt><dd>{{ $primaryMedia['filename'] }}</dd></div>
                    <div><dt>Type</dt><dd>{{ $primaryMedia['type_label'] }}</dd></div>
                    <div><dt>Dimensions</dt><dd>{{ $primaryMedia['dimensions'] }}</dd></div>
                    <div><dt>ALT text</dt><dd>{{ $primaryMedia['alt_text'] !== '' ? $primaryMedia['alt_text'] : '—' }}</dd></div>
                </dl>
            @else
                <p class="media-file-dialog__empty">No available primary Media File.</p>
            @endif
        </section>
    </div>
</div>
