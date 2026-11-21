{{-- Every expanded candidate has its own row; only the Gallery strip collapses the group. --}}
<div class="gallery-hero-source-table__candidate-inline">
    <span class="gallery-hero-source-candidate__preview">
        @if ($candidate['thumbnail_url'])
            <img src="{{ $candidate['thumbnail_url'] }}" alt="" loading="lazy" decoding="async" draggable="false">
        @else
            <span>—</span>
        @endif
    </span>
    <div class="gallery-hero-source-table__candidate-meta">
        <strong title="{{ $candidate['title'] }}">{{ $candidate['title'] }}</strong>
        <small title="{{ ($candidate['disabled'] ?? false) ? __('Disabled') : ($candidate['sequence_label'] ?? '') }}">{{ implode(', ', array_filter([$candidate['gallery'], $candidate['date']])) ?: '—' }}</small>
    </div>
    <div class="gallery-hero-source-table__candidate-chance">
        @if ($heroDisplayStrategy === 'random' && ($candidate['eligible'] ?? false))
            <x-admin.inline-percentage
                :value="$candidate['percentage']"
                :label="'Random chance for '.$candidate['title']"
                wire:change="setHeroPercentage('{{ $candidate['id'] }}', $event.target.value)"
            />
        @elseif ($heroDisplayStrategy === 'random')
            <span>—</span>
        @endif
    </div>
</div>
