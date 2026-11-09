<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->json('admin_delete_preferences')->default('{}');
        });

        $pages = (bool) DB::table('public_content_settings')
            ->where('scope', 'general')->value('pages_delete_without_confirmation');
        foreach (DB::table('users')->select(['id', 'dashboard_delete_without_confirmation'])->get() as $user) {
            DB::table('users')->where('id', $user->id)->update([
                'admin_delete_preferences' => json_encode([
                    'dashboard' => (bool) $user->dashboard_delete_without_confirmation,
                    'pages' => $pages,
                ], JSON_THROW_ON_ERROR),
            ]);
        }
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('dashboard_delete_without_confirmation'));
        Schema::table('public_content_settings', fn (Blueprint $table) => $table->dropColumn('pages_delete_without_confirmation'));
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS pages_delete_without_confirmation');
        }
    }

    public function down(): void
    {
        $choices = [];
        foreach (DB::table('users')->select('admin_delete_preferences')->get() as $user) {
            $prefs = json_decode((string) $user->admin_delete_preferences, true, 512, JSON_THROW_ON_ERROR);
            $choices[] = ($prefs['pages'] ?? false) === true;
        }
        if (count(array_unique($choices)) > 1) {
            throw new RuntimeException('Cannot restore a single site-wide Pages preference from divergent user choices.');
        }

        Schema::table('users', fn (Blueprint $table) => $table->boolean('dashboard_delete_without_confirmation')->default(false));
        Schema::table('public_content_settings', fn (Blueprint $table) => $table->boolean('pages_delete_without_confirmation')->default(false));
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS pages_delete_without_confirmation boolean NOT NULL DEFAULT false');
        }
        foreach (DB::table('users')->select(['id', 'admin_delete_preferences'])->get() as $user) {
            $prefs = json_decode((string) $user->admin_delete_preferences, true, 512, JSON_THROW_ON_ERROR);
            DB::table('users')->where('id', $user->id)->update([
                'dashboard_delete_without_confirmation' => ($prefs['dashboard'] ?? false) === true,
            ]);
        }
        DB::table('public_content_settings')->where('scope', 'general')->update([
            'pages_delete_without_confirmation' => $choices[0] ?? false,
        ]);
        Schema::table('users', fn (Blueprint $table) => $table->dropColumn('admin_delete_preferences'));
    }
};
