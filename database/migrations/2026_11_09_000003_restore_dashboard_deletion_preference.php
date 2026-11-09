<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep the sole deletion preference with Dashboard; the previous
        // workflow JSON held opt-outs for other sections, now intentionally retired.
        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('dashboard_delete_without_confirmation')->default(false);
        });

        foreach (DB::table('users')->select(['id', 'admin_workflow_preferences'])->get() as $user) {
            $preferences = json_decode((string) $user->admin_workflow_preferences, true, 512, JSON_THROW_ON_ERROR);
            $autoPublish = $preferences['publish_new'] ?? [];
            DB::table('users')->where('id', $user->id)->update([
                'dashboard_delete_without_confirmation' => ($preferences['delete']['dashboard'] ?? false) === true,
                'admin_workflow_preferences' => json_encode(is_array($autoPublish) ? $autoPublish : [], JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('admin_workflow_preferences', 'admin_auto_publish_preferences');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->renameColumn('admin_auto_publish_preferences', 'admin_workflow_preferences');
        });

        foreach (DB::table('users')->select(['id', 'admin_workflow_preferences', 'dashboard_delete_without_confirmation'])->get() as $user) {
            $autoPublish = json_decode((string) $user->admin_workflow_preferences, true, 512, JSON_THROW_ON_ERROR);
            DB::table('users')->where('id', $user->id)->update([
                'admin_workflow_preferences' => json_encode([
                    'publish_new' => is_array($autoPublish) ? $autoPublish : [],
                    'delete' => ['dashboard' => (bool) $user->dashboard_delete_without_confirmation],
                ], JSON_THROW_ON_ERROR),
            ]);
        }

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('dashboard_delete_without_confirmation');
        });
    }
};
