<?php

namespace App\Domain\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** User-specific opt-outs never override cascading deletion safety. */
final class AdminDeletionPreferences
{
    public const DASHBOARD = 'dashboard';
    public const PAGES = 'pages';

    private const SCOPES = [self::DASHBOARD, self::PAGES];

    public function withoutConfirmation(string $scope): bool
    {
        $this->assertScope($scope);
        $user = auth()->user();
        $preferences = $user instanceof User ? $user->getAttribute('admin_delete_preferences') : null;

        return is_array($preferences) && ($preferences[$scope] ?? false) === true;
    }

    public function requiresConfirmation(string $scope, bool $hasCascadingEffects = false): bool
    {
        return $hasCascadingEffects || ! $this->withoutConfirmation($scope);
    }

    public function update(string $scope, bool $withoutConfirmation): bool
    {
        $this->assertScope($scope);
        $user = auth()->user();
        if (! $user instanceof User || ! (bool) $user->getAttribute('is_admin')) {
            throw new RuntimeException('An authenticated administrator is required to update deletion preferences.');
        }

        return DB::transaction(function () use ($user, $scope, $withoutConfirmation): bool {
            /** @var User $fresh */
            $fresh = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $preferences = $fresh->getAttribute('admin_delete_preferences');
            $preferences = is_array($preferences) ? $preferences : [];
            if (($preferences[$scope] ?? false) === $withoutConfirmation) {
                return false;
            }

            $preferences[$scope] = $withoutConfirmation;
            $fresh->forceFill(['admin_delete_preferences' => $preferences])->save();
            $user->setAttribute('admin_delete_preferences', $preferences);

            return true;
        });
    }

    private function assertScope(string $scope): void
    {
        if (! in_array($scope, self::SCOPES, true)) {
            throw new InvalidArgumentException('Unknown admin deletion preference.');
        }
    }
}
