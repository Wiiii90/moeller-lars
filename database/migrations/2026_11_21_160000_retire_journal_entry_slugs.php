<?php

use App\Domain\Publication\PublicationSnapshot;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Keep historical slugs only for existing Blog redirect URLs and publication history.
        if (DB::connection()->getDriverName() !== 'pgsql') {
            foreach (['blog_posts', 'exhibitions'] as $table) {
                Schema::table($table, static function (Blueprint $schema): void {
                    $schema->string('slug')->nullable()->change();
                });
            }

            return;
        }

        DB::transaction(static function (): void {
            DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
            foreach (['public', 'committed'] as $schema) {
                foreach (['blog_posts', 'exhibitions'] as $table) {
                    DB::statement("ALTER TABLE {$schema}.{$table} ALTER COLUMN slug DROP NOT NULL");
                }
            }
        });
    }

    public function down(): void
    {
        // Forward only: new Journal entries have no entry slug.
    }
};
