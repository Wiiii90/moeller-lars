<?php

namespace App\Domain\Migration;

use App\Domain\Media\MediaIngestService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\SiteSection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use JsonException;
use RuntimeException;
use Throwable;

final class LegacyArtworkManifestImporter
{
    public function __construct(private readonly MediaIngestService $mediaIngestService) {}

    /** @return array{categories:int,artworks:int,media:int} */
    public function import(string $manifestPath, string $mediaRoot): array
    {
        [$batch, $categories] = $this->preflight($manifestPath, $mediaRoot);

        if (DB::table('artwork_categories')->exists() || DB::table('artworks')->exists() || DB::table('media_assets')->exists()) {
            throw new RuntimeException('Legacy artwork import requires empty artwork/category/media target tables.');
        }

        $storageKeys = [];
        $artworkCount = 0;
        $mediaCount = 0;

        try {
            DB::transaction(function () use ($batch, $categories, $mediaRoot, &$storageKeys, &$artworkCount, &$mediaCount): void {
                $now = now();

                foreach ($categories as $categoryData) {
                    $category = new ArtworkCategory;
                    $category->fill([
                        'slug' => $categoryData['slug'],
                        'name' => $categoryData['name'],
                        'show_on_home' => $categoryData['show_on_home'],
                        'description' => $categoryData['description'],
                        'legacy_source' => $categoryData['legacy_source'],
                        'migration_batch_id' => $batch,
                        'migrated_at' => $now,
                    ]);
                    $category->save();

                    SiteSection::query()->create([
                        'type' => SiteSection::TYPE_GALLERY,
                        'title' => $categoryData['name'],
                        'navigation_label' => $categoryData['name'],
                        'slug' => $categoryData['slug'],
                        'state' => 'published',
                        'position' => $categoryData['position'],
                        'show_in_navigation' => $categoryData['show_in_navigation'],
                        'parent_id' => null,
                        'artwork_category_id' => (int) $category->getKey(),
                    ]);

                    foreach ($categoryData['artworks'] as $artworkData) {
                        $artwork = new Artwork;
                        $artwork->fill([
                            'artwork_category_id' => $category->getKey(),
                            'slug' => $artworkData['slug'],
                            'title' => $artworkData['title'],
                            'medium' => $artworkData['medium'],
                            'dimensions' => $artworkData['dimensions'],
                            'description' => $artworkData['description'],
                            'state' => 'published',
                            'position' => $artworkData['position'],
                            'legacy_date_raw' => $artworkData['legacy_date_raw'],
                            'work_date' => $artworkData['work_date'],
                            'work_year' => $artworkData['work_year'],
                            'featured_on_home' => $artworkData['featured_on_home'],
                            'date_precision' => $artworkData['date_precision'],
                            'legacy_id' => $artworkData['legacy_id'],
                            'legacy_source' => $categoryData['legacy_source'],
                            'migration_batch_id' => $batch,
                            'migrated_at' => $now,
                            'published_at' => $now,
                        ]);
                        $artwork->save();

                        $sourcePath = $this->sourceMediaPath($mediaRoot, $artworkData['media_path']);
                        $upload = new UploadedFile(
                            $sourcePath,
                            basename($sourcePath),
                            null,
                            UPLOAD_ERR_OK,
                            true,
                        );
                        $asset = $this->mediaIngestService->ingest($upload);
                        $storageKeys[] = (string) $asset->getAttribute('storage_key');
                        foreach ($asset->variants()->pluck('storage_key')->all() as $key) {
                            $storageKeys[] = (string) $key;
                        }

                        $asset->fill([
                            'alt_text' => $artworkData['alt_text'],
                            'legacy_id' => $artworkData['legacy_id'],
                            'legacy_source' => $categoryData['legacy_source'],
                            'legacy_path' => $artworkData['media_path'],
                            'legacy_filename' => basename(str_replace('\\', '/', $artworkData['media_path'])),
                            'legacy_byte_size' => $artworkData['media_byte_size'],
                            'migration_batch_id' => $batch,
                            'migrated_at' => $now,
                        ]);
                        $asset->save();

                        if ($asset->getAttribute('sha256') !== $artworkData['media_sha256'] || (int) $asset->getAttribute('byte_size') !== $artworkData['media_byte_size']) {
                            throw new RuntimeException('Canonical original media differs from the preflighted source bytes.');
                        }

                        $usage = new ArtworkMedia;
                        $usage->fill([
                            'artwork_id' => $artwork->getKey(),
                            'media_asset_id' => $asset->getKey(),
                            'role' => 'primary',
                            'position' => 0,
                            'alt_text_override' => null,
                        ]);
                        $usage->save();

                        $artworkCount++;
                        $mediaCount++;
                    }
                }
            });
        } catch (Throwable $exception) {
            $this->cleanupStorage($storageKeys, $exception);
        }

        return [
            'categories' => count($categories),
            'artworks' => $artworkCount,
            'media' => $mediaCount,
        ];
    }

