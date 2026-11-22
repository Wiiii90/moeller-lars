    <div class="artwork-list{{ $divider !== 'none' ? ' artwork-list--divided' : '' }}"
        data-artwork-viewer-sequence data-gallery-infinite
        data-gallery-viewer-manifest="{{ isset($galleryUrl) ? $galleryUrl.'?gallery_viewer=1' : request()->fullUrlWithQuery(['gallery_viewer' => '1']) }}">
        @include('pages.artworks.partials.batch')
    </div>
    @if ($hasMore)
        <a class="gallery-load-more" data-gallery-load-more
            href="{{ isset($galleryUrl) ? $galleryUrl.'?gallery_page='.($page + 1) : request()->fullUrlWithQuery(['gallery_page' => $page + 1]) }}">
            <span class="gallery-load-more__label" data-gallery-load-more-label aria-live="polite">Load more artworks</span>
            <span class="gallery-load-more__dots" data-gallery-load-more-dots aria-hidden="true" hidden>
                <span></span><span></span><span></span>
            </span>
        </a>
    @endif
