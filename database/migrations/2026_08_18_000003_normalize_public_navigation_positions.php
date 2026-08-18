<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $used = [];

        foreach (DB::table('artwork_categories')->orderBy('position')->orderBy('id')->pluck('position') as $position) {
            $used[(int) $position] = true;
        }

        $nextFree = static function (int $preferred) use (&$used): int {
            $position = max(0, $preferred);
            while (isset($used[$position])) {
                $position++;
            }
            $used[$position] = true;

            return $position;
        };

        $settings = DB::table('public_content_settings')->where('id', 1)->first();
        if ($settings !== null) {
            $cvPosition = $nextFree((int) $settings->cv_navigation_position);
            $exhibitionsPosition = $nextFree((int) $settings->exhibitions_navigation_position);

            DB::table('public_content_settings')
                ->where('id', 1)
                ->update([
                    'cv_navigation_position' => $cvPosition,
                    'exhibitions_navigation_position' => $exhibitionsPosition,
                    'updated_at' => now(),
                ]);
        }

        $blogSettings = DB::table('blog_settings')->where('id', 1)->first();
        if ($blogSettings !== null) {
            $blogPosition = $nextFree((int) $blogSettings->navigation_position);

            DB::table('blog_settings')
                ->where('id', 1)
                ->update([
                    'navigation_position' => $blogPosition,
                    'updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        // Navigation normalization is intentionally not data-reversible because
        // prior duplicate positions are ambiguous by definition.
    }
};
