<?php

namespace App\Domain\Media;

use App\Jobs\PrepareMediaRightsImage;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

class MediaIngestService
{
    // All newly ingested images use bounded libvips. GD is retained only in
    // MediaVariantRegenerationService for historical immutable thumbnails.
    // Allow native high-resolution camera images and approximately 400 MP
    // multi-shot composites, with headroom, while retaining a finite
    // decompression safety bound. Runtime resource limits still apply.
    public const MAX_PIXELS = 500_000_000;
    public const LARGE_IMAGE_TRANSFORM_PROFILE = 'public-v2';
    public const VIDEO_POSTER_KIND = 'poster';
    public const VIDEO_POSTER_PROFILE = 'video-poster-v1';

    public const THUMBNAIL_MAX_EDGE = 960;

    public const THUMBNAIL_KIND = 'thumbnail';

    public const TRANSFORM_PROFILE = 'public-v1';

    // A 512 MiB original may require hashing, container probing, poster generation
    // and storage admission. Keep the atomic ingest lock beyond the bounded
    // web request so concurrent uploads cannot race the shared storage quota.
    private const INGEST_LOCK_SECONDS = 420;

    private const MEDIA_PROBE_BYTES = 1024 * 1024;

    public function ingest(UploadedFile $upload): MediaAsset
    {
        $lock = $this->acquireIngestLock();

        try {
            $asset = $this->ingestLocked($upload);
            if (config('media.rights_preparation_enabled', false) === true) {
                PrepareMediaRightsImage::dispatch((int) $asset->getKey())->afterCommit();
            }
            $this->refreshCapacitySnapshot();

            return $asset;
        } finally {
            $lock->release();
        }
    }

    /** @return array{asset:MediaAsset,duplicate:bool} */
    public function ingestUnique(UploadedFile $upload, bool $fromPrivateStaging = false): array
    {
        $lock = $this->acquireIngestLock();

        try {
            $duplicate = $this->findReusableOriginal($upload);
            if ($duplicate instanceof MediaAsset) {
                return ['asset' => $duplicate, 'duplicate' => true];
            }

            $prepared = $this->prepareForIngest($upload);
            $asset = $this->storePreparedLocked($upload, $prepared, $fromPrivateStaging);
            if (config('media.rights_preparation_enabled', false) === true) {
                PrepareMediaRightsImage::dispatch((int) $asset->getKey())->afterCommit();
            }
            $this->refreshCapacitySnapshot();

            return [
                'asset' => $asset,
                'duplicate' => false,
            ];
        } finally {
            $lock->release();
        }
    }

    private function findReusableOriginal(UploadedFile $upload): ?MediaAsset
    {
        $path = $upload->getRealPath();
        $size = $upload->getSize();
        if (! $upload->isValid() || ! is_string($path) || ! is_file($path) || ! is_int($size) || $size <= 0) {
            return null;
        }
        $sha = @hash_file('sha256', $path);
        if (! is_string($sha) || strlen($sha) !== 64) {
            return null;
        }
        $asset = MediaAsset::query()->with('variants')
            ->where('state', 'available')->where('sha256', $sha)->where('byte_size', $size)->first();
        if (! $asset instanceof MediaAsset
            || $size > MediaTypePolicy::maxBytesFor((string) $asset->getAttribute('mime_type'))
            || ! Storage::disk((string) config('media.disk'))->exists((string) $asset->getAttribute('storage_key'))) {
            return null;
        }
        $mime = (string) $asset->getAttribute('mime_type');
        if (MediaTypePolicy::isImage($mime) && MediaPreviewVariant::imageThumbnail($asset) === null) {
            return null;
        }
        if (MediaTypePolicy::isVideo($mime) && MediaPreviewVariant::videoPoster($asset) === null) {
            return null;
        }
        return $asset;
    }
    private function acquireIngestLock(): mixed
    {
        $lock = Cache::lock('media-ingest', self::INGEST_LOCK_SECONDS);
        if (! $lock->get()) {
            throw $this->validationFailure('Another media upload is currently being processed. Try again shortly.');
        }

        return $lock;
    }

    private function refreshCapacitySnapshot(): void
    {
        $capacity = app(MediaCapacityService::class);

        try {
            $capacity->refreshCachedSnapshot();
        } catch (Throwable $exception) {
            report($exception);
            $capacity->forgetCachedSnapshot();
        }
    }

