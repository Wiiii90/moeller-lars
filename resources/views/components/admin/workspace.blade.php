@props([
    'title',
])

@php($isMediaWorkspace = str_contains((string) $attributes->get('class'), 'media-workspace'))

<div
    {{ $attributes->class(['admin-workspace']) }}
    @if ($isMediaWorkspace)
        x-data="{
            storageKey: 'admin.media-files.view-mode',
            allowedModes: ['list', 'grid', 'dense'],
            restoreViewMode() {
                let storedMode = null

                try {
                    storedMode = window.localStorage.getItem(this.storageKey)
                } catch (error) {
                    return
                }

                if (storedMode === null) {
                    return
                }

                if (!this.allowedModes.includes(storedMode)) {
                    try {
                        window.localStorage.removeItem(this.storageKey)
                    } catch (error) {
                        // Keep the normal server default when browser storage is unavailable.
                    }

                    return
                }

                this.$nextTick(() => {
                    const button = Array.from(this.$el.querySelectorAll('.media-workspace__view-option'))
                        .find((candidate) => (candidate.getAttribute('aria-label') || '').toLowerCase() === storedMode)

                    if (button && !button.classList.contains('is-active')) {
                        button.click()
                    }
                })
            },
            persistViewMode(mode) {
                if (!this.allowedModes.includes(mode)) {
                    return
                }

                try {
                    window.localStorage.setItem(this.storageKey, mode)
                } catch (error) {
                    // Browser storage is optional; the current Livewire view still works without it.
                }
            },
        }"
        x-init="restoreViewMode()"
        x-on:click="
            const button = $event.target.closest('.media-workspace__view-option')
            if (button && button.closest('.media-workspace__view-options')) {
                persistViewMode((button.getAttribute('aria-label') || '').toLowerCase())
            }
        "
    @endif
>
    <header class="admin-workspace__header">
        <div class="admin-workspace__heading">
            <h1 class="admin-workspace__title">{{ $title }}</h1>
        </div>

        @isset($summary)
            <div class="admin-workspace__summary">
                {{ $summary }}
            </div>
        @endisset
    </header>

    <div class="admin-workspace__body">
        {{ $slot }}
    </div>
</div>
