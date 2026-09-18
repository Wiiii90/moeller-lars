<?php

use App\Support\AdminPasswordPolicy;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

it('keeps local and testing password changes lightweight while deployment environments stay strict', function (): void {
    $local = AdminPasswordPolicy::rule(strict: false)->appliedRules();
    $strict = AdminPasswordPolicy::rule(strict: true)->appliedRules();
    $default = Password::default()->appliedRules();

    expect($local['min'])->toBe(8)
        ->and($local['max'])->toBe(128)
        ->and($local['uncompromised'])->toBeFalse()
        ->and($strict['min'])->toBe(15)
        ->and($strict['max'])->toBe(128)
        ->and($strict['uncompromised'])->toBeTrue()
        ->and($strict['mixedCase'])->toBeFalse()
        ->and($strict['numbers'])->toBeFalse()
        ->and($strict['symbols'])->toBeFalse()
        ->and($default['min'])->toBe(8)
        ->and($default['max'])->toBe(128)
        ->and($default['uncompromised'])->toBeFalse();
});

it('blocks administration-specific guessable passwords in strict environments', function (): void {
    app()->instance(UncompromisedVerifier::class, new class implements UncompromisedVerifier
    {
        public function verify($data): bool
        {
            return true;
        }
    });

    $blocked = Validator::make(
        ['password' => 'LarsMoeller2026!'],
        ['password' => AdminPasswordPolicy::rule(strict: true)],
    );
    $allowed = Validator::make(
        ['password' => 'walnut-orbit-lamp-falcon-2026'],
        ['password' => AdminPasswordPolicy::rule(strict: true)],
    );

    expect($blocked->fails())->toBeTrue()
        ->and($allowed->passes())->toBeTrue();
});
