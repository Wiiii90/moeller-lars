const DISPLAY_DURATION_MS = 8000
const EXIT_DURATION_MS = 700
const QUEUE_LIMIT = 8
const SEEN_LIMIT = 48

const shell = document.querySelector('[data-admin-header-notification]')

if (shell) {
    const title = shell.querySelector('[data-admin-header-notification-title]')
    const separator = shell.querySelector('[data-admin-header-notification-separator]')
    const body = shell.querySelector('[data-admin-header-notification-body]')
    const timer = shell.querySelector('[data-admin-header-notification-timer]')
    const counter = shell.querySelector('[data-admin-header-notification-counter]')
    const icons = Array.from(shell.querySelectorAll('[data-admin-header-notification-icon]'))

    let current = null
    let queue = []
    let seen = []
    let expiresAt = null
    let phase = 'idle'
    let revision = 0
    let secondsRemaining = 0
    let lifecycleTimer = null
    let countdownTimer = null

    function cancelLifecycle() {
        if (lifecycleTimer === null) return

        window.clearTimeout(lifecycleTimer)
        lifecycleTimer = null
    }

    function cancelCountdown() {
        if (countdownTimer === null) return

        window.clearTimeout(countdownTimer)
        countdownTimer = null
    }

    function cancelTimers() {
        cancelLifecycle()
        cancelCountdown()
    }

    function render() {
        const visible = current !== null

        shell.hidden = ! visible
        shell.dataset.status = current?.status ?? 'info'
        shell.dataset.phase = phase
        shell.dataset.messageRevision = String(revision)

        if (! visible) {
            title.textContent = ''
            body.textContent = ''
            body.hidden = true
            separator.hidden = true
            timer.textContent = ''
            counter.textContent = ''
            counter.hidden = true

            for (const icon of icons) icon.hidden = true

            return
        }

        title.textContent = current.title ?? ''

        const bodyText = current.body ?? ''
        body.textContent = bodyText
        body.hidden = bodyText === ''
        separator.hidden = bodyText === ''

        timer.textContent = `${secondsRemaining}s`

        const pendingCount = queue.length
        counter.textContent = pendingCount > 0 ? `+${pendingCount}` : ''
        counter.hidden = pendingCount === 0

        const status = ['success', 'warning', 'danger'].includes(current.status)
            ? current.status
            : 'info'

        for (const icon of icons) {
            icon.hidden = icon.dataset.adminHeaderNotificationIcon !== status
        }
    }

    function updateCountdown() {
        cancelCountdown()

        if (current === null || expiresAt === null) {
            secondsRemaining = 0
            render()
            return
        }

        const remainingMs = Math.max(0, expiresAt - Date.now())
        secondsRemaining = Math.ceil(remainingMs / 1000)
        render()

        if (remainingMs <= 0) return

        countdownTimer = window.setTimeout(
            updateCountdown,
            Math.min(250, remainingMs),
        )
    }

    function finishCurrent(expectedId) {
        if (current !== null && String(current.id ?? '') !== expectedId) return

        current = null
        expiresAt = null
        phase = 'idle'
        secondsRemaining = 0
        cancelTimers()
        render()

        if (queue.length > 0) {
            lifecycleTimer = window.setTimeout(promoteNext, 0)
        }
    }

    function beginLeave(expectedId) {
        if (current === null || String(current.id ?? '') !== expectedId) return

        cancelCountdown()
        cancelLifecycle()
        secondsRemaining = 0
        phase = 'leaving'
        render()

        const exitDelay = window.matchMedia('(prefers-reduced-motion: reduce)').matches
            ? 0
            : EXIT_DURATION_MS

        lifecycleTimer = window.setTimeout(
            () => finishCurrent(expectedId),
            exitDelay,
        )
    }

    function armLifecycle() {
        cancelLifecycle()

        if (current === null || expiresAt === null) return

        const currentId = String(current.id ?? '')
        const remainingMs = expiresAt - Date.now()

        if (remainingMs <= 0) {
            beginLeave(currentId)
            return
        }

        lifecycleTimer = window.setTimeout(
            () => beginLeave(currentId),
            remainingMs,
        )
    }

    function startNotification(notification) {
        current = notification
        expiresAt = Date.now() + DISPLAY_DURATION_MS
        secondsRemaining = Math.ceil(DISPLAY_DURATION_MS / 1000)
        revision += 1
        phase = 'entering'

        render()
        armLifecycle()
        updateCountdown()
    }

    function promoteNext() {
        lifecycleTimer = null

        const next = queue.shift() ?? null
        if (next === null) {
            render()
            return
        }

        startNotification(next)
    }

    function accept(notification) {
        if (! notification || typeof notification !== 'object') return

        const id = String(notification.id ?? '')
        if (id !== '' && seen.includes(id)) return

        if (id !== '') {
            seen.push(id)
            if (seen.length > SEEN_LIMIT) {
                seen = seen.slice(-SEEN_LIMIT)
            }
        }

        const normalized = {
            id,
            title: String(notification.title ?? ''),
            body: notification.body === null || notification.body === undefined
                ? ''
                : String(notification.body),
            status: String(notification.status ?? 'info'),
        }

        if (current === null) {
            startNotification(normalized)
            return
        }

        if (queue.length >= QUEUE_LIMIT) {
            queue.shift()
        }

        queue.push(normalized)
        render()
    }

    window.addEventListener('admin-header-notification', (event) => {
        accept(event.detail?.notification ?? event.detail)
    })

    const initial = shell.querySelector('[data-admin-header-notification-initial]')
    if (initial) {
        try {
            const notifications = JSON.parse(initial.textContent || '[]')
            if (Array.isArray(notifications)) {
                notifications.forEach(accept)
            }
        } catch (error) {
            console.error('Invalid initial admin Notification payload.', error)
        }

        initial.remove()
    }
}
