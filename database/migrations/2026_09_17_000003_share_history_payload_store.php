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
            $table->unsignedBigInteger('snapshot_payload_id')->nullable();
            $table->index('snapshot_payload_id', 'admin_action_receipts_snapshot_payload_id_index');
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('LOCK TABLE admin_action_receipts IN ACCESS EXCLUSIVE MODE');
        DB::statement('LOCK TABLE publication_version_row_manifests IN ACCESS EXCLUSIVE MODE');
        DB::statement('LOCK TABLE publication_version_payloads IN ACCESS EXCLUSIVE MODE');

        DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS publication_version_rows;
DROP FUNCTION IF EXISTS publication_version_rows_insert();
DROP FUNCTION IF EXISTS publication_version_rows_delete();
SQL);

        DB::statement('ALTER TABLE publication_version_payloads RENAME TO history_payloads');
        DB::statement('ALTER INDEX publication_version_payloads_fingerprint_index RENAME TO history_payloads_fingerprint_index');

        DB::unprepared(<<<'SQL'
DO $$
BEGIN
    IF EXISTS (
        SELECT 1
        FROM history_payloads
        GROUP BY fingerprint
        HAVING COUNT(DISTINCT payload::text) > 1
    ) THEN
        RAISE EXCEPTION 'History payload fingerprint collision detected before deduplication.';
    END IF;
END;
$$;

WITH keepers AS (
    SELECT fingerprint, MIN(id) AS keeper_id
    FROM history_payloads
    GROUP BY fingerprint
), duplicate_map AS (
    SELECT payload.id AS duplicate_id, keepers.keeper_id
    FROM history_payloads AS payload
    JOIN keepers ON keepers.fingerprint = payload.fingerprint
    WHERE payload.id <> keepers.keeper_id
)
UPDATE publication_version_row_manifests AS manifest
SET payload_id = duplicate_map.keeper_id
FROM duplicate_map
WHERE manifest.payload_id = duplicate_map.duplicate_id;

DELETE FROM history_payloads AS payload
USING history_payloads AS keeper
WHERE payload.fingerprint = keeper.fingerprint
  AND payload.id > keeper.id;

CREATE UNIQUE INDEX history_payloads_fingerprint_unique
ON history_payloads (fingerprint);

ALTER TABLE history_payloads
ADD CONSTRAINT history_payloads_fingerprint_matches_payload
CHECK (fingerprint = md5(payload::text));
SQL);

        DB::statement(<<<'SQL'
ALTER TABLE admin_action_receipts
ADD CONSTRAINT admin_action_receipts_snapshot_payload_id_foreign
FOREIGN KEY (snapshot_payload_id)
REFERENCES history_payloads(id)
ON DELETE RESTRICT
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION prevent_history_payload_update()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    RAISE EXCEPTION 'History payloads are immutable.';
END;
$$;

CREATE TRIGGER history_payloads_prevent_update
BEFORE UPDATE ON history_payloads
FOR EACH ROW
EXECUTE FUNCTION prevent_history_payload_update();

CREATE OR REPLACE FUNCTION resolve_history_payload(candidate_payload jsonb)
RETURNS bigint
LANGUAGE plpgsql
AS $$
DECLARE
    candidate_fingerprint text := md5(candidate_payload::text);
    resolved_payload_id bigint;
    resolved_payload jsonb;
BEGIN
    IF candidate_payload IS NULL THEN
        RAISE EXCEPTION 'History payload must not be null.';
    END IF;

    INSERT INTO history_payloads (fingerprint, payload)
    VALUES (candidate_fingerprint, candidate_payload)
    ON CONFLICT (fingerprint) DO NOTHING
    RETURNING id INTO resolved_payload_id;

    IF resolved_payload_id IS NULL THEN
        SELECT id, payload
        INTO resolved_payload_id, resolved_payload
        FROM history_payloads
        WHERE fingerprint = candidate_fingerprint
        FOR KEY SHARE;

        IF resolved_payload IS DISTINCT FROM candidate_payload THEN
            RAISE EXCEPTION 'History payload fingerprint collision detected.';
        END IF;
    END IF;

    RETURN resolved_payload_id;
END;
$$;

CREATE OR REPLACE FUNCTION attach_admin_action_receipt_history_payload()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    shared_marker constant jsonb := '{"$history_payload":true}'::jsonb;
BEGIN
    IF NEW.snapshot_payload IS NULL THEN
        NEW.snapshot_payload_id := NULL;
        RETURN NEW;
    END IF;

    IF NEW.snapshot_payload_id IS NOT NULL AND NEW.snapshot_payload = shared_marker THEN
        RETURN NEW;
    END IF;

    NEW.snapshot_payload_id := resolve_history_payload(NEW.snapshot_payload);
    NEW.snapshot_payload := shared_marker;

    RETURN NEW;
END;
$$;

CREATE TRIGGER admin_action_receipts_attach_history_payload
BEFORE INSERT OR UPDATE OF snapshot_payload ON admin_action_receipts
FOR EACH ROW
EXECUTE FUNCTION attach_admin_action_receipt_history_payload();
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION measure_admin_action_receipt_logical_bytes()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    payload_bytes bigint := 0;
BEGIN
    IF NEW.snapshot_payload_id IS NOT NULL THEN
        SELECT pg_column_size(payload)
        INTO payload_bytes
        FROM history_payloads
        WHERE id = NEW.snapshot_payload_id;
    END IF;

    NEW.logical_bytes := pg_column_size(NEW) + COALESCE(payload_bytes, 0);

    RETURN NEW;
END;
$$;
SQL);

        DB::statement(<<<'SQL'
UPDATE admin_action_receipts
SET snapshot_payload = snapshot_payload
WHERE snapshot_payload IS NOT NULL
SQL);
        DB::statement('UPDATE admin_action_receipts SET logical_bytes = logical_bytes');

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION gc_history_payload(candidate_payload_id bigint)
RETURNS void
LANGUAGE plpgsql
AS $$
BEGIN
    IF candidate_payload_id IS NULL THEN
        RETURN;
    END IF;

    BEGIN
        DELETE FROM history_payloads AS payload
        WHERE payload.id = candidate_payload_id
          AND NOT EXISTS (
              SELECT 1
              FROM publication_version_row_manifests AS manifest
              WHERE manifest.payload_id = candidate_payload_id
          )
          AND NOT EXISTS (
              SELECT 1
              FROM admin_action_receipts AS receipt
              WHERE receipt.snapshot_payload_id = candidate_payload_id
          );
    EXCEPTION
        WHEN foreign_key_violation THEN
            NULL;
    END;
