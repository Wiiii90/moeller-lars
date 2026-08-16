<?php

namespace App\Domain\Migration;

use App\Domain\Artwork\PublicArtworkQuery;
use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaIntegrityService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\MediaAsset;
use Illuminate\Support\Facades\DB;
use JsonException;
use RuntimeException;

final class LegacyMigrationValidator
{
    public function __construct(
        private readonly MediaIntegrityService $mediaIntegrityService,
        private readonly PublicArtworkQuery $publicArtworkQuery,
        private readonly LegacyPublicCvImporter $cvImporter,
        private readonly LegacyPublicProfileImporter $profileImporter,
    ) {
        // Constructor property promotion initializes the validator dependencies.
    }

    /**
     * @return array{
     *   ok:bool,
     *   batch:string,
     *   source:array{categories:int,artworks:int,cv_entries:int},
     *   target:array{categories:int,artworks:int,media:int,cv_entries:int},
     *   category_mapping:list<array{source:string,slug:string,source_count:int,target_count:int}>,
     *   same_date_groups:list<array{source:string,date:string,legacy_ids:list<int>}>,
     *   home:array{expected_slug:?string,target_slug:?string},
     *   errors:list<string>
     * }
     */
    public function validate(string $manifestPath): array
    {
        $manifest = $this->manifest($manifestPath);
        $batch = $this->requiredString($manifest, 'batch');
        $categories = $this->requiredList($manifest, 'categories');
        $errors = [];
        $categoryMapping = [];
        $sameDateGroups = [];
        $expectedArtworkCount = 0;
        $expectedHomeSlug = null;

        foreach ($categories as $categoryData) {
            if (is_array($categoryData) === false) {
                throw new RuntimeException('Legacy manifest category must be an object.');
            }

            $source = $this->requiredString($categoryData, 'legacy_source');
            $slug = $this->requiredString($categoryData, 'slug');
            $artworks = $this->requiredList($categoryData, 'artworks');
            $expectedArtworkCount += count($artworks);

            $category = ArtworkCategory::query()
                ->where('migration_batch_id', $batch)
                ->where('legacy_source', $source)
                ->where('slug', $slug)
                ->first();

            if (($category instanceof ArtworkCategory) === false) {
                $errors[] = "Missing target category {$source} -> {$slug}.";
                $categoryMapping[] = [
                    'source' => $source,
                    'slug' => $slug,
                    'source_count' => count($artworks),
                    'target_count' => 0,
                ];

                continue;
            }

            $this->compareCategory($category, $categoryData, $errors);

            $targetArtworkCount = Artwork::query()
                ->where('artwork_category_id', $category->getKey())
                ->where('migration_batch_id', $batch)
                ->count();
            $categoryMapping[] = [
                'source' => $source,
                'slug' => $slug,
                'source_count' => count($artworks),
                'target_count' => $targetArtworkCount,
            ];

            if ($targetArtworkCount !== count($artworks)) {
                $errors[] = "Artwork count mismatch for {$source}: expected ".count($artworks).", got {$targetArtworkCount}.";
            }

            $dateGroups = [];
            foreach ($artworks as $artworkData) {
                if (is_array($artworkData) === false) {
                    throw new RuntimeException("Legacy manifest artwork in {$source} must be an object.");
                }

                $legacyId = $this->requiredInteger($artworkData, 'legacy_id');
                $date = $this->nullableString($artworkData, 'work_date');
                if ($date !== null) {
                    $dateGroups[$date][] = $legacyId;
                }
                if (($artworkData['featured_on_home'] ?? false) === true) {
                    if ($expectedHomeSlug !== null) {
                        $errors[] = 'Manifest contains more than one explicit home artwork.';
                    }
                    $expectedHomeSlug = $this->requiredString($artworkData, 'slug');
                }

                $this->validateArtwork($category, $batch, $source, $artworkData, $errors);
            }

            foreach ($dateGroups as $date => $legacyIds) {
                if (count($legacyIds) > 1) {
                    $sameDateGroups[] = [
                        'source' => $source,
                        'date' => $date,
                        'legacy_ids' => $legacyIds,
                    ];
                }
            }
        }

        $targetCategoryCount = ArtworkCategory::query()->where('migration_batch_id', $batch)->count();
        $targetArtworkCount = Artwork::query()->where('migration_batch_id', $batch)->count();
        $targetMediaCount = MediaAsset::query()->where('migration_batch_id', $batch)->count();

        if ($targetCategoryCount !== count($categories)) {
            $errors[] = 'Target contains a different number of migrated categories than the manifest.';
        }
        if ($targetArtworkCount !== $expectedArtworkCount) {
            $errors[] = 'Target contains a different number of migrated artworks than the manifest.';
        }
        if ($targetMediaCount !== $expectedArtworkCount) {
            $errors[] = 'Target contains a different number of migrated original media assets than the manifest.';
        }

        $targetHomeSlug = null;
        try {
            $targetHomeSlug = $this->publicArtworkQuery->latestForHome()?->getAttribute('slug');
            if ($targetHomeSlug !== null && is_string($targetHomeSlug) === false) {
                $errors[] = 'Public home query returned an invalid artwork slug.';
                $targetHomeSlug = null;
            }
        } catch (\Throwable $exception) {
            $errors[] = 'Public home query failed: '.$exception->getMessage();
        }

        if ($expectedHomeSlug !== null && $targetHomeSlug !== $expectedHomeSlug) {
            $errors[] = "Home artwork mismatch: expected {$expectedHomeSlug}, got ".($targetHomeSlug ?? 'none').'.';
        }

        $cvCount = $this->validateCv($errors);
        $this->validatePublicProfile($errors);

        return [
            'ok' => $errors === [],
            'batch' => $batch,
            'source' => [
                'categories' => count($categories),
                'artworks' => $expectedArtworkCount,
                'cv_entries' => count($this->cvImporter->expectedRows()),
            ],
            'target' => [
                'categories' => $targetCategoryCount,
                'artworks' => $targetArtworkCount,
                'media' => $targetMediaCount,
                'cv_entries' => $cvCount,
            ],
            'category_mapping' => $categoryMapping,
            'same_date_groups' => $sameDateGroups,
            'home' => [
                'expected_slug' => $expectedHomeSlug,
                'target_slug' => $targetHomeSlug,
            ],
            'errors' => $errors,
        ];
    }

