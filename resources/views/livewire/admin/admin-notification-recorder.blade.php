<div
    hidden
    aria-hidden="true"
    x-data
    x-init="
        const scan = () => {
            document.querySelectorAll('.fi-no-notification').forEach((notification) => {
                if (notification.dataset.adminHistoryRecorded === '1') return

                const title = notification.querySelector('.fi-no-notification-title')?.textContent?.trim() ?? ''
                if (! title) return

                notification.dataset.adminHistoryRecorded = '1'

                const key = notification.getAttribute('wire:key') ?? ''
                const sourceId = key.includes('.notifications.')
                    ? key.split('.notifications.')[0]
                    : (window.crypto?.randomUUID?.() ?? `${Date.now()}-${title}`)
                const status = ['success', 'warning', 'danger', 'info']
                    .find((value) => notification.classList.contains(`fi-status-${value}`)) ?? 'info'
                const body = notification.querySelector('.fi-no-notification-body')?.textContent?.trim() ?? ''

                $wire.record({ sourceId, title, body, status })
            })
        }

        window.__adminNotificationHistoryObserver?.disconnect()
        scan()
        window.__adminNotificationHistoryObserver = new MutationObserver(scan)
        window.__adminNotificationHistoryObserver.observe(document.body, { childList: true, subtree: true })
    "
></div>
