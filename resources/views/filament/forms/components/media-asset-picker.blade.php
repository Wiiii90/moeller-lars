@php
    $statePath = $getStatePath();
    $state = $getState();
    $key = $getKey();
    $disabled = $isDisabled();
@endphp

<x-dynamic-component
    :component="$getFieldWrapperView()"
    :field="$field"
    class="fi-fo-select-wrp"
>
    <x-filament::input.wrapper
        :disabled="$disabled"
        :valid="! $errors->has($statePath)"
        :prefix-actions="$getPrefixActions()"
        :prefix-icon="$getPrefixIcon()"
        :suffix-actions="$getSuffixActions()"
        :suffix-icon="$getSuffixIcon()"
        :attributes="\Filament\Support\prepare_inherited_attributes($getExtraAttributeBag())->class(['fi-fo-select'])"
    >
        <div
            wire:key="{{ $getLivewireKey() }}.media-picker"
            x-data="{
                state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
                selectedLabel: @js(blank($state) ? null : $getOptionLabel()),
                isLive: @js($isLive()),
                isOpen: false,
                isLoading: false,
                hasMore: true,
                hasLoaded: false,
                options: [],
                offset: 0,
                search: '',
                error: false,
                requestVersion: 0,
                openUpwards: false,
                listMaxHeight: 350,

                positionDropdown() {
                    const trigger = this.$refs.trigger
                    if (! trigger) return

                    const bounds = trigger.getBoundingClientRect()
                    const scrollport = trigger.closest('.fi-modal-content')?.getBoundingClientRect()
                    const upper = Math.max(8, scrollport?.top ?? 8)
                    const lower = Math.min(window.innerHeight - 8, scrollport?.bottom ?? window.innerHeight - 8)
                    const roomAbove = Math.max(0, bounds.top - upper)
                    const roomBelow = Math.max(0, lower - bounds.bottom)

                    this.openUpwards = roomBelow < 260 && roomAbove > roomBelow
                    this.listMaxHeight = Math.max(
                        90,
                        Math.min(350, (this.openUpwards ? roomAbove : roomBelow) - 55),
                    )
                },

                async openPicker() {
                    if (@js($disabled)) return
                    if (this.isOpen) return

                    this.isOpen = true
                    this.$nextTick(() => {
                        this.positionDropdown()
                        this.$refs.search?.focus()
                        if (! this.hasLoaded) this.loadMore()
                    })
                },

                closePicker(restoreFocus = false) {
                    if (! this.isOpen) return

                    this.isOpen = false
                    this.requestVersion++
                    if (restoreFocus) this.$refs.trigger?.focus()
                },

                setSearch(value) {
                    const normalized = value.trim()
                    if (normalized === this.search) return

                    this.search = normalized
                    this.options = []
                    this.offset = 0
                    this.hasMore = true
                    this.hasLoaded = false
                    this.error = false
                    this.requestVersion++
                    this.loadMore()
                },

                isNearEnd() {
                    const list = this.$refs.list
                    return list && list.scrollHeight - list.scrollTop - list.clientHeight < 100
                },

                async loadMore() {
                    if (! this.isOpen || this.isLoading || ! this.hasMore) return

                    this.isLoading = true
                    this.error = false
                    const version = this.requestVersion
                    const nextOffset = this.offset

                    try {
                        const page = await $wire.callSchemaComponentMethod(
                            @js($key),
                            'loadMediaPage',
                            { offset: nextOffset, search: this.search },
                        )

                        if (version !== this.requestVersion || ! this.isOpen) return

                        const incoming = Array.isArray(page?.options) ? page.options : []
                        const known = new Set(this.options.map((item) => String(item.value)))

                        for (const item of incoming) {
                            const value = String(item.value)
                            if (known.has(value)) continue
                            this.options.push({ value, label: item.label })
                            known.add(value)
                        }

                        this.offset = nextOffset + incoming.length
                        this.hasMore = Boolean(page?.hasMore)
                        this.hasLoaded = true
                    } catch (error) {
                        if (version === this.requestVersion) {
                            this.error = true
                            console.error('Media choices could not be loaded.', error)
                        }
                    } finally {
                        this.isLoading = false
                        if (version !== this.requestVersion) {
                            if (this.isOpen) this.loadMore()
                        } else if (this.isOpen && this.hasMore && ! this.error) {
                            this.$nextTick(() => {
                                if (this.isNearEnd()) this.loadMore()
                            })
                        }
                    }
                },

                choose(option) {
                    const next = String(option.value)
                    const changed = String(this.state ?? '') !== next
                    this.state = next
                    this.selectedLabel = option.label
                    this.closePicker(true)
                    if (changed) this.emitCompletedChange()
                },

                clear() {
                    if (this.state === null || this.state === undefined || this.state === '') return
                    this.state = null
                    this.selectedLabel = null
                    this.closePicker()
                    this.emitCompletedChange()
                },

                emitCompletedChange() {
                    // Live fields persist through their existing updated-state
                    // callback; non-live Edit dialogs own the native change.
                    if (this.isLive || this.$el.dataset.adminEditChangeOwner === 'livewire') return
                    this.$el.dispatchEvent(new Event('change', { bubbles: true }))
                },
            }"
            x-effect="if (state === null || state === '') selectedLabel = null"
            x-on:keydown.escape="if (isOpen) { $event.stopPropagation(); closePicker(true) }"
            x-on:click.outside="closePicker()"
            {{
                $attributes
                    ->merge($getExtraAlpineAttributes(), escape: false)
                    ->class(['admin-media-picker'])
            }}
        >
            <div class="admin-media-picker__selected">
                <button
                    x-ref="trigger"
                    id="{{ $getId() }}"
                    type="button"
                    class="fi-select-input-btn admin-media-picker__trigger"
                    role="combobox"
                    :aria-expanded="isOpen ? 'true' : 'false'"
                    aria-haspopup="listbox"
                    aria-controls="{{ $getId() }}-options"
                    x-on:click="isOpen ? closePicker() : openPicker()"
                    x-on:keydown.arrow-down.prevent="openPicker()"
                    @disabled($disabled)
                >
                    <span
                        class="fi-select-input-value-label admin-media-picker__value"
                        x-show="selectedLabel"
                        x-cloak
                        x-html="selectedLabel"
                    ></span>
                    <span
                        class="admin-media-picker__placeholder"
                        x-show="! selectedLabel"
                        x-cloak
                    >{{ $getPlaceholder() ?: __('Choose from Storage') }}</span>
                    @unless ($disabled)
                        <x-filament::icon icon="heroicon-m-chevron-up-down" class="admin-media-picker__chevron" />
                    @endunless
                </button>

                @if ($canSelectPlaceholder())
                    <button
                        type="button"
                        class="admin-media-picker__clear"
                        aria-label="{{ __('Clear media selection') }}"
                        title="{{ __('Clear media selection') }}"
                        x-show="selectedLabel"
                        x-cloak
                        x-on:click.stop="clear()"
                        @disabled($disabled)
                    >
                        <x-filament::icon icon="heroicon-m-x-mark" />
                    </button>
                @endif
            </div>

            <div
                class="admin-media-picker__dropdown"
                :class="{ 'admin-media-picker__dropdown--above': openUpwards }"
                :style="{ '--admin-media-picker-list-max-height': listMaxHeight + 'px' }"
                x-show="isOpen"
                x-cloak
                x-on:click.stop
            >
                <input
                    x-ref="search"
                    class="admin-media-picker__search"
                    type="search"
                    autocomplete="off"
                    aria-label="{{ $getSearchPrompt() }}"
                    placeholder="{{ $getSearchPrompt() }}"
                    x-on:input.debounce.300ms.stop="setSearch($event.target.value)"
                    x-on:change.stop="$event.stopPropagation()"
                    x-on:keydown.enter.prevent="$event.preventDefault()"
                />

                <div
                    x-ref="list"
                    id="{{ $getId() }}-options"
                    class="admin-media-picker__list"
                    role="listbox"
                    aria-label="{{ $getLabel() }}"
                    x-on:scroll.passive="if (isNearEnd()) loadMore()"
                >
                    <template x-for="option in options" :key="option.value">
                        <button
                            type="button"
                            class="fi-select-input-option admin-media-picker__option"
                            role="option"
                            :aria-selected="String(state ?? '') === option.value"
                            :class="{ 'fi-selected': String(state ?? '') === option.value }"
                            x-on:click="choose(option)"
                        >
                            <span x-html="option.label"></span>
                        </button>
                    </template>
                </div>

                <div class="admin-media-picker__status" x-show="isLoading" x-cloak role="status">{{ $getSearchingMessage() }}</div>
                <div class="admin-media-picker__status" x-show="hasLoaded && ! isLoading && ! options.length && ! error" x-cloak>{{ $getNoSearchResultsMessage() }}</div>
                <button
                    type="button"
                    class="admin-media-picker__status admin-media-picker__retry"
                    x-show="error && ! isLoading"
                    x-cloak
                    x-on:click="loadMore()"
                >{{ __('Could not load files. Try again') }}</button>
            </div>
        </div>
    </x-filament::input.wrapper>
</x-dynamic-component>
