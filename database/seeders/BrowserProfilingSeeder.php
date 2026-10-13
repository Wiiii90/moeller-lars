<?php

namespace Database\Seeders;

use App\Models\ContactMessage;
use App\Models\SiteSection;
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

        // Required browser interaction fixtures: never silently skip selection tests.
        foreach ([1, 2] as $number) {
            $section = SiteSection::query()->create([
                'type' => SiteSection::TYPE_CUSTOM,
                'template' => null,
                'title' => 'Browser fixture page '.$number,
                'navigation_label' => 'Browser fixture page '.$number,
                'slug' => 'browser-fixture-page-'.$number,
                'state' => 'hidden',
                'position' => 1000 + $number * 10,
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);
            $section->customPageSetting()->create(['blocks' => []]);

            ContactMessage::query()->create([
                'sender_name' => 'Browser fixture '.$number,
                'sender_email' => 'fixture-'.$number.'@example.test',
                'message' => 'Disposable fixture for dashboard selection',
                'mail_delivery_status' => ContactMessage::DELIVERY_PENDING,
            ]);
        }
    }
}
