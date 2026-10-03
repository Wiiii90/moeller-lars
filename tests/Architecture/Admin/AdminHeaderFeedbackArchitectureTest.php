<?php

use Illuminate\Support\Facades\File;

it('keeps admin header feedback centralized bounded and presentation only', function (): void {
    $view = file_get_contents(resource_path('views/filament/partials/admin-header-feedback.blade.php'));
    $styles = file_get_contents(resource_path('css/admin/notifications.css'));
    $provider = file_get_contents(app_path('Providers/Filament/AdminPanelProvider.php'));
    $icons = file_get_contents(app_path('Filament/Support/AdminIcon.php'));

    expect($provider)
        ->toContain('PanelsRenderHook::TOPBAR_START')
        ->toContain("view('filament.partials.admin-header-feedback')")
        ->and($view)
        ->toContain('queueLimit: 8')
        ->toContain('seenLimit: 48')
        ->toContain('displayDurationMs: 8000')
        ->toContain('exitDurationMs: 700')
        ->toContain('runtime.queue.push(notification)')
        ->toContain('runtime.queue.shift()')
        ->toContain('window.setTimeout')
        ->toContain('window.clearTimeout')
        ->toContain("secondsRemaining + 's'")
        ->toContain("x-text=\"'+' + pendingCount\"")
        ->toContain('AdminIcon::FeedbackSuccess->mini()')
        ->toContain('AdminIcon::FeedbackWarning->mini()')
        ->toContain('AdminIcon::FeedbackDanger->mini()')
        ->toContain('AdminIcon::FeedbackInfo->mini()')
        ->toContain('x-bind:data-phase="phase"')
        ->toContain('x-bind:data-message-revision="messageRevision"')
        ->toContain('this.$nextTick(() => this.promoteNext())')
        ->toContain('this.syncFromRuntime();')
        ->toContain('const initialFeedback = @js($initialFeedback);')
        ->toContain('initialFeedback.forEach((notification) => this.accept(notification))')
        ->not->toContain('this.syncFromRuntime()'.PHP_EOL.'            @js($initialFeedback)')
        ->not->toContain('setInterval')
        ->not->toContain('requestAnimationFrame')
        ->not->toContain('MutationObserver')
        ->not->toContain('ResizeObserver')
        ->not->toContain('wire:poll')
        ->not->toContain('fetch(')
        ->not->toContain('Livewire.dispatch')
        ->not->toContain('$wire')
        ->and($styles)
        ->toContain('@keyframes admin-header-feedback-materialize')
        ->toContain('@keyframes admin-header-feedback-dematerialize')
        ->toContain('@keyframes admin-header-feedback-beam-in')
        ->toContain('@keyframes admin-header-feedback-content-in')
        ->toContain('@keyframes admin-header-feedback-content-out')
        ->toContain('@media (prefers-reduced-motion: reduce)')
        ->toContain('.admin-header-feedback__timer')
        ->toContain('.admin-header-feedback__counter')
        ->and($icons)
        ->toContain("case FeedbackSuccess = 'heroicon-o-exclamation-circle'")
        ->toContain("case FeedbackWarning = 'heroicon-o-exclamation-triangle'")
        ->toContain("case FeedbackDanger = 'heroicon-o-x-circle'")
        ->toContain("case FeedbackInfo = 'heroicon-o-information-circle'");

    $renderOccurrences = 0;

    foreach ([...File::allFiles(app_path()), ...File::allFiles(resource_path('views'))] as $file) {
        $renderOccurrences += substr_count(
            file_get_contents($file->getRealPath()),
            'filament.partials.admin-header-feedback',
        );
    }

    expect($renderOccurrences)->toBe(1);
});

it('reuses Activity change and details for successful mutation feedback', function (): void {
    $audit = file_get_contents(app_path('Domain/Admin/AdminAuditService.php'));
    $context = file_get_contents(app_path('Domain/Admin/AdminFeedbackContext.php'));
    $notifier = file_get_contents(app_path('Domain/Admin/AdminNotifier.php'));

    expect($audit)
        ->toContain('AdminActivityPresentation $activityPresentation')
        ->toContain('AdminFeedbackContext $feedbackContext')
        ->toContain('$this->feedbackContext->push');

    expect($context)
        ->toContain('consumeRecorded')
        ->toContain('AuditEvent::query()')
        ->toContain('MAX_MESSAGES = 9');

    expect($notifier)
        ->toContain('$this->feedbackContext->consumeRecorded()')
        ->toContain("(string) \$activityMessage['change']")
        ->toContain("\$activityMessage['details'] ?? null")
        ->not->toContain('Filament\\Notifications');
});
