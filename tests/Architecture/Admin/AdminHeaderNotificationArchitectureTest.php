<?php

use Illuminate\Support\Facades\File;

it('keeps the admin header Notification centralized bounded and SPA-persistent', function (): void {
    $view = file_get_contents(resource_path('views/filament/partials/admin-header-notification.blade.php'));
    $styles = file_get_contents(resource_path('css/admin/notifications.css'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $icons = file_get_contents(app_path('Filament/Support/AdminIcon.php'));

    expect($provider)
        ->toContain('PanelsRenderHook::TOPBAR_START')
        ->toContain("view('filament.partials.admin-header-notification')")
        ->toContain('->spa()')
        ->toContain('->spaUrlExceptions')
        ->and($view)
        ->toContain("@persist('admin-header-notification')")
        ->toContain("request()->hasHeader('X-Livewire-Navigate')")
        ->toContain('queueLimit: 8')
        ->toContain('seenLimit: 48')
        ->toContain('displayDurationMs: 8000')
        ->toContain('exitDurationMs: 700')
        ->toContain('this.queue.push(notification)')
        ->toContain('this.queue.shift()')
        ->toContain('window.setTimeout')
        ->toContain('window.clearTimeout')
        ->toContain("secondsRemaining + 's'")
        ->toContain("x-text="'+' + pendingCount"")
        ->toContain('AdminIcon::NotificationSuccess->mini()')
        ->toContain('AdminIcon::NotificationWarning->mini()')
        ->toContain('AdminIcon::NotificationDanger->mini()')
        ->toContain('AdminIcon::NotificationInfo->mini()')
        ->toContain('x-bind:data-phase="phase"')
        ->toContain('x-bind:data-message-revision="messageRevision"')
        ->toContain('this.$nextTick(() => this.promoteNext())')
        ->toContain('const initialNotifications = @js($initialNotifications)')
        ->toContain('initialNotifications.forEach((notification) => this.accept(notification))')
        ->not->toContain('__adminHeaderFeedbackRuntime')
        ->not->toContain('__adminHeaderNotificationRuntime')
        ->not->toContain('setInterval')
        ->not->toContain('requestAnimationFrame')
        ->not->toContain('MutationObserver')
        ->not->toContain('ResizeObserver')
        ->not->toContain('wire:poll')
        ->not->toContain('fetch(')
        ->not->toContain('Livewire.dispatch')
        ->not->toContain('$wire')
        ->and($styles)
        ->toContain('@keyframes admin-header-notification-materialize')
        ->toContain('@keyframes admin-header-notification-dematerialize')
        ->toContain('@keyframes admin-header-notification-beam-in')
        ->toContain('@keyframes admin-header-notification-content-in')
        ->toContain('@keyframes admin-header-notification-content-out')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('.admin-header-notification__timer')
        ->toContain('.admin-header-notification__counter')
        ->and($icons)
        ->toContain("case NotificationSuccess = 'heroicon-o-check-badge'")
        ->toContain("case NotificationWarning = 'heroicon-o-exclamation-triangle'")
        ->toContain("case NotificationDanger = 'heroicon-o-x-circle'")
        ->toContain("case NotificationInfo = 'heroicon-o-information-circle'");

    $renderOccurrences = 0;

    foreach ([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))] as $file) {
        $renderOccurrences += substr_count(
            file_get_contents($file->getRealPath()),
            'filament.partials.admin-header-notification',
        );
    }

    expect($renderOccurrences)->toBe(1);
});

it('reuses Activity change and details for successful transient Notifications', function (): void {
    $audit = file_get_contents(app_path('Domain/Admin/AdminAuditService.php'));
    $context = file_get_contents(app_path('Domain/Admin/AdminActivityNotificationContext.php'));
    $notifier = file_get_contents(app_path('Domain/Admin/AdminNotifier.php'));

    expect($audit)
        ->toContain('AdminActivityPresentation $activityPresentation')
        ->toContain('AdminActivityNotificationContext $notificationContext')
        ->toContain('$this->notificationContext->push');

    expect($context)
        ->toContain('consumeRecorded')
        ->toContain('AuditEvent::query()')
        ->toContain('MAX_MESSAGES = 9');

    expect($notifier)
        ->toContain('$this->notificationContext->consumeRecorded()')
        ->toContain("(string) \$activityMessage['change']")
        ->toContain("\$activityMessage['details'] ?? null")
        ->toContain("dispatch('admin-header-notification'")
        ->not->toContain('Filament\\Notifications')
        ->not->toContain('function feedback(');
});
