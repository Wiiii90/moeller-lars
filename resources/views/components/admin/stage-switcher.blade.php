@props([
    'storageKey',
    'defaultPane',
    'panes',
    'ariaLabel' => 'Stage view',
    'externalState' => null,
    'rememberedState' => [],
])

@php
    $normalizedPanes = collect($panes)
        ->mapWithKeys(static fn ($label, $value): array => [(string) $value => (string) $label])
        ->all();
    $paneKeys = array_keys($normalizedPanes);
@endphp

<div
    class="admin-stage-tabs admin-stage-switcher @if ($externalState !== null) admin-stage-tabs--inline @endif @isset($tools) admin-stage-tabs--with-tools @endisset"
    role="group"
    aria-label="{{ $ariaLabel }}"
    {{ $attributes }}
    x-data="{
        activePane: @js($defaultPane),
        allowedPanes: @js($paneKeys),
        storageKey: @js($storageKey),
        rememberedState: @js($rememberedState),
        init() {
            try {
                const stored = window.sessionStorage.getItem(this.storageKey)
                if (this.allowedPanes.includes(stored)) this.activePane = stored
            } catch (_) {}

            @if ($externalState !== null)
                {{ $externalState }} = this.activePane
            @endif

            for (const [name, permitted] of Object.entries(this.rememberedState)) {
                if (!Array.isArray(permitted)) continue
                const current = this[name]
                if (typeof current !== 'string' && typeof current !== 'boolean') continue

                const key = this.storageKey + '.' + name

                try {
                    const stored = window.sessionStorage.getItem(key)
                    if (stored !== null) {
                        const restored = typeof current === 'boolean' ? stored === 'true' : stored
                        if (permitted.includes(restored)) this[name] = restored
                    }
                } catch (_) {}

                this.$watch(name, value => {
                    if (!permitted.includes(value)) return
                    try {
                        window.sessionStorage.setItem(key, String(value))
                    } catch (_) {}
                })
            }
        },
        selectPane(pane) {
            if (!this.allowedPanes.includes(pane)) return
            this.activePane = pane

            @if ($externalState !== null)
                {{ $externalState }} = pane
            @endif

            try {
                window.sessionStorage.setItem(this.storageKey, pane)
            } catch (_) {}
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
            @if ($externalState === null)
                x-bind:class="{ 'is-primary': activePane === @js($value) }"
                x-bind:aria-pressed="(activePane === @js($value)).toString()"
            @else
                x-bind:class="{ 'is-primary': {{ $externalState }} === @js($value) }"
                x-bind:aria-pressed="({{ $externalState }} === @js($value)).toString()"
            @endif
        >{{ $label }}</button>
    @endforeach
    @isset($tools)
        <span class="admin-control-separator admin-stage-tabs__separator" aria-hidden="true"></span>
        {{ $tools }}
    @endisset
</div>
