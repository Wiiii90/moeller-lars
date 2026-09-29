<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $columns = array_values(array_filter(
            ['neighbor_artwork_media_id', 'inverse_direction'],
            static fn (string $column): bool => Schema::hasColumn('admin_action_receipts', $column),
        ));

        if ($columns === []) {
            return;
        }

        Schema::table('admin_action_receipts', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }

    public function down(): void
    {
        Schema::table('admin_action_receipts', function (Blueprint $table): void {
            if (! Schema::hasColumn('admin_action_receipts', 'neighbor_artwork_media_id')) {
                $table->unsignedBigInteger('neighbor_artwork_media_id')->nullable();
            }
            if (! Schema::hasColumn('admin_action_receipts', 'inverse_direction')) {
                $table->string('inverse_direction', 8)->nullable();
            }
        });
    }
};
