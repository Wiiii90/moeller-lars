@php
    $initialFeedback = app(\App\Domain\Admin\AdminNotifier::class)->pullQueuedFeedback();
@endphp

<div
    class="admin-notification-ticker"
    data-admin-notification-ticker
    role="status"
    aria-live="polite"
    aria-atomic="true"
    aria-relevant="additions text"
>
    <script type="application/json" data-admin-notification-initial>@json($initialFeedback)</script>

    <div class="admin-notification-ticker__viewport">
        <div class="admin-notification-ticker__message">
            <div class="admin-notification-ticker__runway" data-admin-notification-runway>
                <div class="admin-notification-ticker__copy" data-admin-notification-track>
                    <strong data-admin-notification-title></strong>
                    <span data-admin-notification-body></span>
                </div>
            </div>
            <button
                type="button"
                class="admin-notification-ticker__dismiss"
                data-admin-notification-dismiss
                aria-label="Dismiss notification"
                title="Dismiss notification"
            >
                <svg viewBox="0 0 16 16" aria-hidden="true" focusable="false">
                    <path d="M4 4l8 8M12 4l-8 8" fill="none" stroke-linecap="round" />
                </svg>
            </button>
        </div>
    </div>
</div>
