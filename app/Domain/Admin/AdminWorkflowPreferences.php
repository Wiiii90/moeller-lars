<?php

namespace App\Domain\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** One owner for per-administrator editorial creation and deletion defaults. */
final class AdminWorkflowPreferences
{
    public const DASHBOARD = 'dashboard';
    public const PAGES = 'pages';
    public const CUSTOM_PAGE = 'custom_page';
    public const HOME = 'home';
    public const JOURNAL = 'journal';

    private const DELETE_SCOPES = [self::DASHBOARD, self::PAGES, self::CUSTOM_PAGE, self::HOME];

    /** Match existing new-component defaults; Journal drafts remain unpublished. */
    private const PUBLISH_DEFAULTS = [
        self::PAGES => false,
        self::CUSTOM_PAGE => true,
        self::HOME => true,
        self::JOURNAL => false,
    ];

    public function deletionWithoutConfirmation(string $scope): bool
    {
        $this->assertDeleteScope($scope);

        return $this->value('delete', $scope) === true;
    }

    /**
     * Callers must establish that deletion is non-cascading before supplying
     * false. The default requires a confirmation, even with an enabled opt-out.
     */
    public function requiresDeletionConfirmation(string $scope, bool $hasCascadingEffects = true): bool
    {
        return $hasCascadingEffects || ! $this->deletionWithoutConfirmation($scope);
    }

    public function autoPublish(string $scope): bool
    {
        $this->assertPublishScope($scope);
        $value = $this->value('publish_new', $scope);

        return is_bool($value) ? $value : self::PUBLISH_DEFAULTS[$scope];
    }

    public function updateDeletion(string $scope, bool $withoutConfirmation): bool
    {
        $this->assertDeleteScope($scope);

        return $this->update('delete', $scope, $withoutConfirmation);
    }

    public function updateAutoPublish(string $scope, bool $autoPublish): bool
    {
        $this->assertPublishScope($scope);

        return $this->update('publish_new', $scope, $autoPublish);
    }

    private function value(string $group, string $scope): mixed
    {
        $user = auth()->user();
        $preferences = $user instanceof User ? $user->getAttribute('admin_workflow_preferences') : null;

        return is_array($preferences) && is_array($preferences[$group] ?? null)
            ? ($preferences[$group][$scope] ?? null)
            : null;
    }

    private function update(string $group, string $scope, bool $value): bool
    {
        $user = auth()->user();
        if (! $user instanceof User || ! (bool) $user->getAttribute('is_admin')) {
            throw new RuntimeException('An authenticated administrator is required to update workflow preferences.');
        }

        return DB::transaction(function () use ($user, $group, $scope, $value): bool {
            /** @var User $fresh */
            $fresh = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $preferences = $fresh->getAttribute('admin_workflow_preferences');
            $preferences = is_array($preferences) ? $preferences : [];
            $settings = $preferences[$group] ?? [];
            $settings = is_array($settings) ? $settings : [];
            $default = $group === 'publish_new' ? self::PUBLISH_DEFAULTS[$scope] : false;
            if (($settings[$scope] ?? $default) === $value) {
                return false;
            }

            $settings[$scope] = $value;
            $preferences[$group] = $settings;
            $fresh->forceFill(['admin_workflow_preferences' => $preferences])->save();
            $user->setAttribute('admin_workflow_preferences', $preferences);

            return true;
        });
    }

    private function assertDeleteScope(string $scope): void
    {
        if (! in_array($scope, self::DELETE_SCOPES, true)) {
            throw new InvalidArgumentException('Unknown admin deletion preference.');
        }
    }

    private function assertPublishScope(string $scope): void
    {
        if (! array_key_exists($scope, self::PUBLISH_DEFAULTS)) {
            throw new InvalidArgumentException('Unknown admin publication preference.');
        }
    }
}
