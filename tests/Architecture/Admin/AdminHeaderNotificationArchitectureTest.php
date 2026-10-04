<?php

use Illuminate\Support\Facades\File;

it('keeps one persisted admin Notification shell runtime', function (): void {
    $view = file_get_contents(resource_path('views/filament/partials/admin-header-notification.blade.php'));
    $runtime = file_get_contents(resource_path('js/admin-notifications.js'));
    $viz = file_get_contents(resource_path('js/admin-viz.js'));
    $styles = file_get_contents(resource_path('css/admin/notifications.css'));
    $adminCss = file_get_contents(resource_path('css/admin.css'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $icons = file_get_contents(app_path('Filament/Support/AdminIcon.php'));

    expect($provider)
        ->toContain('PanelsRenderHook::BODY_START')
        ->toContain("view('filament.partials.admin-header-notification')")
        ->not->toContain("view('filament.partials.admin-modal-bootstrap')")
        ->and($view)
        ->toContain("@persist('admin-header-notification')")
        ->toContain('data-admin-header-notification')
        ->toContain('data-admin-header-notification-initial')
        ->toContain('data-admin-header-notification-title')
        ->toContain('data-admin-header-notification-timer')
        ->not->toContain('x-data=')
        ->not->toContain('x-on:admin-header-notification')
        ->and($runtime)
        ->toContain('const DISPLAY_DURATION_MS = 8000')
        ->toContain('const EXIT_DURATION_MS = 700')
        ->toContain('const QUEUE_LIMIT = 8')
        ->toContain('const SEEN_LIMIT = 48')
        ->toContain("window.addEventListener('admin-header-notification'")
        ->toContain('queue.push(normalized)')
        ->toContain('queue.shift()')
        ->toContain('window.setTimeout')
        ->toContain('window.clearTimeout')
        ->not->toContain('setInterval')
        ->not->toContain('MutationObserver')
        ->not->toContain('ResizeObserver')
        ->not->toContain('__adminHeader')
        ->not->toContain('Livewire.dispatch')
        ->and($viz)
        ->toContain("import './admin-notifications.js';")
        ->not->toContain('admin-modal-scroll')
        ->and($styles)
        ->toContain('@keyframes admin-header-notification-materialize')
        ->toContain('@keyframes admin-header-notification-dematerialize')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->and($adminCss)
        ->toContain('--admin-header-notification-edge-gap: .75rem;')
        ->not->toContain('--admin-header-feedback-edge-gap')
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

it('reuses Activity change and details for durable Notifications', function (): void {
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
        ->not->toContain('public function inbox(')
        ->not->toContain('public function both(')
        ->not->toContain('Filament\\Notifications')
        ->not->toContain('function feedback(');
});
