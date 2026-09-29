@php
    $initialFeedback = app(\App\Domain\Admin\AdminNotifier::class)->pullQueuedFeedback();
@endphp

<div
    class="admin-notification-ticker"
    data-admin-notification-ticker
    data-status="info"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    aria-relevant="additions text"
>
    <script type="application/json" data-admin-notification-initial>@json($initialFeedback)</script>

    <div class="admin-notification-ticker__viewport">
        <div class="admin-notification-ticker__message">
            <div class="admin-notification-ticker__content">
                <strong
                    class="admin-notification-ticker__title"
                    data-admin-notification-title
                ></strong>
                <span
                    class="admin-notification-ticker__body"
                    data-admin-notification-body
                    hidden
                ></span>
            </div>

            <div class="admin-notification-ticker__counter-slot" aria-hidden="true">
                <span
                    class="admin-notification-ticker__counter"
                    data-admin-notification-counter
                    hidden
                ></span>
            </div>
        </div>
    </div>
</div>
