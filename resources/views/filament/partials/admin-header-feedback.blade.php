@php
    $initialFeedback = app(\App\Domain\Admin\AdminNotifier::class)->pullPendingFeedback();
@endphp

<div
    class="admin-header-feedback"
    x-data="{
        current: null,
        additionalCount: 0,

        runtime() {
            return window.__adminHeaderFeedbackRuntime ??= {
                current: null,
                additionalCount: 0,
                seen: {},
            }
        },

        sync() {
            const runtime = this.runtime()

            this.current = runtime.current
            this.additionalCount = runtime.additionalCount
        },

        accept(notification) {
            if (! notification) return

            const runtime = this.runtime()
            const id = String(notification.id ?? '')

            if (id !== '' && runtime.seen[id]) {
                this.sync()

                return
            }

            if (id !== '') {
                runtime.seen[id] = true
            }

            if (runtime.current !== null) {
                runtime.additionalCount += 1
            }

            runtime.current = notification
            this.sync()
        },

        init() {
            @js($initialFeedback).forEach((notification) => this.accept(notification))
            this.sync()
        },
    }"
    x-on:admin-header-feedback.window="accept($event.detail?.notification ?? $event.detail)"
    x-bind:data-status="current?.status ?? 'info'"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    aria-relevant="additions text"
>
    <div class="admin-header-feedback__content">
        <strong
            class="admin-header-feedback__title"
            x-text="current?.title ?? ''"
        ></strong>

        <span
            class="admin-header-feedback__body"
            x-cloak
            x-show="Boolean(current?.body)"
            x-text="current?.body ?? ''"
        ></span>
    </div>

    <span
        class="admin-header-feedback__counter"
        x-cloak
        x-show="additionalCount > 0"
        x-text="'+' + additionalCount"
        aria-hidden="true"
    ></span>
</div>
