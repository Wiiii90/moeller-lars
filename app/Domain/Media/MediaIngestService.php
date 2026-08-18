<?php

namespace App\Domain\Media;

use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class MediaIngestService
{
    public const MAX_BYTES = 20 * 1024 * 1024;

    public const MAX_PIXELS = 16_000_000;

    public const THUMBNAIL_MAX_EDGE = 960;

    public const THUMBNAIL_KIND = 'thumbnail';

    public const TRANSFORM_PROFILE = 'public-v1';

    private const INGEST_LOCK_SECONDS = 120;

    /**
     * @var array<string, string>
     */
    private const MIME_EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function ingest(UploadedFile $upload): MediaAsset
    {
        $lock = Cache::lock('media-ingest', self::INGEST_LOCK_SECONDS);
        if (! $lock->get()) {
            throw $this->validationFailure('Another media upload is currently being processed. Try again shortly.');
        }

        try {
            return $this->ingestLocked($upload);
        } finally {
            $lock->release();
        }
    }

    private function ingestLocked(UploadedFile $upload): MediaAsset
    {
        try {
            [$mime, $width, $height, $originalPath, $originalSize, $originalSha256, $thumbnailBytes, $thumbnailWidth, $thumbnailHeight] = $this->prepare($upload);
            app(MediaCapacityService::class)->assertCanStoreOriginal($originalSize);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->validationFailure('The uploaded media could not be processed.', $exception);
        }

        $disk = Storage::disk(config('media.disk'));
        $uuid = (string) Str::uuid();
        $originalKey = 'originals/'.$uuid.'.'.self::MIME_EXTENSIONS[$mime];
        $thumbnailKey = 'variants/'.$uuid.'-'.self::THUMBNAIL_KIND.'.webp';

        if ($disk->exists($originalKey) || $disk->exists($thumbnailKey)) {
            throw new RuntimeException('Generated media storage key already exists.');
        }

        try {
            $originalStream = @fopen($originalPath, 'rb');
            if (! is_resource($originalStream)) {
                throw $this->validationFailure('The uploaded media could not be read completely.');
            }

            try {
                if (! $disk->put($originalKey, $originalStream)) {
                    throw new RuntimeException('Unable to write canonical media.');
                }
            } finally {
                fclose($originalStream);
            }

            if (! $disk->put($thumbnailKey, $thumbnailBytes)) {
                throw new RuntimeException('Unable to write media thumbnail.');
            }

            return DB::transaction(function () use ($originalKey, $upload, $mime, $originalSize, $originalSha256, $width, $height, $thumbnailKey, $thumbnailBytes, $thumbnailWidth, $thumbnailHeight): MediaAsset {
                $asset = new MediaAsset;
                $asset->fill([
                    'storage_key' => $originalKey,
                    'original_filename' => $this->basename($upload->getClientOriginalName()),
                    'mime_type' => $mime,
                    'byte_size' => $originalSize,
                    'sha256' => $originalSha256,
                    'state' => 'available',
                    'width' => $width,
                    'height' => $height,
                ]);
                $asset->save();

                $asset->variants()->create([
                    'variant_kind' => self::THUMBNAIL_KIND,
                    'storage_key' => $thumbnailKey,
                    'mime_type' => 'image/webp',
                    'byte_size' => strlen($thumbnailBytes),
                    'sha256' => hash('sha256', $thumbnailBytes),
                    'transform_profile' => self::TRANSFORM_PROFILE,
                    'state' => 'available',
                    'width' => $thumbnailWidth,
                    'height' => $thumbnailHeight,
                ]);

                return $asset;
            });
        } catch (Throwable $exception) {
            $failed = [];
            foreach ([$originalKey, $thumbnailKey] as $key) {
                try {
                    if ($disk->exists($key) && ! $disk->delete($key)) {
                        $failed[] = $key;

                        continue;
                    }
                    if ($disk->exists($key)) {
                        $failed[] = $key;
                    }
                } catch (Throwable) {
                    $failed[] = $key;
                }
            }

            if ($failed !== []) {
                throw new RuntimeException(
                    'Media ingest failed and storage cleanup also failed for: '.implode(', ', array_unique($failed)).'. Original failure: '.$exception->getMessage(),
                    0,
                    $exception,
                );
            }

            throw $exception;
        }
    }

    /** @return array{string, int, int, string, int, string, string, int, int} */
    private function prepare(UploadedFile $upload): array
    {
        if (! $upload->isValid()) {
            throw $this->validationFailure('The upload was not successful.');
        }

        $size = $upload->getSize();
        if (! is_int($size) || $size <= 0 || $size > self::MAX_BYTES) {
            throw $this->validationFailure('The uploaded media has an invalid size.');
        }

        $path = $upload->getRealPath();
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw $this->validationFailure('The uploaded media could not be read completely.');
        }

        $mime = $this->detectMime($path);
        if (! isset(self::MIME_EXTENSIONS[$mime])) {
            throw $this->validationFailure('The uploaded media type is not allowed.');
        }

        $dimensions = @getimagesize($path);
        if ($dimensions === false) {
            throw $this->validationFailure('The uploaded media is not a valid image.');
        }

        $width = $dimensions[0];
        $height = $dimensions[1];
        if ($width <= 0 || $height <= 0 || ($width * $height) > self::MAX_PIXELS) {
            throw $this->validationFailure('The uploaded image dimensions are not allowed.');
        }

        $sha256 = @hash_file('sha256', $path);
        if (! is_string($sha256) || strlen($sha256) !== 64) {
            throw $this->validationFailure('The uploaded media could not be read completely.');
        }

        $originalImage = $this->decode($path, $mime);
        $thumbnailImage = null;

        try {
            $thumbnailImage = $this->makeThumbnail($originalImage, $width, $height);
            $thumbnailWidth = imagesx($thumbnailImage);
            $thumbnailHeight = imagesy($thumbnailImage);
            $thumbnailBytes = $this->encode($thumbnailImage, 'image/webp', 85);
        } finally {
            $this->destroyImage($thumbnailImage);
            $this->destroyImage($originalImage);
        }

        return [$mime, $width, $height, $path, $size, $sha256, $thumbnailBytes, $thumbnailWidth, $thumbnailHeight];
    }

    private function detectMime(string $path): string
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            throw $this->validationFailure('The uploaded media type could not be detected.');
        }

        try {
            $mime = finfo_file($fileInfo, $path);
        } finally {
            finfo_close($fileInfo);
        }

        if (! is_string($mime)) {
            throw $this->validationFailure('The uploaded media type could not be detected.');
        }

        return $mime;
    }

    private function decode(string $path, string $mime): \GdImage
    {
        $image = match ($mime) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
            default => false,
        };

        if (! $image instanceof \GdImage) {
            throw $this->validationFailure('The uploaded image could not be decoded.');
        }

        return $image;
    }

    private function encode(\GdImage $image, string $mime, int $quality): string
    {
        $this->configureAlpha($image, $mime);
        ob_start();

        try {
            $encoded = match ($mime) {
                'image/jpeg' => imagejpeg($image, null, $quality),
                'image/png' => imagepng($image, null, $quality),
                'image/webp' => imagewebp($image, null, $quality),
            };
            $bytes = ob_get_clean();
        } catch (Throwable $exception) {
            ob_end_clean();
            throw $exception;
        }

        if ($encoded !== true || ! is_string($bytes) || $bytes === '') {
            throw $this->validationFailure('The uploaded image could not be encoded.');
        }

        return $bytes;
    }

    private function makeThumbnail(\GdImage $image, int $width, int $height): \GdImage
    {
        $scale = min(1, self::THUMBNAIL_MAX_EDGE / max($width, $height));
        $thumbnailWidth = max(1, (int) round($width * $scale));
        $thumbnailHeight = max(1, (int) round($height * $scale));
        $thumbnail = imagecreatetruecolor($thumbnailWidth, $thumbnailHeight);
        if (! $thumbnail instanceof \GdImage) {
            throw $this->validationFailure('The thumbnail could not be created.');
        }

        $this->configureAlpha($thumbnail, 'image/webp');
        if (! imagecopyresampled($thumbnail, $image, 0, 0, 0, 0, $thumbnailWidth, $thumbnailHeight, $width, $height)) {
            imagedestroy($thumbnail);
            throw $this->validationFailure('The thumbnail could not be created.');
        }

        return $thumbnail;
    }

    private function configureAlpha(\GdImage $image, string $mime): void
    {
        if ($mime === 'image/jpeg') {
            return;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    private function basename(string $filename): string
    {
        return basename(str_replace('\\', '/', $filename));
    }

    private function destroyImage(mixed &$image): void
    {
        if ($image instanceof \GdImage) {
            imagedestroy($image);
        }

        $image = null;
    }

    private function validationFailure(string $message, ?Throwable $previous = null): ValidationException
    {
        return ValidationException::withMessages(['media' => $message]);
    }
}
