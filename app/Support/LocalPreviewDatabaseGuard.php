<?php

namespace App\Support;

use LogicException;

final class LocalPreviewDatabaseGuard
{
    public static function assertDisposableTestContext(): void
    {
        if (self::hasExplicitCiDisposableContext()) {
            return;
        }

        throw new LogicException(
            'Feature tests are blocked outside the explicit disposable CI database context. '
            .'The local browser preview database is persistent; use CI for Pest feature tests.',
        );
    }

    public static function assertDestructiveCommandIsAllowed(): void
    {
        if (self::hasExplicitCiDisposableContext()) {
            return;
        }

        throw new LogicException(
            'Destructive database commands are blocked outside disposable CI. '
            .'Only the explicit disposable CI database context may run it.',
        );
    }

    private static function hasExplicitCiDisposableContext(): bool
    {
        return self::environmentValue('MOELLER_LARS_PERSISTENT_PREVIEW') !== '1'
            && self::environmentValue('GITHUB_ACTIONS') === 'true'
            && self::environmentValue('MOELLER_LARS_DISPOSABLE_TEST_DATABASE') === '1';
    }

    private static function environmentValue(string $key): ?string
    {
        $value = $_SERVER[$key] ?? $_ENV[$key] ?? getenv($key);

        return $value === false || $value === null ? null : (string) $value;
    }
}
