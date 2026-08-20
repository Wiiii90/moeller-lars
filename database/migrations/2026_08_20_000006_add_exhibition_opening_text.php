<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const LEGACY_VITA_SOURCE = 'legacy-public-vita';

    public function up(): void
    {
        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->string('opening_text', 500)->nullable();
        });

        DB::table('exhibitions')
            ->where('legacy_source', self::LEGACY_VITA_SOURCE)
            ->whereNotNull('description')
            ->orderBy('id')
            ->each(function (object $row): void {
                $description = trim((string) $row->description);
                if ($description === '') {
                    return;
                }

                if (preg_match('/^(.*?)\s*\bVernissage:\s*(.+)$/uis', $description, $match) !== 1) {
                    return;
                }

                $body = trim($match[1]);
                $opening = trim($match[2]);
                if ($opening === '') {
                    return;
                }

                DB::table('exhibitions')->where('id', $row->id)->update([
                    'description' => $body === '' ? null : $body,
                    'opening_text' => $opening,
                    'updated_at' => now(),
                ]);
            });
    }

    public function down(): void
    {
        DB::table('exhibitions')
            ->where('legacy_source', self::LEGACY_VITA_SOURCE)
            ->whereNotNull('opening_text')
            ->orderBy('id')
            ->each(function (object $row): void {
                $opening = trim((string) $row->opening_text);
                if ($opening === '') {
                    return;
                }

                $description = trim((string) ($row->description ?? ''));
                $combined = $description === ''
                    ? 'Vernissage: '.$opening
                    : $description.' Vernissage: '.$opening;

                DB::table('exhibitions')->where('id', $row->id)->update([
                    'description' => $combined,
                    'updated_at' => now(),
                ]);
            });

        Schema::table('exhibitions', function (Blueprint $table): void {
            $table->dropColumn('opening_text');
        });
    }
};
