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
        Schema::table('publication_checkpoints', function (Blueprint $table): void {
            $table->char('hash', 64)->nullable()->unique();
            $table->char('snapshot_hash', 64)->nullable();
            $table->char('schema_hash', 64)->nullable();
            $table->boolean('snapshot_available')->default(false);
            $table->string('operation', 24)->default('commit');
            $table->foreignId('parent_publication_checkpoint_id')
                ->nullable()
                ->constrained('publication_checkpoints')
                ->nullOnDelete();
            $table->foreignId('source_publication_checkpoint_id')
                ->nullable()
                ->constrained('publication_checkpoints')
                ->nullOnDelete();
        });

        Schema::create('publication_version_rows', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('publication_checkpoint_id')
                ->constrained('publication_checkpoints')
                ->cascadeOnDelete();
            $table->string('table_name', 64);
            $table->string('row_key', 128);
            $table->jsonb('payload');

            $table->unique(
                ['publication_checkpoint_id', 'table_name', 'row_key'],
                'publication_version_rows_checkpoint_table_row_unique',
            );
            $table->index(['table_name', 'row_key'], 'publication_version_rows_table_row_index');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        $checkpoints = DB::table('publication_checkpoints')
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();
        if ($checkpoints->isEmpty()) {
            return;
        }

        $latest = $checkpoints->last();
        $latestId = (int) $latest->id;
        $schemaHash = $this->schemaHash();
        $snapshotHash = $this->snapshotHash('committed');

        foreach (PublicationSnapshot::TABLES as $table) {
            DB::statement(
                "INSERT INTO publication_version_rows (publication_checkpoint_id, table_name, row_key, payload) SELECT ?, ?, source.id::text, to_jsonb(source) FROM committed.{$table} AS source",
                [$latestId, $table],
            );
        }

        $parentId = null;
        $parentHash = null;

        foreach ($checkpoints as $checkpoint) {
            $id = (int) $checkpoint->id;
            $hasSnapshot = $id === $latestId;
            $checkpointSnapshotHash = $hasSnapshot ? $snapshotHash : null;
            $hashInputSnapshot = $checkpointSnapshotHash ?? hash('sha256', 'legacy-checkpoint:'.$id);
            $hash = hash('sha256', implode('|', [
                $parentHash ?? '',
                $hashInputSnapshot,
                (string) ($checkpoint->admin_user_id ?? ''),
                (string) $checkpoint->published_at,
                (string) ($checkpoint->message ?? ''),
                (string) $checkpoint->change_count,
            ]));

            DB::table('publication_checkpoints')->where('id', $id)->update([
                'hash' => $hash,
                'snapshot_hash' => $checkpointSnapshotHash,
                'schema_hash' => $hasSnapshot ? $schemaHash : null,
                'snapshot_available' => $hasSnapshot,
                'operation' => (string) $checkpoint->message === 'Initial public state' ? 'initial' : 'commit',
                'parent_publication_checkpoint_id' => $parentId,
            ]);

            $parentId = $id;
            $parentHash = $hash;
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('publication_version_rows');

        Schema::table('publication_checkpoints', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('source_publication_checkpoint_id');
            $table->dropConstrainedForeignId('parent_publication_checkpoint_id');
            $table->dropColumn([
                'hash',
                'snapshot_hash',
                'schema_hash',
                'snapshot_available',
                'operation',
            ]);
        });
    }

    private function schemaHash(): string
    {
        $rows = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->whereIn('table_name', PublicationSnapshot::TABLES)
            ->orderBy('table_name')
            ->orderBy('ordinal_position')
            ->get([
                'table_name',
                'column_name',
                'ordinal_position',
                'data_type',
                'udt_name',
                'is_nullable',
                'character_maximum_length',
                'numeric_precision',
                'numeric_scale',
                'datetime_precision',
                'is_identity',
                'identity_generation',
                'is_generated',
            ]);

        return hash('sha256', json_encode($rows->map(static fn (object $row): array => (array) $row)->all(), JSON_THROW_ON_ERROR));
    }

    private function snapshotHash(string $schema): string
    {
        $context = hash_init('sha256');

        foreach (PublicationSnapshot::TABLES as $table) {
            hash_update($context, "table:{$table}\n");
            $rows = DB::select("SELECT id::text AS row_key, to_jsonb(source)::text AS payload FROM {$schema}.{$table} AS source ORDER BY id");

            foreach ($rows as $row) {
                hash_update($context, (string) $row->row_key."\n".(string) $row->payload."\n");
            }
        }

        return hash_final($context);
    }
};
