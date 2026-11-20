<?php

namespace App\Domain\Content;

use App\Models\ArtworkCategory;
use App\Models\SiteSection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Resolve optional Add Page identity before entering the normal editorial
 * creation workflow. Both Add Page entry points share this one allocator.
 */
final class SiteSectionCreationDefaults
{
    private const LOCK_KEY = 16520261120;

    public function __construct(private readonly SiteSectionPathPolicy $paths) {}

    /** @return array{name:string,slug:string} */
    public function resolve(SiteSectionType $type, string $name, string $slug): array
    {
        // The caller holds the page-creation transaction. Serializing just
        // default allocation prevents simultaneous unnamed submissions from
        // selecting the same title or public URL.
        DB::select('SELECT pg_advisory_xact_lock(?)', [self::LOCK_KEY]);

        $name = trim($name);
        $slug = trim($slug);
        if ($name === '') {
            $base = match ($type) {
                SiteSectionType::CustomPage => 'Untitled Page',
                SiteSectionType::Gallery => 'Untitled Gallery',
                SiteSectionType::Journal => 'Untitled Journal',
                SiteSectionType::NavigationNode => 'Untitled Group',
            };
            for ($number = 1; $number <= 10000; $number++) {
                $candidate = $base.' '.$number;
                if (! SiteSection::query()
                    ->whereRaw('LOWER(title) = ?', [mb_strtolower($candidate)])
                    ->exists()) {
                    $name = $candidate;
                    break;
                }
            }
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'A unique default page name could not be allocated.']);
            }
        }

        if ($type->requiresSlug() && $slug === '') {
            $base = rtrim(mb_substr(Str::slug($name), 0, 72), '-');
            if ($base === '') {
                $base = match ($type) {
                    SiteSectionType::Gallery => 'gallery',
                    SiteSectionType::Journal => 'journal',
                    default => 'page',
                };
            }

            for ($number = 1; $number <= 10000; $number++) {
                $suffix = $number === 1 ? '' : '-'.$number;
                $candidate = rtrim(mb_substr($base, 0, 80 - strlen($suffix)), '-').$suffix;
                if ($this->paths->available($candidate)
                    && ! ArtworkCategory::query()->where('slug', $candidate)->exists()) {
                    $slug = $candidate;
                    break;
                }
            }
            if ($slug === '') {
                throw ValidationException::withMessages(['slug' => 'A unique public slug could not be allocated.']);
            }
        }

        return ['name' => $name, 'slug' => $slug];
    }
}
