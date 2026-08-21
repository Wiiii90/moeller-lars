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
    /** @deprecated UI callers should use MediaTypePolicy::maxUploadBytes() or the type-specific policy. */
    public const MAX_BYTES = 20 * 1024 * 1024;

    public const MAX_PIXELS = 16_000_000;

    public const THUMBNAIL_MAX_EDGE = 960;

    public const THUMBNAIL_KIND = 'thumbnail';

    public const TRANSFORM_PROFILE = 'public-v1';

    private const INGEST_LOCK_SECONDS = 120;

    private const VIDEO_PROBE_BYTES = 1024 * 1024;

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
            $prepared = $this->prepare($upload);
            app(MediaCapacityService::class)->assertCanStoreOriginal($prepared['size']);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->validationFailure('The uploaded media could not be processed.', $exception);
        }

        $extension = MediaTypePolicy::extensionFor($prepared['mime']);
        if ($extension === null) {
            throw $this->validationFailure('The uploaded media type is not allowed.');
        }

        $disk = Storage::disk(config('media.disk'));
        $uuid = (string) Str::uuid();
        $originalKey = 'originals/'.$uuid.'.'.$extension;
        $thumbnailKey = $prepared['thumbnail_bytes'] === null
            ? null
            : 'variants/'.$uuid.'-'.self::THUMBNAIL_KIND.'.webp';
        $storageKeys = array_values(array_filter([$originalKey, $thumbnailKey]));

        foreach ($storageKeys as $key) {
            if ($disk->exists($key)) {
                throw new RuntimeException('Generated media storage key already exists.');
            }
        }

        try {
            $originalStream = @fopen($prepared['path'], 'rb');
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

            if ($thumbnailKey !== null && $prepared['thumbnail_bytes'] !== null && ! $disk->put($thumbnailKey, $prepared['thumbnail_bytes'])) {
                throw new RuntimeException('Unable to write media thumbnail.');
            }

            return DB::transaction(function () use ($originalKey, $upload, $prepared, $thumbnailKey): MediaAsset {
                $asset = new MediaAsset;
                $asset->fill([
                    'storage_key' => $originalKey,
                    'original_filename' => $this->basename($upload->getClientOriginalName()),
                    'mime_type' => $prepared['mime'],
                    'byte_size' => $prepared['size'],
                    'sha256' => $prepared['sha256'],
                    'state' => 'available',
                    'width' => $prepared['width'],
                    'height' => $prepared['height'],
                ]);
                $asset->save();

                if ($thumbnailKey !== null && $prepared['thumbnail_bytes'] !== null) {
                    $asset->variants()->create([
                        'variant_kind' => self::THUMBNAIL_KIND,
                        'storage_key' => $thumbnailKey,
                        'mime_type' => 'image/webp',
                        'byte_size' => strlen($prepared['thumbnail_bytes']),
                        'sha256' => hash('sha256', $prepared['thumbnail_bytes']),
                        'transform_profile' => self::TRANSFORM_PROFILE,
                        'state' => 'available',
                        'width' => $prepared['thumbnail_width'],
                        'height' => $prepared['thumbnail_height'],
                    ]);
                }

                return $asset;
            });
        } catch (Throwable $exception) {
            $failed = [];
            foreach ($storageKeys as $key) {
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

    /**
     * @return array{mime:string,width:?int,height:?int,path:string,size:int,sha256:string,thumbnail_bytes:?string,thumbnail_width:?int,thumbnail_height:?int}
     */
    private function prepare(UploadedFile $upload): array
    {
        if (! $upload->isValid()) {
            throw $this->validationFailure('The upload was not successful.');
        }

        $path = $upload->getRealPath();
        if (! is_string($path) || ! is_file($path) || ! is_readable($path)) {
            throw $this->validationFailure('The uploaded media could not be read completely.');
        }

        $mime = $this->detectMime($path);
        if (MediaTypePolicy::extensionFor($mime) === null) {
            throw $this->validationFailure('The uploaded media type is not allowed.');
        }

        $size = $upload->getSize();
        if (! is_int($size) || $size <= 0 || $size > MediaTypePolicy::maxBytesFor($mime)) {
            throw $this->validationFailure('The uploaded media has an invalid size for this media type.');
        }

        $sha256 = @hash_file('sha256', $path);
        if (! is_string($sha256) || strlen($sha256) !== 64) {
            throw $this->validationFailure('The uploaded media could not be read completely.');
        }

        if (MediaTypePolicy::isVideo($mime)) {
            $this->validateVideoContainer($path, $mime, $size);

            return [
                'mime' => $mime,
                'width' => null,
                'height' => null,
                'path' => $path,
                'size' => $size,
                'sha256' => $sha256,
                'thumbnail_bytes' => null,
                'thumbnail_width' => null,
                'thumbnail_height' => null,
            ];
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

        return [
            'mime' => $mime,
            'width' => $width,
            'height' => $height,
            'path' => $path,
            'size' => $size,
            'sha256' => $sha256,
            'thumbnail_bytes' => $thumbnailBytes,
            'thumbnail_width' => $thumbnailWidth,
            'thumbnail_height' => $thumbnailHeight,
        ];
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

    private function validateVideoContainer(string $path, string $mime, int $size): void
    {
        [$head, $tail] = $this->videoProbe($path, $size);
        $probe = $head.$tail;

        if ($mime === 'video/mp4') {
            $isMp4 = strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp';
            $hasH264 = str_contains($probe, 'avc1') || str_contains($probe, 'avc3');
            if (! $isMp4 || ! $hasH264) {
                throw $this->validationFailure('MP4 uploads must use a browser-native H.264 video track.');
            }

            return;
        }

        if ($mime === 'video/webm') {
            $isWebm = str_starts_with($head, "\x1A\x45\xDF\xA3");
            $hasSupportedVideo = str_contains($probe, 'V_VP8')
                || str_contains($probe, 'V_VP9')
                || str_contains($probe, 'V_AV1');
            if (! $isWebm || ! $hasSupportedVideo) {
                throw $this->validationFailure('WebM uploads must use a browser-native VP8, VP9 or AV1 video track.');
            }

            return;
        }

        throw $this->validationFailure('The uploaded video type is not allowed.');
    }

    /** @return array{string,string} */
    private function videoProbe(string $path, int $size): array
    {
        $stream = @fopen($path, 'rb');
        if (! is_resource($stream)) {
            throw $this->validationFailure('The uploaded video could not be inspected.');
        }

        try {
            $readLength = min(self::VIDEO_PROBE_BYTES, $size);
            $head = fread($stream, $readLength);
            if (! is_string($head)) {
                throw $this->validationFailure('The uploaded video could not be inspected.');
            }

            $tail = '';
            if ($size > self::VIDEO_PROBE_BYTES) {
                if (fseek($stream, max(0, $size - self::VIDEO_PROBE_BYTES)) !== 0) {
                    throw $this->validationFailure('The uploaded video could not be inspected.');
                }
                $tailBytes = fread($stream, self::VIDEO_PROBE_BYTES);
                if (! is_string($tailBytes)) {
                    throw $this->validationFailure('The uploaded video could not be inspected.');
                }
                $tail = $tailBytes;
            }

            return [$head, $tail];
        } finally {
            fclose($stream);
        }
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
