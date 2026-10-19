<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="admin-angle-control"
        x-data="{
            open: false,
            value: Number($wire.get('{{ $getStatePath() }}') ?? 180),
            dragging: false,
            position: { left: 0, top: 0 },
            get angle() { return Math.max(0, Math.min(360, Number(this.value) || 0)) },
            reveal() {
                const rect = this.$refs.input.getBoundingClientRect();
                this.position = {
                    left: Math.max(8, Math.min(rect.left, window.innerWidth - 172)),
                    top: rect.bottom + 165 < window.innerHeight ? rect.bottom + 5 : Math.max(8, rect.top - 160)
                };
                this.open = true;
            },
            pick(event) {
                const bounds = this.$refs.dial.getBoundingClientRect();
                const dx = event.clientX - bounds.left - bounds.width / 2;
                const dy = event.clientY - bounds.top - bounds.height / 2;
                this.value = Math.round((Math.atan2(dx, -dy) * 180 / Math.PI + 360) % 360);
            },
            commit() {
                const degree = Number(this.value);
                if (!Number.isFinite(degree)) return;
                this.value = Math.max(0, Math.min(360, Math.round(degree)));
                $wire.set('{{ $getStatePath() }}', this.value);
            }
        }"
        x-on:keydown.escape.window="open = false; dragging = false"
    >
        <div class="admin-angle-control__value" x-ref="input">
            <input
                type="number"
                min="0"
                max="360"
                step="1"
                x-model.number="value"
                x-on:focus="reveal()"
                x-on:click="reveal()"
                x-on:change="commit()"
                x-on:keydown.enter.prevent="commit(); open = false"
                aria-label="Gradient angle in degrees"
            >
            <span aria-hidden="true">°</span>
        </div>

        <template x-teleport="body">
            <div
                class="admin-angle-control__popover"
                x-show="open"
                x-cloak
                x-bind:style="'left: ' + position.left + 'px; top: ' + position.top + 'px'"
                x-on:click.outside="if (!$event.target.closest('.admin-angle-control__value')) open = false"
            >
                <button
                    type="button"
                    class="admin-angle-control__dial"
                    role="slider"
                    aria-label="Adjust gradient angle"
                    aria-valuemin="0"
                    aria-valuemax="360"
                    x-bind:aria-valuenow="angle"
                    x-ref="dial"
                    x-on:pointerdown="dragging = true; $el.setPointerCapture($event.pointerId); pick($event)"
                    x-on:pointermove="if (dragging) pick($event)"
                    x-on:pointerup="if (dragging) { pick($event); dragging = false; commit() }"
                    x-on:pointercancel="dragging = false"
                    x-on:keydown.left.prevent="value = Math.max(0, angle - 1); commit()"
                    x-on:keydown.right.prevent="value = Math.min(360, angle + 1); commit()"
                    x-on:keydown.up.prevent="value = Math.min(360, angle + 1); commit()"
                    x-on:keydown.down.prevent="value = Math.max(0, angle - 1); commit()"
                >
                    <span class="admin-angle-control__needle" x-bind:style="'transform: rotate(' + angle + 'deg)'"></span>
                </button>
            </div>
        </template>
    </div>
</x-dynamic-component>