    /** @return array{string,list<array<string,mixed>>} */
    private function preflight(string $manifestPath, string $mediaRoot): array
    {
        $manifestFile = realpath($manifestPath);
        if ($manifestFile === false || ! is_file($manifestFile)) {
            throw new RuntimeException('Legacy artwork manifest does not exist.');
        }

        $root = realpath($mediaRoot);
        if ($root === false || ! is_dir($root)) {
            throw new RuntimeException('Legacy media root does not exist.');
        }

        $json = file_get_contents($manifestFile);
        if (! is_string($json)) {
            throw new RuntimeException('Legacy artwork manifest could not be read.');
        }

        try {
            $manifest = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new RuntimeException('Legacy artwork manifest is not valid JSON.', 0, $exception);
        }
        if (! is_array($manifest)) {
            throw new RuntimeException('Legacy artwork manifest root must be an object.');
        }

        $batch = $this->string($manifest, 'batch');
        $categories = $this->list($manifest, 'categories');
        if ($categories === []) {
            throw new RuntimeException('Legacy artwork manifest contains no categories.');
        }

        $categorySlugs = [];
        $navigationPositions = [];
        $artworkSlugs = [];
        $homeCandidates = [];
        $validated = [];

        foreach ($categories as $categoryIndex => $category) {
            if (! is_array($category)) {
                throw new RuntimeException("Category {$categoryIndex} must be an object.");
            }

            $slug = $this->string($category, 'slug');
            if (isset($categorySlugs[$slug])) {
                throw new RuntimeException("Duplicate category slug: {$slug}");
            }
            $categorySlugs[$slug] = true;

            $position = $this->integer($category, 'position');
            if ($position < 0) {
                throw new RuntimeException("Category {$slug} has a negative position.");
            }
            $showInNavigation = $this->boolean($category, 'show_in_navigation');
            if ($showInNavigation) {
                if (isset($navigationPositions[$position])) {
                    throw new RuntimeException("Duplicate public navigation position: {$position}");
                }
                $navigationPositions[$position] = true;
            }

            $artworks = $this->list($category, 'artworks');
            if ($artworks === []) {
                throw new RuntimeException("Category {$slug} contains no artworks.");
            }

            $positions = [];
            $legacyIds = [];
            $validatedArtworks = [];
            foreach ($artworks as $artworkIndex => $artwork) {
                if (! is_array($artwork)) {
                    throw new RuntimeException("Artwork {$slug}/{$artworkIndex} must be an object.");
                }

                $artworkSlug = $this->string($artwork, 'slug');
                if (isset($artworkSlugs[$artworkSlug])) {
                    throw new RuntimeException("Duplicate artwork slug: {$artworkSlug}");
                }
                $artworkSlugs[$artworkSlug] = true;

                $artworkPosition = $this->integer($artwork, 'position');
                if ($artworkPosition < 0 || isset($positions[$artworkPosition])) {
                    throw new RuntimeException("Artwork positions for category {$slug} are invalid or ambiguous.");
                }
                $positions[$artworkPosition] = true;

                $legacyId = $this->integer($artwork, 'legacy_id');
                if (isset($legacyIds[$legacyId])) {
                    throw new RuntimeException("Duplicate legacy id {$legacyId} in category {$slug}.");
                }
                $legacyIds[$legacyId] = true;

                $workYear = $this->integer($artwork, 'work_year');
                if ($workYear < 1000 || $workYear > 9999) {
                    throw new RuntimeException("Artwork {$artworkSlug} has an invalid work year.");
                }
                $datePrecision = $this->string($artwork, 'date_precision');
                if (! in_array($datePrecision, ['year', 'day'], true)) {
                    throw new RuntimeException("Artwork {$artworkSlug} uses an unsupported normalized date precision.");
                }
                $workDate = $this->nullableString($artwork, 'work_date');
                if ($datePrecision === 'year' && $workDate !== null) {
                    throw new RuntimeException("Year-precision artwork {$artworkSlug} must not invent a month or day.");
                }
                if ($datePrecision === 'day') {
                    if ($workDate === null || preg_match('/^\d{4}-\d{2}-\d{2}$/', $workDate) !== 1 || (int) substr($workDate, 0, 4) !== $workYear) {
                        throw new RuntimeException("Day-precision artwork {$artworkSlug} has inconsistent normalized date data.");
                    }
                }

                $mediaPath = $this->string($artwork, 'media_path');
                $sourcePath = $this->sourceMediaPath($root, $mediaPath);
                $expectedBytes = $this->integer($artwork, 'media_byte_size');
                $actualBytes = filesize($sourcePath);
                if (! is_int($actualBytes) || $actualBytes !== $expectedBytes) {
                    throw new RuntimeException("Media byte-size mismatch for {$artworkSlug}.");
                }
                $expectedSha = strtolower($this->string($artwork, 'media_sha256'));
                if (preg_match('/^[a-f0-9]{64}$/', $expectedSha) !== 1 || hash_file('sha256', $sourcePath) !== $expectedSha) {
                    throw new RuntimeException("Media checksum mismatch for {$artworkSlug}.");
                }

                $altText = $this->string($artwork, 'alt_text');
                if (trim($altText) === '') {
                    throw new RuntimeException("Artwork {$artworkSlug} has no canonical ALT text.");
                }

                $featured = $this->boolean($artwork, 'featured_on_home');
                if ($this->boolean($category, 'show_on_home')) {
                    $homeCandidates[] = ['year' => $workYear, 'featured' => $featured, 'slug' => $artworkSlug];
                }

                $validatedArtworks[] = [
                    'legacy_id' => $legacyId,
                    'slug' => $artworkSlug,
                    'title' => $this->string($artwork, 'title'),
                    'position' => $artworkPosition,
                    'legacy_date_raw' => $this->nullableString($artwork, 'legacy_date_raw'),
                    'work_year' => $workYear,
                    'work_date' => $workDate,
                    'date_precision' => $datePrecision,
                    'medium' => $this->nullableString($artwork, 'medium'),
                    'dimensions' => $this->nullableString($artwork, 'dimensions'),
                    'description' => $this->nullableString($artwork, 'description'),
                    'media_path' => $mediaPath,
                    'media_byte_size' => $expectedBytes,
                    'media_sha256' => $expectedSha,
                    'alt_text' => $altText,
                    'featured_on_home' => $featured,
                ];
            }

            $validated[] = [
                'legacy_source' => $this->string($category, 'legacy_source'),
                'slug' => $slug,
                'name' => $this->string($category, 'name'),
                'position' => $position,
                'show_in_navigation' => $showInNavigation,
                'show_on_home' => $this->boolean($category, 'show_on_home'),
                'description' => $this->nullableString($category, 'description'),
                'artworks' => $validatedArtworks,
            ];
        }

        if ($homeCandidates !== []) {
            $latestYear = max(array_column($homeCandidates, 'year'));
            $latest = array_values(array_filter($homeCandidates, static fn (array $item): bool => $item['year'] === $latestYear));
            if (count($latest) > 1 && count(array_filter($latest, static fn (array $item): bool => $item['featured'])) !== 1) {
                throw new RuntimeException("Newest legacy artwork year {$latestYear} is ambiguous and requires exactly one explicit featured_on_home selection.");
            }
        }

        $settings = DB::table('public_content_settings')->where('id', 1)->first();
        if ($settings !== null && (bool) $settings->cv_enabled && $settings->cv_navigation_position !== null && isset($navigationPositions[(int) $settings->cv_navigation_position])) {
            throw new RuntimeException('Artwork navigation collides with the enabled CV navigation position.');
        }

        return [$batch, $validated];
    }