    private function ingestLocked(UploadedFile $upload): MediaAsset
    {
        return $this->storePreparedLocked($upload, $this->prepareForIngest($upload));
    }

    /**
     * @return array{mime:string,width:?int,height:?int,path:string,size:int,sha256:string,thumbnail_bytes:?string,thumbnail_width:?int,thumbnail_height:?int}
     */
    private function prepareForIngest(UploadedFile $upload): array
    {
        try {
            return $this->prepare($upload);
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable $exception) {
            throw $this->validationFailure('The uploaded media could not be processed.', $exception);
        }
    }

    /**
     * @param  array{mime:string,width:?int,height:?int,path:string,size:int,sha256:string,thumbnail_bytes:?string,thumbnail_width:?int,thumbnail_height:?int}  $prepared
     */
    private function storePreparedLocked(UploadedFile $upload, array $prepared, bool $fromPrivateStaging = false): MediaAsset
    {
        try {
            app(MediaCapacityService::class)->assertCanStoreOriginal(
                $prepared['size'],
                $prepared['thumbnail_bytes'] !== null ? strlen($prepared['thumbnail_bytes']) : 0,
                originalAlreadyStaged: $fromPrivateStaging,
            );
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
        $isVideo = MediaTypePolicy::isVideo($prepared['mime']);
        $variantKind = $isVideo ? self::VIDEO_POSTER_KIND : self::THUMBNAIL_KIND;
        $transformProfile = $isVideo
            ? self::VIDEO_POSTER_PROFILE
            : self::LARGE_IMAGE_TRANSFORM_PROFILE;
        $thumbnailKey = $prepared['thumbnail_bytes'] === null
            ? null
            : 'variants/'.$uuid.'-'.$variantKind.'.webp';
        $storageKeys = [$originalKey];
        if ($thumbnailKey !== null) {
            $storageKeys[] = $thumbnailKey;
        }

        foreach ($storageKeys as $key) {
            if ($disk->exists($key)) {
                throw new RuntimeException('Generated media storage key already exists.');
            }
        }

        $movedStagedOriginal = false;
        try {
            if ($fromPrivateStaging) {
                // A multi-gigabyte original is moved in-place on the private
                // media volume; copying it would temporarily violate quota.
                $source = realpath($prepared['path']);
                $stagingDir = realpath(storage_path('app/private/incoming-media'));
                $destination = $disk->path($originalKey);
                if (! is_string($source) || ! is_string($stagingDir)
                    || dirname($source) !== $stagingDir
                    || (! is_dir(dirname($destination))
                        && ! @mkdir(dirname($destination), 0700, true))
                    || ! @rename($source, $destination)) {
                    throw new RuntimeException('The staged original could not be moved into Storage.');
                }
                $movedStagedOriginal = true;
            } else {
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
            }

            if ($thumbnailKey !== null && ! $disk->put($thumbnailKey, $prepared['thumbnail_bytes'])) {
                throw new RuntimeException('Unable to write media thumbnail.');
            }

            return DB::transaction(function () use ($originalKey, $upload, $prepared, $thumbnailKey, $variantKind, $transformProfile): MediaAsset {
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
                    'copyright_notice_mode' => MediaAsset::COPYRIGHT_INHERIT,
                ]);
                $asset->save();

                if ($thumbnailKey !== null && $prepared['thumbnail_bytes'] !== null) {
                    $asset->variants()->create([
                        'variant_kind' => $variantKind,
                        'storage_key' => $thumbnailKey,
                        'mime_type' => 'image/webp',
                        'byte_size' => strlen($prepared['thumbnail_bytes']),
                        'sha256' => hash('sha256', $prepared['thumbnail_bytes']),
                        'transform_profile' => $transformProfile,
                        'state' => 'available',
                        'width' => $prepared['thumbnail_width'],
                        'height' => $prepared['thumbnail_height'],
                    ]);
                }

                return $asset;
            });
        } catch (Throwable $exception) {
            $failed = [];
            if ($movedStagedOriginal && $disk->exists($originalKey)
                && ! @rename($disk->path($originalKey), $prepared['path'])) {
                // Preserve the sole surviving copy for explicit recovery.
                throw new RuntimeException(
                    'Media ingest failed; its staged original could not be restored from '.$originalKey,
                    0,
                    $exception,
                );
            }
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

        $size = $upload->getSize();
        if (! is_int($size) || $size <= 0) {
            throw $this->validationFailure('The uploaded media has an invalid size.');
        }

        $mime = $this->detectMime($path, $size);
        if (MediaTypePolicy::extensionFor($mime) === null) {
            throw $this->validationFailure('The uploaded media type is not allowed.');
        }

        if ($size > MediaTypePolicy::maxBytesFor($mime)) {
            throw $this->validationFailure('The uploaded media has an invalid size for this media type.');
        }

        $sha256 = @hash_file('sha256', $path);
        if (! is_string($sha256) || strlen($sha256) !== 64) {
            throw $this->validationFailure('The uploaded media could not be read completely.');
        }

        if (MediaTypePolicy::isVideo($mime)) {
            $this->validateVideoContainer($path, $mime, $size);
            $poster = app(MediaDerivativeGenerator::class)->videoPoster($path);

            return [
                ...$this->nonImagePrepared($mime, $path, $size, $sha256),
                'thumbnail_bytes' => $poster['bytes'],
                'thumbnail_width' => $poster['width'],
                'thumbnail_height' => $poster['height'],
            ];
        }

        if (MediaTypePolicy::isAudio($mime)) {
            $this->validateAudioContainer($path, $mime, $size);

            return $this->nonImagePrepared($mime, $path, $size, $sha256);
        }

        $dimensions = @getimagesize($path);
        if ($dimensions === false) {
            throw $this->validationFailure('The uploaded media is not a valid image.');
        }

        $width = $dimensions[0];
        $height = $dimensions[1];
        if ($width <= 0 || $height <= 0 || ($width * $height) > self::MAX_PIXELS) {
            throw $this->validationFailure(sprintf(
                'The uploaded image dimensions (%d x %d pixels) are not allowed (maximum %d megapixels).',
                $width,
                $height,
                intdiv(self::MAX_PIXELS, 1_000_000),
            ));
        }

        $preview = app(MediaDerivativeGenerator::class)->imageThumbnail($path);
        $thumbnailBytes = $preview['bytes'];
        $thumbnailWidth = $preview['width'];
        $thumbnailHeight = $preview['height'];

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

    /**
     * @return array{mime:string,width:null,height:null,path:string,size:int,sha256:string,thumbnail_bytes:null,thumbnail_width:null,thumbnail_height:null}
     */
    private function nonImagePrepared(string $mime, string $path, int $size, string $sha256): array
    {
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

    private function detectMime(string $path, int $size): string
    {
        $fileInfo = finfo_open(FILEINFO_MIME_TYPE);
        if ($fileInfo === false) {
            throw $this->validationFailure('The uploaded media type could not be detected.');
        }

        try {
            $detected = finfo_file($fileInfo, $path);
        } finally {
            finfo_close($fileInfo);
        }

        if (! is_string($detected)) {
            throw $this->validationFailure('The uploaded media type could not be detected.');
        }

        if (MediaTypePolicy::isImage($detected)) {
            return $detected;
        }

        [$head, $tail] = $this->mediaProbe($path, $size);
        $probe = $head.$tail;

        if ($this->isMp4Container($head)) {
            // The real stream can be anywhere in the MP4 container; a
            // compatible brand or an arbitrary sampled 'avc1' string does
            // not establish an H.264 track.
            if ($this->probeVideoStream($path) !== null) {
                return 'video/mp4';
            }

            if ($this->hasMp4AudioTrack($probe) && ! $this->hasVideoTrack($probe)) {
                return 'audio/mp4';
            }
        }

        if ($this->isWaveContainer($head)) {
            return 'audio/wav';
        }

        if ($this->isOggAudio($head, $probe)) {
            return 'audio/ogg';
        }

        if ($detected === 'audio/mpeg' || $this->hasLeadingMpegAudioSignature($head)) {
            return 'audio/mpeg';
        }

        return match ($detected) {
            'audio/x-wav', 'audio/vnd.wave' => 'audio/wav',
            'audio/x-m4a' => 'audio/mp4',
            'application/ogg' => 'audio/ogg',
            default => $detected,
        };
    }

    private function validateVideoContainer(string $path, string $mime, int $size): void
    {
        // The H.264/VP8/VP9/AV1 description can live anywhere in the
        // container, including an MP4 moov atom beyond both fixed probes.
        // FFprobe, bundled with FFmpeg for poster extraction, parses the
        // actual video stream instead of guessing from arbitrary byte slices.
        $stream = $this->probeVideoStream($path);
        $codec = $stream['codec'] ?? null;

        if ($mime === 'video/mp4') {
            [$head] = $this->mediaProbe($path, min($size, 12));
            if (! $this->isMp4Container($head) || $codec !== 'h264') {
                throw $this->validationFailure('MP4 uploads must contain a browser-native H.264 video stream.');
            }

            return;
        }

        if ($mime === 'video/webm') {
            [$head] = $this->mediaProbe($path, min($size, 4));
            if (! str_starts_with($head, "\x1A\x45\xDF\xA3")
                || ! in_array($codec, ['vp8', 'vp9', 'av1'], true)) {
                throw $this->validationFailure('WebM uploads must contain a browser-native VP8, VP9 or AV1 video stream.');
            }

            return;
        }

        throw $this->validationFailure('The uploaded video type is not allowed.');
    }

    /** @return array{codec:string,width:int,height:int}|null */
    private function probeVideoStream(string $path): ?array
    {
        $process = new Process([
            'ffprobe', '-v', 'error',
            '-select_streams', 'v:0',
            '-show_entries', 'stream=codec_name,width,height',
            '-of', 'json',
            $path,
        ]);
        $process->setTimeout(30);

        try {
            if ($process->run() !== 0) {
                return null;
            }
        } catch (\Symfony\Component\Process\Exception\ProcessTimedOutException) {
            return null;
        }

        $decoded = json_decode($process->getOutput(), true);
        $stream = is_array($decoded) ? ($decoded['streams'][0] ?? null) : null;
        if (! is_array($stream)) {
            return null;
        }

        $codec = $stream['codec_name'] ?? null;
        $width = $stream['width'] ?? null;
        $height = $stream['height'] ?? null;

        return is_string($codec)
            && is_int($width) && $width > 0
            && is_int($height) && $height > 0
            ? ['codec' => $codec, 'width' => $width, 'height' => $height]
            : null;
    }

    private function validateAudioContainer(string $path, string $mime, int $size): void
    {
        [$head, $tail] = $this->mediaProbe($path, $size);
        $probe = $head.$tail;

        $valid = match ($mime) {
            'audio/mpeg' => $this->containsMpegAudioFrameSequence($probe),
            'audio/mp4' => $this->isMp4Container($head)
                && $this->hasMp4AudioTrack($probe)
                && ! $this->hasVideoTrack($probe),
            'audio/ogg' => $this->isOggAudio($head, $probe),
            'audio/wav' => $this->isWaveContainer($head)
                && str_contains($probe, 'fmt ')
                && str_contains($probe, 'data'),
            default => false,
        };

        if (! $valid) {
            throw $this->validationFailure('The uploaded audio is not a supported browser-native audio file.');
        }
    }

    /** @return array{string,string} */
    private function mediaProbe(string $path, int $size): array
    {
        $stream = @fopen($path, 'rb');
        if (! is_resource($stream)) {
            throw $this->validationFailure('The uploaded media could not be inspected.');
        }

        try {
            $readLength = min(self::MEDIA_PROBE_BYTES, $size);
            $head = fread($stream, $readLength);
            if (! is_string($head)) {
                throw $this->validationFailure('The uploaded media could not be inspected.');
            }

            $tail = '';
            if ($size > self::MEDIA_PROBE_BYTES) {
                if (fseek($stream, max(0, $size - self::MEDIA_PROBE_BYTES)) !== 0) {
                    throw $this->validationFailure('The uploaded media could not be inspected.');
                }
                $tailBytes = fread($stream, self::MEDIA_PROBE_BYTES);
                if (! is_string($tailBytes)) {
                    throw $this->validationFailure('The uploaded media could not be inspected.');
                }
                $tail = $tailBytes;
            }

            return [$head, $tail];
        } finally {
            fclose($stream);
        }
    }

    private function isMp4Container(string $head): bool
    {
        return strlen($head) >= 12 && substr($head, 4, 4) === 'ftyp';
    }

    private function hasMp4AudioTrack(string $probe): bool
    {
        return str_contains($probe, 'mp4a');
    }

    private function hasVideoTrack(string $probe): bool
    {
        foreach (['avc1', 'avc3', 'hvc1', 'hev1', 'vp09', 'av01', 'theora'] as $marker) {
            if (str_contains($probe, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function isWaveContainer(string $head): bool
    {
        return strlen($head) >= 12
            && str_starts_with($head, 'RIFF')
            && substr($head, 8, 4) === 'WAVE';
    }

    private function isOggAudio(string $head, string $probe): bool
    {
        return str_starts_with($head, 'OggS')
            && ! str_contains($probe, 'theora')
            && (str_contains($probe, 'vorbis') || str_contains($probe, 'OpusHead'));
    }

    private function hasLeadingMpegAudioSignature(string $head): bool
    {
        if (str_starts_with($head, 'ID3')) {
            return $this->containsMpegAudioFrameSequence($head);
        }

        $firstFrameLength = $this->mpegAudioFrameLength($head, 0);
        if ($firstFrameLength === null) {
            return false;
        }

        return $this->mpegAudioFrameLength($head, $firstFrameLength) !== null;
    }

    private function containsMpegAudioFrameSequence(string $probe): bool
    {
        $length = min(strlen($probe), self::MEDIA_PROBE_BYTES);
        for ($index = 0; $index < $length - 7; $index++) {
            $frameLength = $this->mpegAudioFrameLength($probe, $index);
            if ($frameLength === null) {
                continue;
            }

            $nextFrame = $index + $frameLength;
            if ($nextFrame + 4 <= $length && $this->mpegAudioFrameLength($probe, $nextFrame) !== null) {
                return true;
            }
        }

        return false;
    }

    private function mpegAudioFrameLength(string $probe, int $offset): ?int
    {
        if ($offset < 0 || $offset + 4 > strlen($probe)) {
            return null;
        }

        $first = ord($probe[$offset]);
        $second = ord($probe[$offset + 1]);
        $third = ord($probe[$offset + 2]);

        if ($first !== 0xFF || ($second & 0xE0) !== 0xE0) {
            return null;
        }

        $versionBits = ($second >> 3) & 0x03;
        $layerBits = ($second >> 1) & 0x03;
        $bitrateIndex = ($third >> 4) & 0x0F;
        $sampleRateIndex = ($third >> 2) & 0x03;
        $padding = ($third >> 1) & 0x01;

        if ($versionBits === 0x01
            || $layerBits === 0x00
            || in_array($bitrateIndex, [0x00, 0x0F], true)
            || $sampleRateIndex === 0x03) {
            return null;
        }

        $mpeg1 = $versionBits === 0x03;
        $layer = match ($layerBits) {
            0x03 => 1,
            0x02 => 2,
            0x01 => 3,
        };
        $sampleRates = match ($versionBits) {
            0x03 => [44_100, 48_000, 32_000],
            0x02 => [22_050, 24_000, 16_000],
            0x00 => [11_025, 12_000, 8_000],
        };
        $bitRates = $mpeg1
            ? match ($layer) {
                1 => [0, 32, 64, 96, 128, 160, 192, 224, 256, 288, 320, 352, 384, 416, 448],
                2 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320, 384],
                3 => [0, 32, 40, 48, 56, 64, 80, 96, 112, 128, 160, 192, 224, 256, 320],
            }
        : match ($layer) {
            1 => [0, 32, 48, 56, 64, 80, 96, 112, 128, 144, 160, 176, 192, 224, 256],
            2, 3 => [0, 8, 16, 24, 32, 40, 48, 56, 64, 80, 96, 112, 128, 144, 160],
        };
        $sampleRate = $sampleRates[$sampleRateIndex];
        $bitRate = $bitRates[$bitrateIndex] * 1000;

        if ($layer === 1) {
            return (intdiv(12 * $bitRate, $sampleRate) + $padding) * 4;
        }

        $coefficient = $layer === 3 && ! $mpeg1 ? 72 : 144;

        return intdiv($coefficient * $bitRate, $sampleRate) + $padding;
    }

    private function basename(string $filename): string
    {
        return basename(str_replace('\\', '/', $filename));
    }

    private function validationFailure(string $message, ?Throwable $previous = null): ValidationException
    {
        return ValidationException::withMessages(['media' => $message]);
    }
}
