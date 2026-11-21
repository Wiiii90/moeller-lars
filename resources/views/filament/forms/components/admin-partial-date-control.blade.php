<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <x-admin.partial-date-picker
        :accessible-label="$accessibleLabel"
        x-data="adminPartialDateControl($wire.entangle('{{ $getStatePath() }}'){{ $liveUpdates ? '.live' : '' }}, @js($region))"
    />
</x-dynamic-component>
