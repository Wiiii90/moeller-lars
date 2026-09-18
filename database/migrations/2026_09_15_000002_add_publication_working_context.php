<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publication_working_context', function (Blueprint $table): void {
            $table->unsignedTinyInteger('id')->primary();
            $table->string('operation', 24)->nullable();
            $table->foreignId('source_publication_checkpoint_id')
                ->nullable()
                ->constrained('publication_checkpoints')
                ->nullOnDelete();
            $table->timestampTz('updated_at')->nullable();
        });

        DB::table('publication_working_context')->insert([
            'id' => 1,
            'operation' => null,
            'source_publication_checkpoint_id' => null,
            'updated_at' => now(),
        ]);

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                CREATE OR REPLACE FUNCTION prevent_publication_checkpoint_delete()
                RETURNS trigger AS $$
                BEGIN
                    RAISE EXCEPTION 'publication_checkpoints are permanent history';
                END;
                $$ LANGUAGE plpgsql;

                DROP TRIGGER IF EXISTS publication_checkpoints_prevent_delete ON publication_checkpoints;
                CREATE TRIGGER publication_checkpoints_prevent_delete
                BEFORE DELETE ON publication_checkpoints
                FOR EACH ROW EXECUTE FUNCTION prevent_publication_checkpoint_delete();
            SQL);
        }
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
                DROP TRIGGER IF EXISTS publication_checkpoints_prevent_delete ON publication_checkpoints;
                DROP FUNCTION IF EXISTS prevent_publication_checkpoint_delete();
            SQL);
        }

        Schema::dropIfExists('publication_working_context');
    }
};
