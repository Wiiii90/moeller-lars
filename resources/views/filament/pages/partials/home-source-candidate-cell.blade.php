{{-- One candidate presentation for the Gallery's first row and all expanded rows. --}}
<div class="home-source-table__candidate-inline">
    <button
        type="button"
        class="home-source-candidate__preview home-source-table__candidate-image-toggle"
        wire:click="toggleSourceExpansion({{ $groupId }})"
        aria-label="Collapse candidates in {{ $gallery['name'] }}"
    >
        @if ($candidate && $candidate['thumbnail_url'])
            <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
        @else
            <span>—</span>
        @endif
    </button>
    <div class="home-source-table__candidate-meta">
        @if ($candidate)
            <strong title="{{ $candidate['title'] }}">{{ $candidate['title'] }}</strong>
            <small title="{{ ($candidate['disabled'] ?? false) ? __('Disabled') : ($candidate['sequence_label'] ?? '') }}">{{ implode(', ', array_filter([$candidate['gallery'], $candidate['date']])) ?: '—' }}</small>
        @else
            <small>{{ __('No sourced candidates') }}</small>
        @endif
    </div>
    <div class="home-source-table__candidate-chance">
        @if ($candidate && $heroDisplayStrategy === 'random' && ($candidate['eligible'] ?? false))
            <label>
                <input class="admin-form-control" type="number" min="0" max="100" step="0.01"
                    value="{{ $candidate['percentage'] }}"
                    wire:change="setHeroPercentage('{{ $candidate['id'] }}', $event.target.value)"
                    aria-label="Random chance for {{ $candidate['title'] }}">
                <span>%</span>
            </label>
        @elseif ($candidate && $heroDisplayStrategy === 'random')
            <span>—</span>
        @endif
    </div>
</div>
