<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

final class BrowserProfilingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || env('MOELLER_LARS_BROWSER_PROFILE') !== '1') {
            throw new RuntimeException('Browser profiling fixtures may only be created in the explicit disposable testing context.');
        }

        $email = trim((string) env('PLAYWRIGHT_ADMIN_EMAIL'));
        $password = (string) env('PLAYWRIGHT_ADMIN_PASSWORD');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 16) {
            throw new RuntimeException('Valid synthetic Playwright admin credentials are required.');
        }

        $admin = new User;
        $admin->forceFill([
            'name' => 'Playwright Admin',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $admin->save();
    }
}
