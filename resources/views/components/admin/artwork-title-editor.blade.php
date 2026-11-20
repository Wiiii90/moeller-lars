@props(['artwork'])

{{-- Canonical Gallery Artwork inline title editor for Grid, List and Dense. --}}
<div
                                        class="gallery-workspace__title-editor"
                                        x-data="{
                                            editing: false,
                                            saving: false,
                                            original: '',
                                            value: '',
                                            normalize(value) { return value.trim().replace(/\s+/g, ' ') },
                                            start() {
                                                this.value = this.original
                                                this.editing = true
                                                this.$nextTick(() => this.$refs.input.select())
                                            },
                                            cancel() {
                                                this.value = this.original
                                                this.editing = false
                                                this.saving = false
                                            },
                                            commit() {
                                                if (! this.editing || this.saving) return
                                                const next = this.normalize(this.value)
                                                if (next === this.normalize(this.original)) {
                                                    this.value = this.original
                                                    this.editing = false
                                                    return
                                                }

                                                this.saving = true
                                                $wire.renameArtwork({{ $artwork['id'] }}, next)
                                                    .then((saved) => {
                                                        if (saved) {
                                                            this.original = saved
                                                            this.value = saved
                                                        } else {
                                                            this.value = this.original
                                                        }
                                                        this.editing = false
                                                        this.saving = false
                                                    })
                                                    .catch(() => {
                                                        this.value = this.original
                                                        this.editing = false
                                                        this.saving = false
                                                    })
                                            },
                                        }"
                                        x-init="original = $refs.initialTitle.textContent; value = original"
                                    >
                                        <span x-ref="initialTitle" hidden>{{ $artwork['title'] }}</span>
                                        <button class="gallery-workspace__title-button" type="button" x-show="!editing" x-on:click="start" title="Rename artwork">
                                            <strong x-text="original">{{ $artwork['title'] }}</strong>
                                        </button>
                                        <input
                                            class="gallery-workspace__title-input"
                                            x-ref="input"
                                            x-show="editing"
                                            x-cloak
                                            x-model="value"
                                            x-on:keydown.enter.prevent="commit"
                                            x-on:keydown.escape.stop.prevent="cancel"
                                            x-on:blur="commit"
                                            maxlength="240"
                                            aria-label="Rename {{ $artwork['title'] }}"
                                        >
                                    </div>
