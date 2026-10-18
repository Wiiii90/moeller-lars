@props([
    'storageKey',
    'defaultPane',
    'panes',
    'ariaLabel' => 'Stage view',
    'externalState' => null,
])

@php
    $normalizedPanes = collect($panes)
        ->mapWithKeys(static fn ($label, $value): array => [(string) $value => (string) $label])
        ->all();
    $paneKeys = array_keys($normalizedPanes);
@endphp

<div
    class="admin-stage-tabs admin-stage-switcher @if ($externalState !== null) admin-stage-tabs--inline @endif"
    role="group"
    aria-label="{{ $ariaLabel }}"
    @if ($externalState === null)
    x-data="{
        activePane: @js($defaultPane),
        allowedPanes: @js($paneKeys),
        storageKey: @js($storageKey),
        init() {
            try {
                const stored = window.sessionStorage.getItem(this.storageKey)
                if (this.allowedPanes.includes(stored)) this.activePane = stored
            } catch (_) {}

        },
        selectPane(pane) {
            if (! this.allowedPanes.includes(pane)) return

            this.activePane = pane

            try {
                window.sessionStorage.setItem(this.storageKey, pane)
            } catch (_) {}

        },
    }"
    @endif
>
    @foreach ($normalizedPanes as $value => $label)
        <button
            class="admin-action"
            type="button"
            data-admin-stage-slot="{{ $loop->iteration }}"
            aria-pressed="{{ $value === $defaultPane ? 'true' : 'false' }}"
            @if ($externalState === null)
                x-on:click="selectPane(@js($value))"
                x-bind:class="{ 'is-primary': activePane === @js($value) }"
                x-bind:aria-pressed="(activePane === @js($value)).toString()"
            @else
                x-on:click="{{ $externalState }} = @js($value)"
                x-bind:class="{ 'is-primary': {{ $externalState }} === @js($value) }"
                x-bind:aria-pressed="({{ $externalState }} === @js($value)).toString()"
            @endif
        >{{ $label }}</button>
    @endforeach
</div>
