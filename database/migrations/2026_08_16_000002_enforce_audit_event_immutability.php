<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement(<<<'SQL'
            CREATE OR REPLACE FUNCTION moeller_lars_reject_audit_event_mutation()
            RETURNS trigger
            LANGUAGE plpgsql
            AS $$
            BEGIN
                IF TG_OP IN ('UPDATE', 'DELETE') THEN
                    RAISE EXCEPTION 'audit_events are append-only';
                END IF;

                RETURN NEW;
            END;
            $$
            SQL);

        DB::statement('DROP TRIGGER IF EXISTS audit_events_append_only ON audit_events');
        DB::statement(<<<'SQL'
            CREATE TRIGGER audit_events_append_only
            BEFORE UPDATE OR DELETE
            ON audit_events
            FOR EACH ROW
            EXECUTE FUNCTION moeller_lars_reject_audit_event_mutation()
            SQL);
    }

    public function down(): void
    {
        DB::statement('DROP TRIGGER IF EXISTS audit_events_append_only ON audit_events');
        DB::statement('DROP FUNCTION IF EXISTS moeller_lars_reject_audit_event_mutation()');
    }
};
