{{-- Canonical ranked Admin table: Position, Drag, optional Media, Artwork,
     List details or Dense date/data, Actions and terminal Selection. --}}
<x-admin.table class="admin-table--data admin-table--ranked media-workspace__table-wrap gallery-workspace__table-wrap"
    data-admin-view-panel="table" x-bind:class="{ 'is-dense': viewMode === 'dense' }">
    <table class="admin-table--six-grid media-workspace__table gallery-workspace__table">
        <colgroup>
            <col class="admin-table__col-quarter-unit admin-table__col-position">
            <col class="admin-table__col-quarter-unit admin-table__col-drag">
            <col class="admin-table__col-half-unit gallery-workspace__col-media admin-view-list-only">
            <col class="gallery-workspace__col-artwork">
            <col class="gallery-workspace__col-details admin-table__yield--compact admin-view-list-only">
            <col class="gallery-workspace__col-date admin-table__yield--minimal admin-view-dense-only">
            <col class="gallery-workspace__col-dimensions admin-table__yield--narrow admin-view-dense-only">
            <col class="gallery-workspace__col-material admin-table__yield--compact admin-view-dense-only">
            <col class="gallery-workspace__col-analytics gallery-workspace__analytics-column">
            <col class="gallery-workspace__col-actions">
            <col class="admin-table__selection-col">
        </colgroup>
        <thead>
            <tr>
                <th scope="colgroup" colspan="2" class="admin-table__ordering-heading">{{ __('Position') }}</th>
                <th scope="col" class="gallery-workspace__media-head admin-view-list-only">{{ __('Media') }}</th>
                <th scope="col">{{ __('Artwork') }}</th>
                <th scope="col" class="admin-table__yield--compact admin-view-list-only">{{ __('Details') }}</th>
                <th scope="col" class="admin-table__yield--minimal admin-view-dense-only">{{ __('Date') }}</th>
                <th scope="col" class="admin-table__yield--narrow admin-view-dense-only">{{ __('Dimensions') }}</th>
                <th scope="col" class="admin-table__yield--compact admin-view-dense-only">{{ __('Material') }}</th>
                <th scope="col" class="gallery-workspace__analytics-column">{{ __('30d analytics') }}</th>
                <th scope="col" class="admin-table__actions"><span class="admin-row-actions-heading">{{ __('Actions') }}</span></th>
                <th scope="col" class="admin-table__selection admin-table__selection--trailing">
                    <input type="checkbox" x-data="{}" wire:click.prevent="toggleVisibleArtworkSelection"
                        x-effect="
                            const visibleIds = @js(array_column($artworks, 'id')).map(Number);
                            const selectedIds = $wire.selectedArtworkIds.map(Number);
                            const selectedCount = visibleIds.filter(id => selectedIds.includes(id)).length;
                            $el.checked = visibleIds.length > 0 && selectedCount === visibleIds.length;
                            $el.indeterminate = selectedCount > 0 && selectedCount < visibleIds.length;
                            $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                        "
                        @disabled($artworks === [])
                        aria-label="Toggle selection for visible artworks">
                </th>
            </tr>
        </thead>
        <tbody @if ($reorderEnabled) wire:sort="sortArtwork" @endif>
            @foreach ($artworks as $artwork)
                @php($selected = in_array((int) $artwork['id'], array_map('intval', $selectedArtworkIds), true))
                @php($analyticsAvailable = (bool) $artwork['analytics']['available'])
                @php($analyticsTop = $analyticsAvailable
                    ? number_format($artwork['analytics']['views']).' views, '.number_format($artwork['analytics']['opens']).' opens'
                    : 'Unavailable')
                @php($analyticsBottom = $analyticsAvailable
                    ? number_format($artwork['analytics']['zooms']).' zooms, '.$artwork['analytics']['attention'].' attention'
                    : '')
                @php($analyticsFull = $analyticsAvailable ? $analyticsTop.', '.$analyticsBottom : $analyticsTop)
                <tr class="{{ $selected ? 'is-selected' : '' }}" wire:key="gallery-table-artwork-{{ $artwork['id'] }}"
                    @if ($reorderEnabled) wire:sort:item="{{ $artwork['id'] }}" @endif>
                    <td class="admin-table__position">
                        <span class="admin-position">{{ $artwork['sequence'] }}</span>
                    </td>
                    <td class="admin-table__drag">
                        <button class="admin-drag-handle" type="button"
                            @if ($reorderEnabled) wire:sort:handle title="Drag to reorder" @else disabled title="{{ $reorderDisabledReason }}" @endif
                            aria-label="Drag {{ $artwork['title'] }} to reorder">⋮⋮</button>
                    </td>
                        <td class="media-workspace__thumb gallery-workspace__media-cell admin-view-list-only">
                            <button class="gallery-workspace__media-preview" type="button"
                                wire:click="mountAction('previewArtwork', { artwork: {{ $artwork['id'] }} })"
                                title="Preview {{ $artwork['title'] }}" aria-label="Preview {{ $artwork['title'] }}">
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                @else
                                    <span aria-label="No preview image">—</span>
                                @endif
                            </button>
                        </td>
                    <td class="admin-table__identity admin-table__identity--ellipsis-meta gallery-workspace__table-identity">
                        <x-admin.artwork-title-editor :artwork="$artwork" />
                        @if ($artwork['date'])
                            <small class="gallery-workspace__table-date admin-view-list-only">{{ $artwork['date'] }}</small>
                        @endif
                        <small class="gallery-workspace__folded-meta">
                            @if ($artwork['dimensions'] || $artwork['medium'])
                                <span class="gallery-workspace__folded-details admin-view-list-only">{{ implode(', ', array_filter([$artwork['dimensions'], $artwork['medium']])) }}</span>
                            @endif
                            @if ($artwork['date'])<span class="gallery-workspace__folded-date admin-view-dense-only">{{ $artwork['date'] }}</span>@endif
                            @if ($artwork['dimensions'])<span class="gallery-workspace__folded-dimensions admin-view-dense-only">{{ $artwork['dimensions'] }}</span>@endif
                            @if ($artwork['medium'])<span class="gallery-workspace__folded-material admin-view-dense-only">{{ $artwork['medium'] }}</span>@endif
                        </small>
                    </td>
                    <td class="gallery-workspace__table-details admin-table__yield--compact admin-view-list-only" title="{{ implode(', ', array_filter([$artwork['dimensions'], $artwork['medium']])) }}">
                        <span>{{ $artwork['dimensions'] ?: '—' }}</span>
                        <small>{{ $artwork['medium'] ?: '—' }}</small>
                    </td>
                    <td class="gallery-workspace__table-date-cell admin-table__yield--minimal admin-view-dense-only">{{ $artwork['date'] ?: '—' }}</td>
                    <td class="gallery-workspace__table-dimensions admin-table__yield--narrow admin-view-dense-only" title="{{ $artwork['dimensions'] }}">{{ $artwork['dimensions'] ?: '—' }}</td>
                    <td class="gallery-workspace__table-material admin-table__yield--compact admin-view-dense-only" title="{{ $artwork['medium'] }}">{{ $artwork['medium'] ?: '—' }}</td>
                    <td class="gallery-workspace__table-analytics gallery-workspace__analytics-column" title="{{ $analyticsFull }}">
                        <span>{{ $analyticsTop }}</span>
                        @if ($analyticsAvailable)
                            <small class="admin-view-list-only">{{ $analyticsBottom }}</small>
                            <span class="gallery-workspace__analytics-dense-suffix admin-view-dense-only">, {{ $analyticsBottom }}</span>
                        @endif
                    </td>
                    <td class="media-workspace__actions admin-table__actions">
                        <x-admin.toolbar class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--six-static admin-row-actions--six-static--gallery">
                            <x-admin.row-action action="edit" wire:click="mountAction('editArtwork', { artwork: {{ $artwork['id'] }} })" />
                            <x-admin.row-action action="move-up" wire:click="moveArtwork({{ $artwork['id'] }}, 'up')"
                                :disabled="! $reorderEnabled || ! $artwork['can_move_up']"
                                label="Move up" title="{{ $reorderEnabled ? 'Move up' : $reorderDisabledReason }}" />
                            <x-admin.row-action action="move-down" wire:click="moveArtwork({{ $artwork['id'] }}, 'down')"
                                :disabled="! $reorderEnabled || ! $artwork['can_move_down']"
                                label="Move down" title="{{ $reorderEnabled ? 'Move down' : $reorderDisabledReason }}" />
                            @if ($artwork['state'] === 'published')
                                <x-admin.row-action action="unpublish" wire:click="mountAction('unpublishArtwork', { artwork: {{ $artwork['id'] }} })" />
                            @else
                                <x-admin.row-action action="publish" wire:click="mountAction('publishArtwork', { artwork: {{ $artwork['id'] }} })" />
                            @endif
                            <x-admin.row-action action="move-to-gallery"
                                wire:click="mountAction('moveArtworkToGallery', { artwork: {{ $artwork['id'] }} })"
                                :disabled="$moveTargets === [] || ($artwork['state'] === 'published' && ! $hasPublishedMoveTarget)"
                                label="Move" title="Move to Gallery" />
                            <x-admin.row-action action="delete" wire:click="mountAction('deleteArtwork', { artwork: {{ $artwork['id'] }} })"
                                label="Delete" title="Delete Artwork (optionally with its Storage file)" />
                        </x-admin.toolbar>
                    </td>
                    <td class="admin-table__selection admin-table__selection--trailing">
                        <input type="checkbox" wire:click="toggleArtworkSelection({{ $artwork['id'] }})"
                            @checked($selected) aria-label="Toggle selection for {{ $artwork['title'] }}">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-admin.table>