    /** @param array<string, mixed> $expected @param list<string> $errors */
    private function compareCategory(ArtworkCategory $category, array $expected, array &$errors): void
    {
        $checks = [
            'name' => $expected['name'] ?? null,
            'position' => $expected['position'] ?? null,
            'show_in_navigation' => $expected['show_in_navigation'] ?? null,
            'show_on_home' => $expected['show_on_home'] ?? null,
            'description' => $expected['description'] ?? null,
            'state' => 'published',
        ];

        foreach ($checks as $field => $value) {
            $actual = $category->getAttribute($field);
            if (is_bool($value)) {
                $actual = (bool) $actual;
            }
            if ($actual !== $value) {
                $errors[] = "Category {$category->getAttribute('slug')} field {$field} does not match the manifest.";
            }
        }
    }

    /** @param array<string, mixed> $expected @param list<string> $errors */
    private function validateArtwork(ArtworkCategory $category, string $batch, string $source, array $expected, array &$errors): void
    {
        $legacyId = $this->requiredInteger($expected, 'legacy_id');
        $artwork = Artwork::query()
            ->where('artwork_category_id', $category->getKey())
            ->where('migration_batch_id', $batch)
            ->where('legacy_source', $source)
            ->where('legacy_id', $legacyId)
            ->first();

        if (($artwork instanceof Artwork) === false) {
            $errors[] = "Missing target artwork {$source}#{$legacyId}.";

            return;
        }

        $expectedDate = $this->nullableString($expected, 'work_date');
        $actualDate = $artwork->getAttribute('work_date');
        $actualDateText = $actualDate instanceof \DateTimeInterface ? $actualDate->format('Y-m-d') : null;
        $checks = [
            'slug' => $expected['slug'] ?? null,
            'title' => $expected['title'] ?? null,
            'position' => $expected['position'] ?? null,
            'legacy_date_raw' => $expected['legacy_date_raw'] ?? null,
            'work_year' => $expected['work_year'] ?? null,
            'date_precision' => $expected['date_precision'] ?? null,
            'medium' => $expected['medium'] ?? null,
            'dimensions' => $expected['dimensions'] ?? null,
            'description' => $expected['description'] ?? null,
            'featured_on_home' => $expected['featured_on_home'] ?? null,
            'state' => 'published',
        ];

        foreach ($checks as $field => $value) {
            $actual = $artwork->getAttribute($field);
            if (is_bool($value)) {
                $actual = (bool) $actual;
            }
            if ($actual !== $value) {
                $errors[] = "Artwork {$source}#{$legacyId} field {$field} does not match the manifest.";
            }
        }
        if ($actualDateText !== $expectedDate) {
            $errors[] = "Artwork {$source}#{$legacyId} normalized date does not match the manifest.";
        }

        $primary = $artwork->artworkMedia()->where('role', 'primary')->get();
        if ($primary->count() !== 1) {
            $errors[] = "Artwork {$source}#{$legacyId} does not have exactly one primary media usage.";

            return;
        }

        $asset = MediaAsset::query()->find($primary->first()?->getAttribute('media_asset_id'));
        if (($asset instanceof MediaAsset) === false) {
            $errors[] = "Artwork {$source}#{$legacyId} primary media asset is missing.";

            return;
        }

        $expectedPath = $this->requiredString($expected, 'media_path');
        $mediaChecks = [
            'state' => 'available',
            'sha256' => strtolower($this->requiredString($expected, 'media_sha256')),
            'byte_size' => $this->requiredInteger($expected, 'media_byte_size'),
            'alt_text' => $this->requiredString($expected, 'alt_text'),
            'legacy_id' => $legacyId,
            'legacy_source' => $source,
            'legacy_path' => $expectedPath,
            'legacy_filename' => basename(str_replace('\\', '/', $expectedPath)),
            'legacy_byte_size' => $this->requiredInteger($expected, 'media_byte_size'),
            'migration_batch_id' => $batch,
        ];
        foreach ($mediaChecks as $field => $value) {
            if ($asset->getAttribute($field) !== $value) {
                $errors[] = "Media for {$source}#{$legacyId} field {$field} does not match the manifest.";
            }
        }

        $integrityIssues = $this->mediaIntegrityService->issues($asset);
        foreach ($integrityIssues as $issue) {
            $errors[] = "Media integrity failure for {$source}#{$legacyId}: {$issue}.";
        }

        $publicThumbnailCount = $asset->variants()
            ->where('state', 'available')
            ->where('variant_kind', MediaIngestService::THUMBNAIL_KIND)
            ->where('transform_profile', MediaIngestService::TRANSFORM_PROFILE)
            ->count();
        if ($publicThumbnailCount !== 1) {
            $errors[] = "Media for {$source}#{$legacyId} does not have exactly one available public-v1 thumbnail.";
        }
    }