    private function sourceMediaPath(string $mediaRoot, string $relativePath): string
    {
        if ($relativePath === '' || str_contains(str_replace('\\', '/', $relativePath), '../') || str_starts_with($relativePath, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $relativePath) === 1) {
            throw new RuntimeException('Legacy media path must be a safe relative path.');
        }

        $root = realpath($mediaRoot);
        if ($root === false) {
            throw new RuntimeException('Legacy media root does not exist.');
        }
        $candidate = realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath));
        if ($candidate === false || ! is_file($candidate)) {
            throw new RuntimeException("Legacy media file is missing: {$relativePath}");
        }

        $normalizedRoot = rtrim(str_replace('\\', '/', $root), '/').'/';
        $normalizedCandidate = str_replace('\\', '/', $candidate);
        if (! str_starts_with(strtolower($normalizedCandidate), strtolower($normalizedRoot))) {
            throw new RuntimeException('Legacy media path escapes the supplied media root.');
        }

        return $candidate;
    }

    /** @param list<string> $keys */
    private function cleanupStorage(array $keys, Throwable $original): never
    {
        $disk = Storage::disk(config('media.disk'));
        $failed = [];
        foreach (array_values(array_unique($keys)) as $key) {
            try {
                if ($disk->exists($key) && ! $disk->delete($key)) {
                    $failed[] = $key;
                }
            } catch (Throwable) {
                $failed[] = $key;
            }
        }

        if ($failed !== []) {
            throw new RuntimeException('Legacy import failed and storage cleanup failed for: '.implode(', ', $failed).'. Original failure: '.$original->getMessage(), 0, $original);
        }

        throw $original;
    }

    /** @return list<mixed> */
    private function list(array $data, string $key): array
    {
        if (! array_key_exists($key, $data) || ! is_array($data[$key]) || ! array_is_list($data[$key])) {
            throw new RuntimeException("Manifest field {$key} must be a list.");
        }

        return $data[$key];
    }

    private function string(array $data, string $key): string
    {
        if (! array_key_exists($key, $data) || ! is_string($data[$key]) || trim($data[$key]) === '') {
            throw new RuntimeException("Manifest field {$key} must be a non-empty string.");
        }

        return $data[$key];
    }

    private function nullableString(array $data, string $key): ?string
    {
        if (! array_key_exists($key, $data) || ($data[$key] !== null && ! is_string($data[$key]))) {
            throw new RuntimeException("Manifest field {$key} must be an explicit string or null.");
        }

        return $data[$key];
    }

    private function integer(array $data, string $key): int
    {
        if (! array_key_exists($key, $data) || ! is_int($data[$key])) {
            throw new RuntimeException("Manifest field {$key} must be an integer.");
        }

        return $data[$key];
    }

    private function boolean(array $data, string $key): bool
    {
        if (! array_key_exists($key, $data) || ! is_bool($data[$key])) {
            throw new RuntimeException("Manifest field {$key} must be a boolean.");
        }

        return $data[$key];
    }
}
