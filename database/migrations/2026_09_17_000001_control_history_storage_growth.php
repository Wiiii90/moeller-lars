<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->index('expires_at', 'admin_action_receipts_expires_at_index');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::table('admin_action_receipts')
            ->where('expires_at', '<=', now())
            ->delete();

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prune_expired_admin_action_receipts_on_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    DELETE FROM admin_action_receipts
    WHERE expires_at <= CURRENT_TIMESTAMP;

    RETURN NEW;
END;
$$;

CREATE TRIGGER admin_action_receipts_prune_expired_before_insert
BEFORE INSERT ON admin_action_receipts
FOR EACH ROW
EXECUTE FUNCTION prune_expired_admin_action_receipts_on_insert();
SQL);

        Schema::rename('publication_version_rows', 'publication_version_row_manifests');

        Schema::create('publication_version_payloads', function (Blueprint $table): void {
            $table->id();
            $table->char('fingerprint', 32);
            $table->jsonb('payload');

            $table->index('fingerprint', 'publication_version_payloads_fingerprint_index');
        });

        DB::statement('ALTER TABLE publication_version_row_manifests ADD COLUMN payload_id bigint NULL');

        DB::statement(<<<'SQL'
INSERT INTO publication_version_payloads (fingerprint, payload)
SELECT md5(manifest.payload::text), manifest.payload
FROM publication_version_row_manifests AS manifest
GROUP BY manifest.payload
SQL);

        DB::statement(<<<'SQL'
UPDATE publication_version_row_manifests AS manifest
SET payload_id = payload.id
FROM publication_version_payloads AS payload
WHERE payload.fingerprint = md5(manifest.payload::text)
  AND payload.payload = manifest.payload
SQL);

        DB::statement('ALTER TABLE publication_version_row_manifests ALTER COLUMN payload_id SET NOT NULL');
        DB::statement(<<<'SQL'
ALTER TABLE publication_version_row_manifests
ADD CONSTRAINT publication_version_row_manifests_payload_id_foreign
FOREIGN KEY (payload_id)
REFERENCES publication_version_payloads(id)
ON DELETE RESTRICT
SQL);
        DB::statement('CREATE INDEX publication_version_row_manifests_payload_id_index ON publication_version_row_manifests (payload_id)');
        DB::statement('ALTER TABLE publication_version_row_manifests DROP COLUMN payload');

        DB::statement(<<<'SQL'
CREATE VIEW publication_version_rows AS
SELECT manifest.id,
       manifest.publication_checkpoint_id,
       manifest.table_name,
       manifest.row_key,
       payload.payload
FROM publication_version_row_manifests AS manifest
JOIN publication_version_payloads AS payload ON payload.id = manifest.payload_id
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION publication_version_rows_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    resolved_payload_id bigint;
BEGIN
    IF NEW.payload IS NULL THEN
        RAISE EXCEPTION 'Publication version payload must not be null.';
    END IF;

    SELECT payload.id
    INTO resolved_payload_id
    FROM publication_version_payloads AS payload
    WHERE payload.fingerprint = md5(NEW.payload::text)
      AND payload.payload = NEW.payload
    ORDER BY payload.id
    LIMIT 1;

    IF resolved_payload_id IS NULL THEN
        INSERT INTO publication_version_payloads (fingerprint, payload)
        VALUES (md5(NEW.payload::text), NEW.payload)
        RETURNING id INTO resolved_payload_id;
    END IF;

    INSERT INTO publication_version_row_manifests (
        publication_checkpoint_id,
        table_name,
        row_key,
        payload_id
    ) VALUES (
        NEW.publication_checkpoint_id,
        NEW.table_name,
        NEW.row_key,
        resolved_payload_id
    )
    RETURNING id INTO NEW.id;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION publication_version_rows_delete()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    removed_payload_id bigint;
BEGIN
    DELETE FROM publication_version_row_manifests
    WHERE id = OLD.id
    RETURNING payload_id INTO removed_payload_id;

    IF removed_payload_id IS NOT NULL
       AND NOT EXISTS (
           SELECT 1
           FROM publication_version_row_manifests
           WHERE payload_id = removed_payload_id
       ) THEN
        DELETE FROM publication_version_payloads
        WHERE id = removed_payload_id;
    END IF;

    RETURN OLD;
END;
$$;

CREATE TRIGGER publication_version_rows_insert_instead
INSTEAD OF INSERT ON publication_version_rows
FOR EACH ROW
EXECUTE FUNCTION publication_version_rows_insert();

CREATE TRIGGER publication_version_rows_delete_instead
INSTEAD OF DELETE ON publication_version_rows
FOR EACH ROW
EXECUTE FUNCTION publication_version_rows_delete();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS publication_version_rows;
DROP FUNCTION IF EXISTS publication_version_rows_insert();
DROP FUNCTION IF EXISTS publication_version_rows_delete();
DROP TRIGGER IF EXISTS admin_action_receipts_prune_expired_before_insert ON admin_action_receipts;
DROP FUNCTION IF EXISTS prune_expired_admin_action_receipts_on_insert();
SQL);

            DB::statement('ALTER TABLE publication_version_row_manifests ADD COLUMN payload jsonb NULL');
            DB::statement(<<<'SQL'
UPDATE publication_version_row_manifests AS manifest
SET payload = payload.payload
FROM publication_version_payloads AS payload
WHERE payload.id = manifest.payload_id
SQL);
            DB::statement('ALTER TABLE publication_version_row_manifests ALTER COLUMN payload SET NOT NULL');
            DB::statement('ALTER TABLE publication_version_row_manifests DROP CONSTRAINT publication_version_row_manifests_payload_id_foreign');
            DB::statement('DROP INDEX IF EXISTS publication_version_row_manifests_payload_id_index');
            DB::statement('ALTER TABLE publication_version_row_manifests DROP COLUMN payload_id');

            Schema::rename('publication_version_row_manifests', 'publication_version_rows');
            Schema::dropIfExists('publication_version_payloads');
        }

        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->dropIndex('admin_action_receipts_expires_at_index');
        });
    }
};
