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
        $apply = function (): void {
            Schema::table('artwork_categories', static function (Blueprint $table): void {
                $table->jsonb('artwork_information')->nullable();
                $table->boolean('media_viewer_enabled')->nullable()->default(true);
                $table->boolean('artwork_pages_enabled')->nullable()->default(true);
                $table->boolean('viewer_zoom_enabled')->nullable()->default(true);
                $table->boolean('viewer_navigation_enabled')->nullable()->default(true);
            });
            Schema::table('artworks', static function (Blueprint $table): void {
                $table->jsonb('artwork_information_override')->nullable();
            });

            $schemas = [''];
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement("ALTER TABLE committed.artwork_categories
                    ADD COLUMN artwork_information jsonb NULL,
                    ADD COLUMN media_viewer_enabled boolean NULL DEFAULT true,
                    ADD COLUMN artwork_pages_enabled boolean NULL DEFAULT true,
                    ADD COLUMN viewer_zoom_enabled boolean NULL DEFAULT true,
                    ADD COLUMN viewer_navigation_enabled boolean NULL DEFAULT true");
                DB::statement('ALTER TABLE committed.artworks ADD COLUMN artwork_information_override jsonb NULL');
                $schemas = ['public.', 'committed.'];
            }

            foreach ($schemas as $schema) {
                $table = $schema.'artwork_categories';
                DB::table($table)->select([
                    'id', 'show_artwork_information', 'show_media_credits',
                    'show_media_copyright', 'caption_layout',
                ])->orderBy('id')->chunkById(100, static function ($galleries) use ($table): void {
                    foreach ($galleries as $gallery) {
                        $info = $gallery->show_artwork_information !== false;
                        $credits = (bool) $gallery->show_media_credits;
                        $copyright = (bool) $gallery->show_media_copyright;
                        $rightsRight = $gallery->caption_layout === 'rights_right';
                        $infoSide = $rightsRight ? 'left' : 'right';
                        $rightsSide = $rightsRight ? 'right' : 'left';

                        // Preserve the historical caption on both Working
                        // and Committed. Old checkpoint JSON is immutable and
                        // reads through the legacy presentation fallback.
                        $configuration = [
                            'enabled' => $info || $credits || $copyright,
                            'visible' => [
                                'title' => $info,
                                'date' => $info,
                                'dimensions' => $info,
                                'material' => $info,
                                'credits' => $credits,
                                'copyright' => $copyright,
                            ],
                            'arrangement' => [
                                ['field' => 'credits', 'row' => 1, 'align' => $rightsSide],
                                ['field' => 'copyright', 'row' => 1, 'align' => $rightsSide],
                                ['field' => 'title', 'row' => 1, 'align' => $infoSide],
                                ['field' => 'date', 'row' => 1, 'align' => $infoSide],
                                ['field' => 'dimensions', 'row' => 2, 'align' => $infoSide],
                                ['field' => 'material', 'row' => 2, 'align' => $infoSide],
                            ],
                        ];

                        DB::table($table)->where('id', $gallery->id)->update([
                            'artwork_information' => json_encode($configuration, JSON_THROW_ON_ERROR),
                        ]);
                    }
                });
            }
        };

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::transaction(static function () use ($apply): void {
                DB::select('SELECT pg_advisory_xact_lock(?)', [PublicationSnapshot::LOCK_KEY]);
                $apply();
            });
        } else {
            $apply();
        }
    }

    public function down(): void
    {
        // Forward-only: Artwork presentation is part of committed publication
        // state and must never be discarded by a rollback.
    }
};
