<?php

namespace Database\Seeders;

use App\Domain\Media\PublicMedia;
use App\Domain\Publication\PublicationService;
use App\Models\Artwork;
use App\Models\ArtworkCategory;
use App\Models\ArtworkMedia;
use App\Models\BlogPost;
use App\Models\ContactMessage;
use App\Models\Exhibition;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\SiteSection;
use App\Models\User;
use App\Support\LocalPreviewDatabaseGuard;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

final class BrowserProfilingSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('testing') || env('MOELLER_LARS_BROWSER_PROFILE') !== '1') {
            throw new RuntimeException('Browser profiling fixtures may only be created in the explicit disposable testing context.');
        }

        // Match the Pest guard: fixtures may never touch the persistent local preview.
        LocalPreviewDatabaseGuard::assertDisposableTestContext();

        $email = trim((string) env('PLAYWRIGHT_ADMIN_EMAIL'));
        $password = (string) env('PLAYWRIGHT_ADMIN_PASSWORD');

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($password) < 16) {
            throw new RuntimeException('Valid synthetic Playwright admin credentials are required.');
        }

        $admin = new User;
        $admin->forceFill([
            'name' => 'Playwright Admin',
            'email' => $email,
            'email_verified_at' => now(),
            'password' => Hash::make($password),
            'is_admin' => true,
        ]);
        $admin->save();

        // Required browser interaction fixtures: never silently skip selection tests.
        $media = [];
        foreach (['committed', 'unreferenced', 'workingOnly'] as $index => $name) {
            $media[$name] = $this->syntheticMedia($name, $index);
        }
        $publicSettings = [];
        foreach ([1, 2] as $number) {
            $section = SiteSection::query()->create([
                'type' => SiteSection::TYPE_CUSTOM,
                'template' => null,
                'title' => 'Browser fixture page '.$number,
                'navigation_label' => 'Browser fixture page '.$number,
                'slug' => 'browser-fixture-page-'.$number,
                'state' => 'hidden',
                'position' => 1000 + $number * 10,
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);
            $section->customPageSetting()->create(['blocks' => []]);

            ContactMessage::query()->create([
                'sender_name' => 'Browser fixture '.$number,
                'sender_email' => 'fixture-'.$number.'@example.test',
                'message' => 'Disposable fixture for dashboard selection',
                'mail_delivery_status' => ContactMessage::DELIVERY_PENDING,
            ]);
        }

        // A committed public pair is necessary for genuine SPA navigation,
        // History back/forward and content-swap browser tests. Keep the two
        // editorial draft fixtures above unchanged for the filter tests.
        foreach ([1, 2] as $number) {
            $section = SiteSection::query()->create([
                'type' => SiteSection::TYPE_CUSTOM,
                'template' => null,
                'title' => 'Browser public SPA '.$number,
                'navigation_label' => 'Browser public SPA '.$number,
                'slug' => 'browser-public-spa-'.$number,
                'state' => 'published',
                'position' => 1100 + $number * 10,
                'parent_id' => null,
                'artwork_category_id' => null,
            ]);
            $publicSettings[$number] = $section->customPageSetting()->create([
                'blocks' => [[
                    'type' => 'text',
                    'published' => true,
                    'title' => 'Public SPA content '.$number,
                    'body' => null,
                ], [
                    'type' => 'image',
                    'published' => true,
                    'media_asset_id' => $media['committed']['id'],
                    'image_decorative' => false,
                    'image_scale_percent' => $number === 1 ? 50 : 75,
                ]],
            ]);
        }

        $category = ArtworkCategory::query()->create([
            'name' => 'Browser Gallery', 'slug' => 'browser-gallery',
            // Disposable fixture opts in. Production Galleries default off.
            'landscape_detail_enabled' => true,
            'edge_navigation_mode' => 'cyclic',
        ]);
        SiteSection::query()->create([
            'type' => SiteSection::TYPE_GALLERY, 'title' => 'Browser Gallery',
            'navigation_label' => 'Browser Gallery', 'slug' => 'browser-gallery',
            'state' => 'published', 'position' => 1200, 'parent_id' => null,
            'artwork_category_id' => $category->id,
        ]);
        $media['gallery'] = ['url' => '/browser-gallery', 'artworks' => []];
        foreach ([1, 2] as $number) {
            $fixture = $this->syntheticMedia('viewer-'.$number, $number - 1, 1600, 1200);
            $artwork = Artwork::query()->create([
                'artwork_category_id' => $category->id, 'slug' => 'browser-artwork-'.$number,
                'title' => 'Browser Artwork '.$number, 'state' => 'published',
                'position' => $number * 10, 'work_year' => 2026, 'published_at' => now(),
                'description' => $number === 1
                    ? 'A long artwork description for checking the landscape gallery detail layout at different viewport widths. The narrative and its divider must use the full available artwork track without clipping or affecting other pages.'
                    : null,
            ]);
            ArtworkMedia::query()->create([
                'artwork_id' => $artwork->id, 'media_asset_id' => $fixture['id'],
                'role' => 'primary', 'position' => 10,
            ]);
            $media['gallery']['artworks'][] = $fixture + ['title' => $artwork->title, 'url' => route('artworks.show', $artwork->slug, false)];
        }

        // Independent editorial ordering fixtures: never reorder the public viewer pair.
        $sortGallery = ArtworkCategory::query()->create(['name' => 'Browser Sort Gallery', 'slug' => 'browser-sort-gallery']);
        SiteSection::query()->create([
            'type' => SiteSection::TYPE_GALLERY, 'title' => 'Browser Sort Gallery',
            'navigation_label' => 'Browser Sort Gallery', 'slug' => 'browser-sort-gallery',
            'state' => 'published', 'position' => 1210, 'parent_id' => null,
            'artwork_category_id' => $sortGallery->id,
        ]);
        $sortMedia = $this->syntheticMedia('gallery-sort', 0);
        foreach ([1, 2, 3] as $number) {
            $artwork = Artwork::query()->create([
                'artwork_category_id' => $sortGallery->id, 'slug' => 'browser-sort-artwork-'.$number,
                'title' => 'Browser Sort Artwork '.$number, 'state' => 'published',
                'position' => $number - 1, 'work_year' => 2026, 'published_at' => now(),
            ]);
            ArtworkMedia::query()->create([
                'artwork_id' => $artwork->id, 'media_asset_id' => $sortMedia['id'],
                'role' => 'primary', 'position' => 10,
            ]);
        }

        // Twenty-six is the smallest sequence exceeding the 25-row browser view.
        foreach (['blog' => BlogPost::class, 'exhibitions' => Exhibition::class] as $template => $entryClass) {
            $section = SiteSection::query()->create([
                'type' => SiteSection::TYPE_JOURNAL, 'template' => $template,
                'title' => 'Browser Sort '.$template, 'navigation_label' => 'Browser Sort '.$template,
                'slug' => 'browser-sort-'.$template, 'state' => 'published',
                'position' => $template === 'blog' ? 1220 : 1230, 'parent_id' => null,
            ]);
            foreach (range(1, 26) as $position) {
                $entryClass::query()->create([
                    'site_section_id' => $section->id, 'slug' => 'browser-sort-'.$template.'-'.$position,
                    'title' => 'Browser '.$template.' entry '.$position, 'state' => 'published',
                    'position' => $position, 'published_at' => now(),
                    ...($template === 'blog'
                        ? ['body' => 'Synthetic Journal ordering content.']
                        : ['starts_on' => '2020-01-01', 'ends_on' => '2020-01-02']),
                ]);
            }
        }

        if (app(PublicationService::class)->commit($admin, 'Browser public SPA and Gallery fixtures') === null) {
            throw new RuntimeException('Browser SPA fixtures must be published before running public E2E tests.');
        }

        // Deliberately diverge Working from Committed in both directions:
        // removing a Working reference must not revoke LIVE media, while a
        // new Working-only reference must never publish another stored file.
        foreach ($publicSettings as $number => $settings) {
            $blocks = [$settings->components()[0]];
            if ($number === 2) {
                $blocks[] = [
                    'type' => 'image', 'published' => true,
                    'media_asset_id' => $media['workingOnly']['id'],
                    'image_decorative' => false,
                ];
            }
            $settings->update(['blocks' => $blocks]);
        }
        Storage::disk('local')->put('browser-public-media.json', json_encode($media, JSON_THROW_ON_ERROR));
    }

    /** @return array<string, mixed> */
    private function syntheticMedia(string $name, int $index, int $width = 64, int $height = 48): array
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 50 + $index * 50, 90, 150));
        imagefilledrectangle($image, (int) ($width / 8), (int) ($height / 8), (int) ($width * 5 / 8), (int) ($height * 5 / 8), imagecolorallocate($image, 210, 180, 80));
        ob_start();
        imagejpeg($image, null, 95);
        $original = ob_get_clean();
        $thumbnailWidth = min(320, (int) ($width / 2));
        $thumbnailHeight = (int) ($height * $thumbnailWidth / $width);
        $thumbnail = imagescale($image, $thumbnailWidth, $thumbnailHeight);
        ob_start();
        imagewebp($thumbnail, null, 100);
        $thumbBytes = ob_get_clean();
        imagedestroy($image);
        imagedestroy($thumbnail);
        if (! is_string($original) || ! is_string($thumbBytes) || $original === '' || $thumbBytes === '') {
            throw new RuntimeException('Synthetic browser media encoding failed.');
        }
        $disk = Storage::disk(config('media.disk'));
        $originalKey = 'browser-public-e2e/'.$name.'/original.jpg';
        $thumbnailKey = 'browser-public-e2e/'.$name.'/thumbnail.webp';
        if (! $disk->put($originalKey, $original) || ! $disk->put($thumbnailKey, $thumbBytes)) {
            throw new RuntimeException('Synthetic browser media could not be stored.');
        }
        $asset = MediaAsset::query()->create([
            'storage_key' => $originalKey, 'original_filename' => 'browser-'.$name.'.jpg',
            'mime_type' => 'image/jpeg', 'byte_size' => strlen($original),
            'sha256' => hash('sha256', $original), 'state' => 'available',
            'alt_text' => 'Browser '.$name.' media', 'width' => $width, 'height' => $height,
        ]);
        $variant = MediaVariant::query()->create([
            'media_asset_id' => $asset->id, 'variant_kind' => PublicMedia::THUMBNAIL_KIND,
            'transform_profile' => PublicMedia::PUBLIC_TRANSFORM_PROFILE,
            'storage_key' => $thumbnailKey, 'mime_type' => 'image/webp',
            'byte_size' => strlen($thumbBytes), 'sha256' => hash('sha256', $thumbBytes),
            'state' => 'available', 'width' => $thumbnailWidth, 'height' => $thumbnailHeight,
        ]);

        return [
            'id' => $asset->id,
            'original' => ['url' => route('media.original', $asset, false), 'sha256' => $asset->sha256,
                'byteSize' => $asset->byte_size, 'mime' => $asset->mime_type, 'width' => $width, 'height' => $height],
            'thumbnail' => ['url' => route('media.variant', $variant, false), 'sha256' => $variant->sha256,
                'byteSize' => $variant->byte_size, 'mime' => $variant->mime_type, 'width' => $thumbnailWidth, 'height' => $thumbnailHeight],
        ];
    }
}
