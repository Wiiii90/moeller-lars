<?php

namespace App\Domain\Admin;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use RuntimeException;

/** One per-administrator source of truth for automatic publication defaults. */
final class AdminAutoPublishPreferences
{
    public const PAGES = 'pages';
    public const CUSTOM_PAGE = 'custom_page';
    public const HOME = 'home';
    public const JOURNAL = 'journal';
    public const GALLERY = 'gallery';

    /** Preserve existing new-content defaults, with drafts in Journal/Gallery. */
    private const DEFAULTS = [
        self::PAGES => false,
        self::CUSTOM_PAGE => true,
        self::HOME => true,
        self::JOURNAL => false,
        self::GALLERY => false,
    ];

    public function enabled(string $scope): bool
    {
        $this->assertScope($scope);
        $user = auth()->user();
        $settings = $user instanceof User ? $user->getAttribute('admin_auto_publish_preferences') : null;
        $value = is_array($settings) ? ($settings[$scope] ?? null) : null;

        return is_bool($value) ? $value : self::DEFAULTS[$scope];
    }

    public function update(string $scope, bool $enabled): bool
    {
        $this->assertScope($scope);
        $user = auth()->user();
        if (! $user instanceof User || ! (bool) $user->getAttribute('is_admin')) {
            throw new RuntimeException('An authenticated administrator is required to update publication preferences.');
        }

        return DB::transaction(function () use ($user, $scope, $enabled): bool {
            /** @var User $fresh */
            $fresh = User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
            $settings = $fresh->getAttribute('admin_auto_publish_preferences');
            $settings = is_array($settings) ? $settings : [];
            if (($settings[$scope] ?? self::DEFAULTS[$scope]) === $enabled) {
                return false;
            }

            $settings[$scope] = $enabled;
            $fresh->forceFill(['admin_auto_publish_preferences' => $settings])->save();
            $user->setAttribute('admin_auto_publish_preferences', $settings);

            return true;
        });
    }

    private function assertScope(string $scope): void
    {
        if (! array_key_exists($scope, self::DEFAULTS)) {
            throw new InvalidArgumentException('Unknown automatic publication preference.');
        }
    }
}
