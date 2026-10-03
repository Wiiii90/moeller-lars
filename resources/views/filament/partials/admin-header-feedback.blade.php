@php
    $initialFeedback = app(\App\Domain\Admin\AdminNotifier::class)->pullPendingFeedback();
@endphp

<div
    class="admin-header-feedback"
    x-data="{
        current: null,
        dismissTimer: null,
        displayDurationMs: 4000,

        runtime() {
            const runtime = window.__adminHeaderFeedbackRuntime ??= {
                current: null,
                expiresAt: null,
                seen: {},
            }

            runtime.expiresAt ??= null
            runtime.seen ??= {}

            return runtime
        },

        sync() {
            const runtime = this.runtime()

            if (
                runtime.current !== null
                && runtime.expiresAt !== null
                && runtime.expiresAt <= Date.now()
            ) {
                runtime.current = null
                runtime.expiresAt = null
            }

            this.current = runtime.current
            this.armDismissal()
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

            runtime.current = notification
            runtime.expiresAt = Date.now() + this.displayDurationMs
            this.current = notification
            this.armDismissal()
        },

        armDismissal() {
            this.cancelDismissal()

            const runtime = this.runtime()

            if (runtime.current === null || runtime.expiresAt === null) {
                return
            }

            const currentId = String(runtime.current?.id ?? '')
            const remainingMs = runtime.expiresAt - Date.now()

            if (remainingMs <= 0) {
                this.dismissCurrent(currentId)

                return
            }

            this.dismissTimer = window.setTimeout(
                () => this.dismissCurrent(currentId),
                remainingMs,
            )
        },

        dismissCurrent(expectedId) {
            const runtime = this.runtime()

            if (runtime.current === null) {
                this.current = null
                this.cancelDismissal()

                return
            }

            if (String(runtime.current?.id ?? '') !== expectedId) {
                this.sync()

                return
            }

            runtime.current = null
            runtime.expiresAt = null
            this.current = null
            this.cancelDismissal()
        },

        cancelDismissal() {
            if (this.dismissTimer === null) {
                return
            }

            window.clearTimeout(this.dismissTimer)
            this.dismissTimer = null
        },

        init() {
            @js($initialFeedback).forEach((notification) => this.accept(notification))
            this.sync()
        },

        destroy() {
            this.cancelDismissal()
        },
    }"
    x-on:admin-header-feedback.window="accept($event.detail?.notification ?? $event.detail)"
    x-bind:data-status="current?.status ?? 'info'"
    x-cloak
    x-show="current !== null"
    x-transition:enter="admin-header-feedback-transition"
    x-transition:enter-start="admin-header-feedback-transition--hidden"
    x-transition:enter-end="admin-header-feedback-transition--shown"
    x-transition:leave="admin-header-feedback-transition"
    x-transition:leave-start="admin-header-feedback-transition--shown"
    x-transition:leave-end="admin-header-feedback-transition--hidden"
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
</div>
