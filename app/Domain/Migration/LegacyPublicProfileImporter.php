<?php

namespace App\Domain\Migration;

use Illuminate\Support\Facades\DB;
use RuntimeException;

final class LegacyPublicProfileImporter
{
    public function import(): void
    {
        $updated = DB::table('public_content_settings')
            ->where('id', 1)
            ->update([
                'public_email' => 'moeller.lars1689@gmail.com',
                'instagram_handle' => 'larsmoeller_art',
                'legal_disclaimer' => 'Obwohl ich die Inhalte sowie die hier aufgeführten Verweise regelmäßig pflege, kann ich für diese nicht haften.',
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('Public content settings singleton is missing.');
        }
    }
}