END;
$$;

CREATE OR REPLACE FUNCTION gc_history_payload_after_publication_manifest_change()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        PERFORM gc_history_payload(OLD.payload_id);
        RETURN OLD;
    END IF;

    IF OLD.payload_id IS DISTINCT FROM NEW.payload_id THEN
        PERFORM gc_history_payload(OLD.payload_id);
    END IF;

    RETURN NEW;
END;
$$;

CREATE OR REPLACE FUNCTION gc_history_payload_after_admin_receipt_change()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    IF TG_OP = 'DELETE' THEN
        PERFORM gc_history_payload(OLD.snapshot_payload_id);
        RETURN OLD;
    END IF;

    IF OLD.snapshot_payload_id IS DISTINCT FROM NEW.snapshot_payload_id THEN
        PERFORM gc_history_payload(OLD.snapshot_payload_id);
    END IF;

    RETURN NEW;
END;
$$;

CREATE TRIGGER publication_version_row_manifests_gc_history_payload
AFTER DELETE OR UPDATE OF payload_id ON publication_version_row_manifests
FOR EACH ROW
EXECUTE FUNCTION gc_history_payload_after_publication_manifest_change();

CREATE TRIGGER admin_action_receipts_gc_history_payload
AFTER DELETE OR UPDATE OF snapshot_payload_id ON admin_action_receipts
FOR EACH ROW
EXECUTE FUNCTION gc_history_payload_after_admin_receipt_change();
SQL);

        DB::statement(<<<'SQL'
CREATE VIEW publication_version_rows AS
SELECT manifest.id,
       manifest.publication_checkpoint_id,
       manifest.table_name,
       manifest.row_key,
       payload.payload
FROM publication_version_row_manifests AS manifest
JOIN history_payloads AS payload ON payload.id = manifest.payload_id
SQL);

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION publication_version_rows_insert()
RETURNS trigger
LANGUAGE plpgsql
AS $$
DECLARE
    resolved_payload_id bigint;
