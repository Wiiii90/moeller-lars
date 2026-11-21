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
            window.adminRememberedTabs.connectAlpine(this, {
                property: 'activePane',
                key: this.storageKey,
                allowed: this.allowedPanes,
            })

            @if ($externalState !== null)
                {{ $externalState }} = this.activePane
            @endif

            for (const [name, permitted] of Object.entries(this.rememberedState)) {
                if (!Array.isArray(permitted)) continue
                const current = this[name]
                if (typeof current !== 'string' && typeof current !== 'boolean') continue

                window.adminRememberedTabs.connectAlpine(this, {
                    property: name,
                    key: this.storageKey + '.' + name,
                    allowed: permitted,
                })
            }
        },
        selectPane(pane) {
            if (!this.allowedPanes.includes(pane)) return
            this.activePane = pane

            @if ($externalState !== null)
                {{ $externalState }} = pane
            @endif
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
