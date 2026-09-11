<x-filament-panels::page>
    <x-admin.workspace title="General" class="admin-general">
        @include('filament.schemas.components.general-status-metrics')

        <section
            class="general-appearance-stage admin-visual-stage admin-visual-stage--rail admin-visual-stage--stackable"
            aria-label="General settings and live public preview"
            x-data="{
                device: 'desktop',
                refreshTimer: null,
                refreshPreview() {
                    window.clearTimeout(this.refreshTimer)
                    this.refreshTimer = window.setTimeout(() => {
                        const url = new URL(this.$refs.frame.src, window.location.origin)
                        url.searchParams.set('_appearance', Date.now().toString())
                        this.$refs.frame.src = url.toString()
                    }, 700)
                },
            }"
            x-on:general-appearance-updated.window="refreshPreview()"
            x-on:change.debounce.800ms="refreshPreview()"
        >
            <div class="general-appearance-stage__controls admin-visual-stage__pane">
                <livewire:admin.general-layout-controls />
                {{ $this->form }}
            </div>

            <aside class="general-appearance-stage__preview admin-visual-stage__pane" aria-label="Live public preview">
                <div class="general-live-preview__toolbar">
                    <span>Live preview</span>
                    <div class="general-live-preview__modes" role="group" aria-label="Preview device">
                        <button
                            class="general-live-preview__mode"
                            type="button"
                            x-bind:class="device === 'desktop' ? 'is-active' : ''"
                            x-on:click="device = 'desktop'"
                            x-bind:aria-pressed="(device === 'desktop').toString()"
                            title="Desktop preview"
                        >
                            <x-filament::icon icon="heroicon-m-computer-desktop" />
                        </button>
                        <button
                            class="general-live-preview__mode"
                            type="button"
                            x-bind:class="device === 'mobile' ? 'is-active' : ''"
                            x-on:click="device = 'mobile'"
                            x-bind:aria-pressed="(device === 'mobile').toString()"
                            title="Mobile preview"
                        >
                            <x-filament::icon icon="heroicon-m-device-phone-mobile" />
                        </button>
                    </div>
                </div>

                <div class="general-live-preview__viewport">
                    <div
                        class="general-live-preview__device"
                        x-bind:class="device === 'desktop' ? 'is-desktop' : 'is-mobile'"
                    >
                        <iframe
                            x-ref="frame"
                            src="{{ route('preview.home', ['appearance' => now()->timestamp]) }}"
                            title="Live preview of the public homepage"
                            loading="eager"
                        ></iframe>
                    </div>
                </div>
            </aside>
        </section>
    </x-admin.workspace>
</x-filament-panels::page>
