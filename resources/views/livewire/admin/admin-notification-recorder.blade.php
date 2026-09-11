<div
    hidden
    aria-hidden="true"
    x-data
    x-init="
        const scan = () => {
            document.querySelectorAll('.fi-no-notification').forEach((notification) => {
                if (notification.dataset.adminHistoryRecorded === '1') return

                const title = notification.querySelector('.fi-no-notification-title, [class*=notification-title]')?.textContent?.trim() ?? ''
                if (! title) return

                notification.dataset.adminHistoryRecorded = '1'

                const key = notification.getAttribute('wire:key')?.trim() ?? ''
                const sourceId = key || (window.crypto?.randomUUID?.() ?? `${Date.now()}-${title}`)
                const classText = [notification, ...notification.querySelectorAll('*')]
                    .map((element) => typeof element.className === 'string' ? element.className : '')
                    .join(' ')
                    .toLowerCase()
                const status = ['success', 'warning', 'danger', 'info']
                    .find((value) => classText.includes(value)) ?? 'info'
                const body = notification.querySelector('.fi-no-notification-body, [class*=notification-body]')?.textContent?.trim() ?? ''

                $wire.record({ sourceId, title, body, status })
            })
        }

        window.__adminNotificationHistoryObserver?.disconnect()
        scan()
        window.__adminNotificationHistoryObserver = new MutationObserver(scan)
        window.__adminNotificationHistoryObserver.observe(document.body, { childList: true, subtree: true })
    "
></div>
