@php
    $initialFeedback = app(\App\Domain\Admin\AdminNotifier::class)->pullPendingFeedback();
@endphp

<div
    class="admin-header-feedback"
    x-data="{
        current: null,
        pendingCount: 0,
        secondsRemaining: 0,
        phase: 'idle',
        messageRevision: 0,
        displayDurationMs: 8000,
        exitDurationMs: 700,
        queueLimit: 8,
        seenLimit: 48,
        lifecycleTimer: null,
        countdownTimer: null,

        runtime() {
            const runtime = window.__adminHeaderFeedbackRuntime ??= {
                current: null,
                queue: [],
                expiresAt: null,
                seen: [],
            }

            if (! Array.isArray(runtime.queue)) {
                runtime.queue = []
            }

            if (! Array.isArray(runtime.seen)) {
                runtime.seen = []
            }

            runtime.expiresAt ??= null

            return runtime
        },

        syncFromRuntime() {
            this.cancelTimers()

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
            this.pendingCount = runtime.queue.length

            if (runtime.current === null) {
                if (runtime.queue.length > 0) {
                    this.promoteNext()
                } else {
                    this.phase = 'idle'
                    this.secondsRemaining = 0
                }

                return
            }

            this.phase = 'visible'
            this.armLifecycle()
            this.updateCountdown()
        },

        accept(notification) {
            if (! notification) return

            const runtime = this.runtime()
            const id = String(notification.id ?? '')

            if (id !== '' && runtime.seen.includes(id)) {
                this.syncFromRuntime()

                return
            }

            if (id !== '') {
                runtime.seen.push(id)

                if (runtime.seen.length > this.seenLimit) {
                    runtime.seen = runtime.seen.slice(-this.seenLimit)
                }
            }

            if (runtime.current === null) {
                this.startNotification(notification)

                return
            }

            if (runtime.queue.length >= this.queueLimit) {
                runtime.queue.shift()
            }

            runtime.queue.push(notification)
            this.pendingCount = runtime.queue.length
        },

        startNotification(notification) {
            const runtime = this.runtime()

            runtime.current = notification
            runtime.expiresAt = Date.now() + this.displayDurationMs

            this.current = notification
            this.pendingCount = runtime.queue.length
            this.secondsRemaining = Math.ceil(this.displayDurationMs / 1000)
            this.messageRevision += 1
            this.phase = 'entering'

            this.armLifecycle()
            this.updateCountdown()
        },

        promoteNext() {
            const runtime = this.runtime()
            const next = runtime.queue.shift() ?? null

            this.pendingCount = runtime.queue.length

            if (next === null) {
                runtime.current = null
                runtime.expiresAt = null
                this.current = null
                this.phase = 'idle'
                this.secondsRemaining = 0

                return
            }

            this.startNotification(next)
        },

        armLifecycle() {
            this.cancelLifecycle()

            const runtime = this.runtime()

            if (runtime.current === null || runtime.expiresAt === null) {
                return
            }

            const currentId = String(runtime.current?.id ?? '')
            const remainingMs = runtime.expiresAt - Date.now()

            if (remainingMs <= 0) {
                this.beginLeave(currentId)

                return
            }

            this.lifecycleTimer = window.setTimeout(
                () => this.beginLeave(currentId),
                remainingMs,
            )
        },

        beginLeave(expectedId) {
            const runtime = this.runtime()

            if (
                runtime.current === null
                || String(runtime.current?.id ?? '') !== expectedId
            ) {
                this.syncFromRuntime()

                return
            }

            this.cancelCountdown()
            this.cancelLifecycle()
            this.secondsRemaining = 0
            this.phase = 'leaving'

            const exitDelay = window.matchMedia('(prefers-reduced-motion: reduce)').matches
                ? 0
                : this.exitDurationMs

            this.lifecycleTimer = window.setTimeout(
                () => this.finishCurrent(expectedId),
                exitDelay,
            )
        },

        finishCurrent(expectedId) {
            const runtime = this.runtime()

            if (
                runtime.current !== null
                && String(runtime.current?.id ?? '') !== expectedId
            ) {
                this.syncFromRuntime()

                return
            }

            runtime.current = null
            runtime.expiresAt = null
            this.current = null
            this.pendingCount = runtime.queue.length
            this.phase = 'idle'
            this.secondsRemaining = 0
            this.cancelTimers()

            if (runtime.queue.length > 0) {
                this.$nextTick(() => this.promoteNext())
            }
        },

        updateCountdown() {
            this.cancelCountdown()

            const runtime = this.runtime()

            if (runtime.current === null || runtime.expiresAt === null) {
                this.secondsRemaining = 0

                return
            }

            const remainingMs = Math.max(0, runtime.expiresAt - Date.now())
            this.secondsRemaining = Math.ceil(remainingMs / 1000)

            if (remainingMs <= 0) {
                return
            }

            this.countdownTimer = window.setTimeout(
                () => this.updateCountdown(),
                Math.min(250, remainingMs),
            )
        },

        cancelLifecycle() {
            if (this.lifecycleTimer === null) {
                return
            }

            window.clearTimeout(this.lifecycleTimer)
            this.lifecycleTimer = null
        },

        cancelCountdown() {
            if (this.countdownTimer === null) {
                return
            }

            window.clearTimeout(this.countdownTimer)
            this.countdownTimer = null
        },

        cancelTimers() {
            this.cancelLifecycle()
            this.cancelCountdown()
        },

        init() {
            this.syncFromRuntime();

            const initialFeedback = @js($initialFeedback);
            initialFeedback.forEach((notification) => this.accept(notification))
        },

        destroy() {
            this.cancelTimers()
        },
    }"
    x-on:admin-header-feedback.window="accept($event.detail?.notification ?? $event.detail)"
    x-bind:data-status="current?.status ?? 'info'"
    x-bind:data-phase="phase"
    x-bind:data-message-revision="messageRevision"
    x-cloak
    x-show="current !== null"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    aria-relevant="additions text"
>
    <span class="admin-header-feedback__icon" aria-hidden="true">
        <x-filament::icon
            :icon="\App\Filament\Support\AdminIcon::NotificationSuccess->mini()"
            x-show="current?.status === 'success'"
        />
        <x-filament::icon
            :icon="\App\Filament\Support\AdminIcon::NotificationWarning->mini()"
            x-show="current?.status === 'warning'"
        />
        <x-filament::icon
            :icon="\App\Filament\Support\AdminIcon::NotificationDanger->mini()"
            x-show="current?.status === 'danger'"
        />
        <x-filament::icon
            :icon="\App\Filament\Support\AdminIcon::NotificationInfo->mini()"
            x-show="! ['success', 'warning', 'danger'].includes(current?.status ?? '')"
        />
    </span>

    <div class="admin-header-feedback__content">
        <strong
            class="admin-header-feedback__title"
            x-text="current?.title ?? ''"
        ></strong>

        <span
            class="admin-header-feedback__separator"
            x-cloak
            x-show="Boolean(current?.body)"
            aria-hidden="true"
        >:</span>

        <span
            class="admin-header-feedback__body"
            x-cloak
            x-show="Boolean(current?.body)"
            x-text="current?.body ?? ''"
        ></span>
    </div>

    <div class="admin-header-feedback__meta" aria-hidden="true">
        <span
            class="admin-header-feedback__timer"
            x-text="secondsRemaining + 's'"
        ></span>

        <span
            class="admin-header-feedback__counter"
            x-cloak
            x-show="pendingCount > 0"
            x-text="'+' + pendingCount"
        ></span>
    </div>
</div>
