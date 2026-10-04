@php
    $initialNotifications = request()->hasHeader('X-Livewire-Navigate')
        ? []
        : app(\App\Domain\Admin\AdminNotifier::class)->pullPendingNotifications();
@endphp

@persist('admin-header-notification')
<div
    class="admin-header-notification"
    data-admin-header-notification
    data-status="info"
    data-phase="idle"
    data-message-revision="0"
    hidden
    role="status"
    aria-live="polite"
    aria-atomic="true"
    aria-relevant="additions text"
>
    <script type="application/json" data-admin-header-notification-initial>@json($initialNotifications)</script>

    <span class="admin-header-notification__icon" aria-hidden="true">
        <span data-admin-header-notification-icon="success" hidden>
            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::NotificationSuccess->mini()" />
        </span>
        <span data-admin-header-notification-icon="warning" hidden>
            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::NotificationWarning->mini()" />
        </span>
        <span data-admin-header-notification-icon="danger" hidden>
            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::NotificationDanger->mini()" />
        </span>
        <span data-admin-header-notification-icon="info" hidden>
            <x-filament::icon :icon="\App\Filament\Support\AdminIcon::NotificationInfo->mini()" />
        </span>
    </span>

    <div class="admin-header-notification__content">
        <strong
            class="admin-header-notification__title"
            data-admin-header-notification-title
        ></strong>

        <span
            class="admin-header-notification__separator"
            data-admin-header-notification-separator
            hidden
            aria-hidden="true"
        >:</span>

        <span
            class="admin-header-notification__body"
            data-admin-header-notification-body
            hidden
        ></span>
    </div>

    <div class="admin-header-notification__meta" aria-hidden="true">
        <span
            class="admin-header-notification__timer"
            data-admin-header-notification-timer
        ></span>

        <span
            class="admin-header-notification__counter"
            data-admin-header-notification-counter
            hidden
        ></span>
    </div>
</div>
@endpersist
