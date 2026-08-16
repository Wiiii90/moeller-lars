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
                ...$this->expectedValues(),
                'updated_at' => now(),
            ]);

        if ($updated !== 1) {
            throw new RuntimeException('Public content settings singleton is missing.');
        }
    }

    /** @return array{public_email:string,instagram_handle:string,legal_disclaimer:string} */
    public function expectedValues(): array
    {
        return [
            'public_email' => 'moeller.lars1689@gmail.com',
            'instagram_handle' => 'larsmoeller_art',
            'legal_disclaimer' => 'Obwohl ich die Inhalte sowie die hier aufgeführten Verweise regelmäßig pflege, kann ich für diese nicht haften.',
        ];
    }
}
