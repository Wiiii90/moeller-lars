@php
    // Repeated help text on the same page must still have unique ARIA targets.
    $tooltipId = 'admin-help-'.\Illuminate\Support\Str::uuid();
@endphp

<span
    class="admin-help"
    x-data="{
        open: false,
        top: 0,
        left: 0,
        show() {
            this.open = true;
            this.$nextTick(() => this.place());
        },
        hide() { this.open = false },
        place() {
            if (! this.open || ! this.$refs.trigger || ! this.$refs.panel) return;
            const trigger = this.$refs.trigger.getBoundingClientRect();
            const panel = this.$refs.panel;
            const width = panel.offsetWidth;
            const height = panel.offsetHeight;
            const margin = 12;
            const gap = 8;
            const below = window.innerHeight - trigger.bottom - margin;
            const above = trigger.top - margin;
            const upward = below < height + gap && above > below;
            const requestedTop = upward ? trigger.top - height - gap : trigger.bottom + gap;
            this.top = Math.max(margin, Math.min(requestedTop, window.innerHeight - height - margin));
            this.left = Math.max(margin, Math.min(trigger.right - width, window.innerWidth - width - margin));
        }
    }"
    x-on:mouseenter="show()"
    x-on:mouseleave="hide()"
    x-on:focusin="show()"
    x-on:focusout="if (! $el.contains($event.relatedTarget)) hide()"
    x-on:keydown.escape.window="hide()"
    x-on:scroll.window="if (open) place()"
    x-on:resize.window="if (open) place()"
>
    <button
        x-ref="trigger"
        type="button"
        class="admin-help__trigger"
        aria-label="{{ $label }}"
        aria-describedby="{{ $tooltipId }}"
        aria-controls="{{ $tooltipId }}"
        x-bind:aria-expanded="open.toString()"
        x-on:click.stop="open ? hide() : show()"
    >
        <svg viewBox="0 0 24 24" aria-hidden="true">
            <path d="M9 18h6M10 22h4M8.4 14.4A6 6 0 1 1 15.6 14.4C14.6 15.2 14 16 14 17h-4c0-1-.6-1.8-1.6-2.6Z" />
        </svg>
    </button>
    <template x-teleport="body">
        <span
            id="{{ $tooltipId }}"
            x-ref="panel"
            class="admin-help__popover"
            role="tooltip"
            x-cloak
            x-show="open"
            x-bind:style="'top:' + top + 'px;left:' + left + 'px'"
        >{{ $text }}</span>
    </template>
</span>