BEGIN
    resolved_payload_id := resolve_history_payload(NEW.payload);

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
BEGIN
    DELETE FROM publication_version_row_manifests
    WHERE id = OLD.id;

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
        if (DB::connection()->getDriverName() !== 'pgsql') {
            Schema::table('admin_action_receipts', function (Blueprint $table): void {
                $table->dropIndex('admin_action_receipts_snapshot_payload_id_index');
                $table->dropColumn('snapshot_payload_id');
            });

            return;
        }

        DB::statement('LOCK TABLE admin_action_receipts IN ACCESS EXCLUSIVE MODE');
        DB::statement('LOCK TABLE publication_version_row_manifests IN ACCESS EXCLUSIVE MODE');
        DB::statement('LOCK TABLE history_payloads IN ACCESS EXCLUSIVE MODE');

        DB::unprepared(<<<'SQL'
DROP VIEW IF EXISTS publication_version_rows;
DROP FUNCTION IF EXISTS publication_version_rows_insert();
DROP FUNCTION IF EXISTS publication_version_rows_delete();

DROP TRIGGER IF EXISTS history_payloads_prevent_update ON history_payloads;
DROP TRIGGER IF EXISTS publication_version_row_manifests_gc_history_payload ON publication_version_row_manifests;
DROP TRIGGER IF EXISTS admin_action_receipts_gc_history_payload ON admin_action_receipts;
DROP TRIGGER IF EXISTS admin_action_receipts_attach_history_payload ON admin_action_receipts;

DROP FUNCTION IF EXISTS prevent_history_payload_update();
DROP FUNCTION IF EXISTS gc_history_payload_after_publication_manifest_change();
DROP FUNCTION IF EXISTS gc_history_payload_after_admin_receipt_change();
DROP FUNCTION IF EXISTS gc_history_payload(bigint);
DROP FUNCTION IF EXISTS attach_admin_action_receipt_history_payload();
SQL);

        DB::statement(<<<'SQL'
UPDATE admin_action_receipts AS receipt
SET snapshot_payload = payload.payload
FROM history_payloads AS payload
WHERE payload.id = receipt.snapshot_payload_id
SQL);

        DB::statement('ALTER TABLE admin_action_receipts DROP CONSTRAINT admin_action_receipts_snapshot_payload_id_foreign');

        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->dropIndex('admin_action_receipts_snapshot_payload_id_index');
            $table->dropColumn('snapshot_payload_id');
        });

        DB::unprepared(<<<'SQL'
CREATE OR REPLACE FUNCTION measure_admin_action_receipt_logical_bytes()
RETURNS trigger
LANGUAGE plpgsql
AS $$
BEGIN
    NEW.logical_bytes := pg_column_size(NEW);

    RETURN NEW;
END;
$$;
SQL);
        DB::statement('UPDATE admin_action_receipts SET logical_bytes = logical_bytes');

        DB::statement(<<<'SQL'
DELETE FROM history_payloads AS payload
WHERE NOT EXISTS (
    SELECT 1
    FROM publication_version_row_manifests AS manifest
    WHERE manifest.payload_id = payload.id
)
SQL);

        DB::statement('ALTER TABLE history_payloads DROP CONSTRAINT history_payloads_fingerprint_matches_payload');
        DB::statement('DROP INDEX IF EXISTS history_payloads_fingerprint_unique');
        DB::statement('ALTER TABLE history_payloads RENAME TO publication_version_payloads');
        DB::statement('ALTER INDEX history_payloads_fingerprint_index RENAME TO publication_version_payloads_fingerprint_index');

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

DROP FUNCTION IF EXISTS resolve_history_payload(jsonb);
SQL);
    }
};
