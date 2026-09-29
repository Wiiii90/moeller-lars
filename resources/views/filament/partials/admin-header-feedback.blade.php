@php
    $initialFeedback = app(\App\Domain\Admin\AdminNotifier::class)->pullPendingFeedback();
@endphp

<div
    class="admin-header-feedback"
    data-admin-header-feedback
    data-status="info"
    role="status"
    aria-live="polite"
    aria-atomic="true"
    aria-relevant="additions text"
>
    <script type="application/json" data-admin-header-feedback-initial>@json($initialFeedback)</script>

    <div class="admin-header-feedback__content">
        <strong
            class="admin-header-feedback__title"
            data-admin-header-feedback-title
        ></strong>
        <span
            class="admin-header-feedback__body"
            data-admin-header-feedback-body
            hidden
        ></span>
    </div>

    <span
        class="admin-header-feedback__counter"
        data-admin-header-feedback-counter
        aria-hidden="true"
        hidden
    ></span>
</div>
