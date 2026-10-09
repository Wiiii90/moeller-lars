<?php

namespace App\Filament\Auth;

use App\Domain\Admin\AdminNotifier;
use Filament\Actions\Action;
use Filament\Auth\MultiFactor\App\AppAuthentication;

final class AdminAppAuthentication extends AppAuthentication
{
    private const FILAMENT_NOTIFICATION_SESSION_KEY = 'filament.notifications';

    private const NOTIFICATION_BASELINE_ATTRIBUTE = 'admin.mfa.notification_baseline';

    /**
     * Keep Filament as the security/workflow authority. The project adapter only
     * routes notifications emitted by those vendor actions into the canonical
     * admin header notification surface.
     *
     * @return array<Action>
     */
    public function getActions(): array
    {
        return array_map(
            fn (Action $action): Action => $this->routeNotification($action),
            parent::getActions(),
        );
    }

    private function routeNotification(Action $action): Action
    {
        return $action
            ->before(function (): void {
                $notifications = session()->get(self::FILAMENT_NOTIFICATION_SESSION_KEY, []);

                request()->attributes->set(
                    self::NOTIFICATION_BASELINE_ATTRIBUTE,
                    is_array($notifications) ? count($notifications) : 0,
                );
            })
            ->after(function (): void {
                $notifications = session()->get(self::FILAMENT_NOTIFICATION_SESSION_KEY, []);
                if (! is_array($notifications)) {
                    return;
                }

                $baseline = request()->attributes->get(self::NOTIFICATION_BASELINE_ATTRIBUTE, 0);
                $baseline = is_int($baseline) ? max(0, $baseline) : 0;
                $newNotifications = array_slice($notifications, $baseline);

                if ($newNotifications === []) {
                    return;
                }

                session()->put(
                    self::FILAMENT_NOTIFICATION_SESSION_KEY,
                    array_slice($notifications, 0, $baseline),
                );

                foreach ($newNotifications as $notification) {
                    if (! is_array($notification)) {
                        continue;
                    }

                    $title = trim(strip_tags((string) ($notification['title'] ?? '')));
                    if ($title === '') {
                        continue;
                    }

                    $body = trim(strip_tags((string) ($notification['body'] ?? '')));
                    $status = (string) ($notification['status'] ?? 'info');

                    app(AdminNotifier::class)->notification(
                        title: $title,
                        body: $body !== '' ? $body : null,
                        status: in_array($status, ['success', 'warning', 'danger', 'info'], true)
                            ? $status
                            : 'info',
                    );
                }
            });
    }
}
