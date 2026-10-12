@if ($artwork)
    <x-artwork-card
        :artwork="$artwork"
        :media="$media"
        :show-details="$showDetails"
        :show-category-link="$showGalleryLink"
        :eager="true"
    />
@endif