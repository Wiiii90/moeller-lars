@php
    $initialNotifications = request()->hasHeader('X-Livewire-Navigate')
        ? []
        : app(\App\Domain\Admin\AdminNotifier::class)->pullPendingNotifications();
@endphp

@persist('admin-header-notification')
<div
    class="admin-header-notification"
    x-data="{
        current: null,
        queue: [],
        seen: [],
        expiresAt: null,
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

        accept(notification) {
            if (! notification) return

            const id = String(notification.id ?? '')
            if (id !== '' && this.seen.includes(id)) return

            if (id !== '') {
                this.seen.push(id)
                if (this.seen.length > this.seenLimit) {
                    this.seen = this.seen.slice(-this.seenLimit)
                }
            }

            if (this.current === null) {
                this.startNotification(notification)
                return
            }

            if (this.queue.length >= this.queueLimit) {
                this.queue.shift()
            }

            this.queue.push(notification)
            this.pendingCount = this.queue.length
        },

        startNotification(notification) {
            this.current = notification
            this.expiresAt = Date.now() + this.displayDurationMs
            this.pendingCount = this.queue.length
            this.secondsRemaining = Math.ceil(this.displayDurationMs / 1000)
            this.messageRevision += 1
            this.phase = 'entering'

            this.armLifecycle()
            this.updateCountdown()
        },

        promoteNext() {
            const next = this.queue.shift() ?? null
            this.pendingCount = this.queue.length

            if (next === null) {
                this.current = null
                this.expiresAt = null
                this.phase = 'idle'
                this.secondsRemaining = 0
                return
            }

            this.startNotification(next)
        },

        armLifecycle() {
            this.cancelLifecycle()

            if (this.current === null || this.expiresAt === null) return

            const currentId = String(this.current?.id ?? '')
            const remainingMs = this.expiresAt - Date.now()

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
            if (
                this.current === null
                || String(this.current?.id ?? '') !== expectedId
            ) {
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
            if (
                this.current !== null
                && String(this.current?.id ?? '') !== expectedId
            ) {
                return
            }

            this.current = null
            this.expiresAt = null
            this.pendingCount = this.queue.length
            this.phase = 'idle'
            this.secondsRemaining = 0
            this.cancelTimers()

            if (this.queue.length > 0) {
                this.$nextTick(() => this.promoteNext())
            }
        },

        updateCountdown() {
            this.cancelCountdown()

            if (this.current === null || this.expiresAt === null) {
                this.secondsRemaining = 0
                return
            }

            const remainingMs = Math.max(0, this.expiresAt - Date.now())
            this.secondsRemaining = Math.ceil(remainingMs / 1000)

            if (remainingMs <= 0) return

            this.countdownTimer = window.setTimeout(
                () => this.updateCountdown(),
                Math.min(250, remainingMs),
            )
        },

        cancelLifecycle() {
            if (this.lifecycleTimer === null) return

            window.clearTimeout(this.lifecycleTimer)
            this.lifecycleTimer = null
        },

        cancelCountdown() {
            if (this.countdownTimer === null) return

            window.clearTimeout(this.countdownTimer)
            this.countdownTimer = null
        },

        cancelTimers() {
            this.cancelLifecycle()
            this.cancelCountdown()
        },

        init() {
            const initialNotifications = @js($initialNotifications)
            initialNotifications.forEach((notification) => this.accept(notification))
        },

        destroy() {
            this.cancelTimers()
        },
    }"
    x-on:admin-header-notification.window="accept($event.detail?.notification ?? $event.detail)"
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
    <span class="admin-header-notification__icon" aria-hidden="true">
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

    <div class="admin-header-notification__content">
        <strong
            class="admin-header-notification__title"
            x-text="current?.title ?? ''"
        ></strong>

        <span
            class="admin-header-notification__separator"
            x-cloak
            x-show="Boolean(current?.body)"
            aria-hidden="true"
        >:</span>

        <span
            class="admin-header-notification__body"
            x-cloak
            x-show="Boolean(current?.body)"
            x-text="current?.body ?? ''"
        ></span>
    </div>

    <div class="admin-header-notification__meta" aria-hidden="true">
        <span
            class="admin-header-notification__timer"
            x-text="secondsRemaining + 's'"
        ></span>

        <span
            class="admin-header-notification__counter"
            x-cloak
            x-show="pendingCount > 0"
            x-text="'+' + pendingCount"
        ></span>
    </div>
</div>
@endpersist
