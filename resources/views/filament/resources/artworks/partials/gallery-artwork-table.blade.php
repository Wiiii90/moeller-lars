{{-- Same table shell, role order and List/Dense density as Storage.
     Artwork actions always call the existing Gallery editorial owners. --}}
<x-admin.table class="media-workspace__table-wrap gallery-workspace__table-wrap {{ $viewMode === 'dense' ? 'is-dense' : '' }}">
    <table class="media-workspace__table gallery-workspace__table admin-table--six-grid">
        <colgroup>
            @if ($viewMode === 'list')
                <col class="gallery-workspace__col-preview">
            @endif
            <col class="gallery-workspace__col-artwork">
            <col class="gallery-workspace__col-analytics admin-table__yield--compact">
            <col class="gallery-workspace__col-status admin-table__yield--narrow">
            <col class="gallery-workspace__col-actions">
            <col class="gallery-workspace__col-selection admin-table__selection-col">
        </colgroup>
        <thead>
            <tr>
                @if ($viewMode === 'list')
                    <th scope="col" class="gallery-workspace__thumb-head">{{ __('Preview') }}</th>
                @endif
                <th scope="col">{{ __('Artwork') }}</th>
                <th scope="col" class="admin-table__yield--compact">{{ __('30d analytics') }}</th>
                <th scope="col" class="admin-table__yield--narrow">{{ __('Status') }}</th>
                <th scope="col" class="gallery-workspace__actions-head admin-table__actions">
                    <span class="admin-row-actions-heading">{{ __('Actions') }}</span>
                </th>
                <th scope="col" class="gallery-workspace__selection-head">
                    <input type="checkbox"
                        x-data="{}"
                        wire:click.prevent="toggleVisibleArtworkSelection"
                        x-effect="
                            const visibleIds = @js(array_column($artworks, 'id')).map(Number);
                            const selectedIds = $wire.selectedArtworkIds.map(Number);
                            const selectedCount = visibleIds.filter(id => selectedIds.includes(id)).length;
                            $el.checked = visibleIds.length > 0 && selectedCount === visibleIds.length;
                            $el.indeterminate = selectedCount > 0 && selectedCount < visibleIds.length;
                            $el.setAttribute('aria-checked', $el.indeterminate ? 'mixed' : ($el.checked ? 'true' : 'false'));
                        "
                        aria-label="Toggle selection for visible artworks">
                </th>
            </tr>
        </thead>
        <tbody @if ($reorderEnabled) wire:sort="sortArtwork" @endif>
            @foreach ($artworks as $artwork)
                @php($selected = in_array((int) $artwork['id'], array_map('intval', $selectedArtworkIds), true))
                @php($meta = implode(' · ', array_filter([
                    $artwork['year'] ? (string) $artwork['year'] : null,
                    $artwork['dimensions'] ?: null,
                    $artwork['medium'] ?: null,
                ])))
                @php($analyticsSummary = $artwork['analytics']['available']
                    ? '30d · '.number_format($artwork['analytics']['views']).' views · '.number_format($artwork['analytics']['opens']).' opens · '.number_format($artwork['analytics']['zooms']).' zooms · '.$artwork['analytics']['attention'].' attention'
                    : '30d analytics unavailable')
                <tr class="{{ $selected ? 'is-selected' : '' }}" wire:key="gallery-table-artwork-{{ $artwork['id'] }}"
                    @if ($reorderEnabled) wire:sort:item="{{ $artwork['id'] }}" @endif>
                    @if ($viewMode === 'list')
                        <td class="media-workspace__thumb gallery-workspace__thumb">
                            <button type="button" wire:click="mountAction('previewArtwork', { artwork: {{ $artwork['id'] }} })"
                                title="Preview {{ $artwork['title'] }}" aria-label="Preview {{ $artwork['title'] }}">
                                @if ($artwork['thumbnail_url'])
                                    <img src="{{ $artwork['thumbnail_url'] }}" alt="" loading="lazy" decoding="async">
                                @elseif ($artwork['primary_kind'] === 'video' && $artwork['primary_original_url'])
                                    <span>{{ __('Video') }}</span>
                                @else
                                    <span>{{ __('No media') }}</span>
                                @endif
                            </button>
                        </td>
                    @endif
                    <td class="media-workspace__identity admin-table__identity admin-table__identity--ellipsis-meta gallery-workspace__table-identity">
                        <div class="gallery-workspace__table-name">
                            <button class="admin-drag-handle" type="button"
                                @if ($reorderEnabled) wire:sort:handle @endif
                                @disabled(! $reorderEnabled)
                                title="{{ $reorderEnabled ? 'Drag to reorder' : $reorderDisabledReason }}"
                                aria-label="{{ $reorderEnabled ? 'Drag '.$artwork['title'].' to reorder' : $reorderDisabledReason.' '.$artwork['title'] }}">⋮⋮</button>
                            <span class="gallery-workspace__table-sequence" title="Artwork position">{{ $artwork['sequence'] }}</span>
                            <button class="media-workspace__filename-button gallery-workspace__table-title"
                                type="button" wire:click="mountAction('previewArtwork', { artwork: {{ $artwork['id'] }} })"
                                title="{{ $artwork['title'] }}">
                                <strong>{{ $artwork['title'] }}</strong>
                            </button>
                            @if ($artwork['public_url'])
                                <a class="gallery-workspace__table-open-public" href="{{ $artwork['public_url'] }}"
                                    target="_blank" rel="noopener" title="View public artwork"
                                    aria-label="View {{ $artwork['title'] }} on the public site">
                                    <x-filament::icon :icon="\App\Filament\Support\AdminIcon::OpenPublic->mini()" />
                                </a>
                            @endif
                        </div>
                        <small title="{{ $meta }}">{{ $meta }}</small>
                        <small class="admin-responsive-meta" title="{{ $analyticsSummary }}">{{ $analyticsSummary }}</small>
                        <small class="admin-responsive-meta gallery-workspace__table-folded-state">
                            {{ $artwork['state_label'] }}{{ $artwork['state'] === 'published' ? '' : ' · '.$artwork['readiness_label'] }}
                        </small>
                    </td>
                    <td class="gallery-workspace__table-analytics admin-table__yield--compact" title="{{ $analyticsSummary }}">
                        {{ $analyticsSummary }}
                    </td>
                    <td class="gallery-workspace__table-status admin-table__yield--narrow">
                        <span class="media-workspace__state {{ $artwork['state'] === 'published' ? 'is-available' : '' }}">
                            {{ $artwork['state_label'] }}
                        </span>
                        @if ($artwork['state'] !== 'published')
                            <small title="{{ $artwork['readiness_label'] }}">{{ $artwork['readiness_label'] }}</small>
                        @endif
                    </td>
                    <td class="media-workspace__actions gallery-workspace__table-actions admin-table__actions">
                        <div class="admin-row-actions admin-row-actions--canonical admin-row-actions--stable admin-row-actions--six-static admin-toolbar gallery-workspace__row-actions">
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
                                :disabled="$artwork['state'] !== 'draft'"
                                label="Delete" title="{{ $artwork['state'] === 'draft' ? 'Delete' : 'Unpublish before deleting' }}" />
                        </div>
                    </td>
                    <td class="gallery-workspace__selection-cell">
                        <input type="checkbox" value="{{ $artwork['id'] }}" wire:model.live="selectedArtworkIds"
                            aria-label="Select artwork {{ $artwork['title'] }}">
                    </td>
                </tr>
            @endforeach
        </tbody>
    </table>
</x-admin.table>
