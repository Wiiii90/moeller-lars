@php
    $stops = $generalPage->appearanceStops($surface);
    $extras = array_slice($stops, 1, -1, true);
@endphp

<div class="general-gradient-colors">
    @foreach ($extras as $index => $stop)
        <div class="general-gradient-colors__row" wire:key="gradient-color-{{ $surface }}-{{ $index }}">
            <span class="general-gradient-colors__swatch" style="background-color: {{ $stop['color'] }}" aria-hidden="true"></span>
            <span class="general-gradient-colors__value">{{ $stop['color'] }}</span>
            <span class="general-gradient-colors__position">{{ $stop['position'] }}%</span>
            <x-admin.row-action
                :action="\App\Filament\Support\AdminRowAction::Edit"
                wire:click="mountAction('editGradientColor', { surface: '{{ $surface }}', index: {{ $index }} })"
            />
            <button type="button" class="admin-action" wire:click="mountAction('removeGradientColor', { surface: '{{ $surface }}', index: {{ $index }} })">Remove</button>
        </div>
    @endforeach
    @if (count($stops) < \App\Domain\Content\PublicAppearance::MAX_STOPS)
        <x-admin.add-row wire:click="mountAction('addGradientColor', { surface: '{{ $surface }}' })">Add color</x-admin.add-row>
    @endif
</div>
