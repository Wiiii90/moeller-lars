<?php

use App\Domain\Admin\AdminActionReceiptRetentionPolicy;
use App\Domain\Admin\AdminActionReceiptService;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->unsignedBigInteger('logical_bytes')->default(0);
            $table->index(
                ['admin_user_id', 'created_at', 'id'],
                'admin_action_receipts_user_created_id_index',
            );
        });

        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
UPDATE admin_action_receipts AS receipt
SET logical_bytes = pg_column_size(receipt)
SQL);

        $maxBytes = AdminActionReceiptRetentionPolicy::MAX_BYTES_PER_USER;
        $maxReceipts = AdminActionReceiptService::MAX_RECEIPTS_PER_USER;

        DB::unprepared(<<<SQL
CREATE OR REPLACE FUNCTION measure_admin_action_receipt_logical_bytes()
RETURNS trigger
LANGUAGE plpgsql
AS \$\$
BEGIN
    NEW.logical_bytes := pg_column_size(NEW);

    RETURN NEW;
END;
\$\$;

CREATE TRIGGER admin_action_receipts_measure_logical_bytes
BEFORE INSERT OR UPDATE ON admin_action_receipts
FOR EACH ROW
EXECUTE FUNCTION measure_admin_action_receipt_logical_bytes();

CREATE OR REPLACE FUNCTION prune_admin_action_receipts_to_budget()
RETURNS trigger
LANGUAGE plpgsql
AS \$\$
DECLARE
    configured_budget text;
    max_bytes bigint := {$maxBytes};
BEGIN
    configured_budget := current_setting('app.admin_undo_max_bytes', true);

    IF configured_budget ~ '^[1-9][0-9]*$' THEN
        max_bytes := configured_budget::bigint;
    END IF;

    WITH ranked AS (
        SELECT id,
               row_number() OVER (
                   ORDER BY created_at DESC, id DESC
               ) AS receipt_number,
               sum(logical_bytes) OVER (
                   ORDER BY created_at DESC, id DESC
                   ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW
               ) AS cumulative_bytes
        FROM admin_action_receipts
        WHERE admin_user_id = NEW.admin_user_id
    ),
    doomed AS (
        SELECT id
        FROM ranked
        WHERE receipt_number > {$maxReceipts}
           OR cumulative_bytes > max_bytes
    )
    DELETE FROM admin_action_receipts AS receipt
    USING doomed
    WHERE receipt.id = doomed.id;

    RETURN NEW;
END;
\$\$;

CREATE TRIGGER admin_action_receipts_prune_budget_after_insert
AFTER INSERT ON admin_action_receipts
FOR EACH ROW
EXECUTE FUNCTION prune_admin_action_receipts_to_budget();
SQL);
    }

    public function down(): void
    {
        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::unprepared(<<<'SQL'
DROP TRIGGER IF EXISTS admin_action_receipts_prune_budget_after_insert ON admin_action_receipts;
DROP FUNCTION IF EXISTS prune_admin_action_receipts_to_budget();
DROP TRIGGER IF EXISTS admin_action_receipts_measure_logical_bytes ON admin_action_receipts;
DROP FUNCTION IF EXISTS measure_admin_action_receipt_logical_bytes();
SQL);
        }

        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            $table->dropIndex('admin_action_receipts_user_created_id_index');
            $table->dropColumn('logical_bytes');
        });
    }
};
