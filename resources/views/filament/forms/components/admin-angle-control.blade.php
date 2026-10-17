<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="admin-angle-control"
        x-data="{
            value: Number($wire.get('{{ $getStatePath() }}') ?? 180),
            dragging: false,
            get angle() { return Math.max(0, Math.min(360, Number(this.value) || 0)) },
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
    >
        <button
            type="button"
            class="admin-angle-control__dial"
            role="slider"
            aria-label="Gradient angle"
            aria-valuemin="0"
            aria-valuemax="360"
            x-bind:aria-valuenow="angle"
            x-ref="dial"
            x-on:pointerdown="dragging = true; $el.setPointerCapture($event.pointerId); pick($event)"
            x-on:pointermove="if (dragging) pick($event)"
            x-on:pointerup="if (dragging) { pick($event); dragging = false; commit() }"
            x-on:pointercancel="dragging = false"
            x-on:keydown.left.prevent="value = (angle + 359) % 360; commit()"
            x-on:keydown.right.prevent="value = (angle + 1) % 360; commit()"
            x-on:keydown.up.prevent="value = (angle + 1) % 360; commit()"
            x-on:keydown.down.prevent="value = (angle + 359) % 360; commit()"
        ><span class="admin-angle-control__needle" x-bind:style="'transform: rotate(' + angle + 'deg)'"></span></button>
        <div class="admin-angle-control__input">
            <input type="number" min="0" max="360" step="1" x-model.number="value" x-on:change="commit()" aria-label="Gradient angle in degrees">
            <span aria-hidden="true">°</span>
        </div>
    </div>
</x-dynamic-component>
