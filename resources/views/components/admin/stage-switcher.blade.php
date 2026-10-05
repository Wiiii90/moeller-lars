@props([
    'storageKey',
    'defaultPane',
    'panes',
    'ariaLabel' => 'Stage view',
])

@php
    $normalizedPanes = collect($panes)
        ->mapWithKeys(static fn ($label, $value): array => [(string) $value => (string) $label])
        ->all();
    $paneKeys = array_keys($normalizedPanes);
@endphp

<div
    class="admin-stage-switcher"
    role="group"
    aria-label="{{ $ariaLabel }}"
    x-data="{
        activePane: @js($defaultPane),
        allowedPanes: @js($paneKeys),
        storageKey: @js($storageKey),
        init() {
            try {
                const stored = window.sessionStorage.getItem(this.storageKey)
                if (this.allowedPanes.includes(stored)) this.activePane = stored
            } catch (_) {}

            this.sync()
        },
        selectPane(pane) {
            if (! this.allowedPanes.includes(pane)) return

            this.activePane = pane

            try {
                window.sessionStorage.setItem(this.storageKey, pane)
            } catch (_) {}

            this.sync()
        },
        sync() {
            this.$el.parentElement?.setAttribute('data-active-pane', this.activePane)
        },
    }"
>
    @foreach ($normalizedPanes as $value => $label)
        <button
            class="admin-action"
            type="button"
            data-admin-stage-slot="{{ $loop->iteration }}"
            aria-pressed="{{ $value === $defaultPane ? 'true' : 'false' }}"
            x-on:click="selectPane(@js($value))"
            x-bind:class="{ 'is-primary': activePane === @js($value) }"
            x-bind:aria-pressed="(activePane === @js($value)).toString()"
        >{{ $label }}</button>
    @endforeach
</div>
