@if (filled($get('favicon_media_asset_id')))
    <div class="admin-toolbar admin-favicon-control__actions">
        <button class="admin-action is-danger" type="button" wire:click="removeFavicon">Remove</button>
    </div>
@endif
