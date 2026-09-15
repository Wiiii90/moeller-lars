<?php

namespace App\Support;

use Closure;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;

final class AdminPasswordPolicy
{
    public const LOCAL_MINIMUM_LENGTH = 8;
    public const STRICT_MINIMUM_LENGTH = 15;
    public const MAXIMUM_LENGTH = 128;

    private const CONTEXT_VALUES = [
        'admin',
        'administrator',
        'larsmoller',
        'larsmoeller',
        'mollerlars',
        'moellerlars',
        'larsmolleradmin',
        'larsmoelleradmin',
        'mollerlarsadmin',
        'moellerlarsadmin',
        'larsmollerde',
        'larsmoellerde',
        'mollerlarsde',
        'moellerlarsde',
    ];

    public static function rule(?bool $strict = null): Password
    {
        $strict ??= self::isStrictEnvironment();

        $rule = Password::min($strict ? self::STRICT_MINIMUM_LENGTH : self::LOCAL_MINIMUM_LENGTH)
            ->max(self::MAXIMUM_LENGTH);

        if (! $strict) {
            return $rule;
        }

        return $rule
            ->uncompromised()
            ->rules([self::contextSpecificRule()]);
    }

    public static function isStrictEnvironment(): bool
    {
        return ! app()->environment(['local', 'testing']);
    }

    private static function contextSpecificRule(): Closure
    {
        return static function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_string($value)) {
                return;
            }

            $normalized = self::normalize($value);
            $withoutTrailingDigits = preg_replace('/\d+$/', '', $normalized) ?? $normalized;

            if (
                in_array($normalized, self::CONTEXT_VALUES, true)
                || in_array($withoutTrailingDigits, self::CONTEXT_VALUES, true)
            ) {
                $fail('The password is too easy to guess for this administration.');
            }
        };
    }

    private static function normalize(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', '')
            ->toString();
    }
}
