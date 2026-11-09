<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', fn (Blueprint $table) => $table->renameColumn('admin_delete_preferences', 'admin_workflow_preferences'));

        $pages = (bool) DB::table('public_content_settings')->where('scope', 'general')
            ->value('pages_auto_publish');
        foreach (DB::table('users')->select(['id', 'admin_workflow_preferences'])->get() as $user) {
            $deletion = json_decode((string) $user->admin_workflow_preferences, true, 512, JSON_THROW_ON_ERROR);
            DB::table('users')->where('id', $user->id)->update([
                'admin_workflow_preferences' => json_encode([
                    'delete' => $deletion,
                    'publish_new' => ['pages' => $pages],
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::table('public_content_settings', fn (Blueprint $table) => $table->dropColumn('pages_auto_publish'));
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings DROP COLUMN IF EXISTS pages_auto_publish');
        }
    }

    public function down(): void
    {
        $choices = [];
        foreach (DB::table('users')->select('admin_workflow_preferences')->get() as $user) {
            $prefs = json_decode((string) $user->admin_workflow_preferences, true, 512, JSON_THROW_ON_ERROR);
            $choices[] = ($prefs['publish_new']['pages'] ?? false) === true;
        }
        if (count(array_unique($choices)) > 1) {
            throw new RuntimeException('Cannot restore a site-wide Pages publication preference from divergent user choices.');
        }

        Schema::table('public_content_settings', fn (Blueprint $table) => $table->boolean('pages_auto_publish')->default(false));
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE committed.public_content_settings ADD COLUMN IF NOT EXISTS pages_auto_publish boolean NOT NULL DEFAULT false');
        }
        DB::table('public_content_settings')->where('scope', 'general')->update(['pages_auto_publish' => $choices[0] ?? false]);

        foreach (DB::table('users')->select(['id', 'admin_workflow_preferences'])->get() as $user) {
            $prefs = json_decode((string) $user->admin_workflow_preferences, true, 512, JSON_THROW_ON_ERROR);
            DB::table('users')->where('id', $user->id)->update([
                'admin_workflow_preferences' => json_encode($prefs['delete'] ?? [], JSON_THROW_ON_ERROR),
            ]);
        }
        Schema::table('users', fn (Blueprint $table) => $table->renameColumn('admin_workflow_preferences', 'admin_delete_preferences'));
    }
};