    /** @param list<string> $errors */
    private function validateCv(array &$errors): int
    {
        $expectedRows = $this->cvImporter->expectedRows();
        $actualRows = DB::table('cv_entries')
            ->where('legacy_source', LegacyPublicCvImporter::SOURCE)
            ->where('migration_batch_id', LegacyPublicCvImporter::BATCH)
            ->orderBy('position')
            ->get();

        if ($actualRows->count() !== count($expectedRows)) {
            $errors[] = 'Legacy CV entry count does not match the verified public Vita source.';

            return $actualRows->count();
        }

        foreach ($expectedRows as $position => $expected) {
            $actual = $actualRows->get($position);
            if (is_object($actual) === false) {
                $errors[] = "Legacy CV entry {$position} is missing.";

                continue;
            }

            $checks = [
                'section' => $expected['section'],
                'title' => $expected['title'],
                'year_text' => $expected['year_text'],
                'date_precision' => $expected['date_precision'],
                'organisation' => $expected['organisation'],
                'location' => $expected['location'],
                'body' => $expected['body'],
                'starts_on' => $expected['starts_on'],
                'ends_on' => $expected['ends_on'],
                'position' => $position,
                'state' => 'published',
            ];
            foreach ($checks as $field => $value) {
                $actualValue = $actual->{$field} ?? null;
                if ($actualValue !== $value) {
                    $errors[] = "Legacy CV entry {$position} field {$field} does not match the verified source.";
                }
            }
        }

        return $actualRows->count();
    }

    /** @param list<string> $errors */
    private function validatePublicProfile(array &$errors): void
    {
        $settings = DB::table('public_content_settings')->where('id', 1)->first();
        if (is_object($settings) === false) {
            $errors[] = 'Public content settings singleton is missing.';

            return;
        }

        foreach ($this->profileImporter->expectedValues() as $field => $value) {
            if (($settings->{$field} ?? null) !== $value) {
                $errors[] = "Public profile field {$field} does not match the verified legacy source.";
            }
        }
    }

    /** @return array<string, mixed> */
    private function manifest(string $path): array
    {
        $file = realpath($path);
        if ($file === false || is_file($file) === false) {
            throw new RuntimeException('Legacy manifest does not exist.');
        }

        $json = file_get_contents($file);
        if (is_string($json) === false) {
            throw new RuntimeException('Legacy manifest could not be read.');
        }

        try {
            $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Legacy manifest is not valid JSON.', 0, $exception);
        }
        if (is_array($data) === false) {
            throw new RuntimeException('Legacy manifest root must be an object.');
        }

        return $data;
    }

    private function requiredString(array $data, string $key): string
    {
        $value = $data[$key] ?? null;
        if (is_string($value) === false || trim($value) === '') {
            throw new RuntimeException("Legacy manifest field {$key} must be a non-empty string.");
        }

        return $value;
    }

    private function nullableString(array $data, string $key): ?string
    {
        $value = $data[$key] ?? null;
        if ($value === null) {
            return null;
        }
        if (is_string($value) === false) {
            throw new RuntimeException("Legacy manifest field {$key} must be a string or null.");
        }

        return $value;
    }

    private function requiredInteger(array $data, string $key): int
    {
        $value = $data[$key] ?? null;
        if (is_int($value) === false) {
            throw new RuntimeException("Legacy manifest field {$key} must be an integer.");
        }

        return $value;
    }

    /** @return list<mixed> */
    private function requiredList(array $data, string $key): array
    {
        $value = $data[$key] ?? null;
        if (is_array($value) === false || array_is_list($value) === false) {
            throw new RuntimeException("Legacy manifest field {$key} must be a list.");
        }

        return $value;
    }
}
