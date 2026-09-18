@if ($artwork)
    <x-artwork-card
        :artwork="$artwork"
        :media="$media"
        :show-details="$showDetails"
        :show-category-link="$showGalleryLink"
        :eager="true"
    />
@else
    <p class="public-empty-state">No artwork is available for the homepage yet.</p>
@endif