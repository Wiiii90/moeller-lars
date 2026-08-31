<?php

namespace App\Http\Controllers;

use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use ZipArchive;

final class AdminMediaController extends Controller
{
    public function original(MediaAsset $mediaAsset): Response
    {
        $this->authorizeAdmin();
        $file = $this->originalFile($mediaAsset);

        return $this->sendfile(
            $file['path'],
            $file['mime_type'],
            $file['byte_size'],
            $file['sha256'],
        );
    }

    public function download(MediaAsset $mediaAsset): Response
    {
        $this->authorizeAdmin();
        $file = $this->originalFile($mediaAsset);

        return $this->sendfile(
            $file['path'],
            $file['mime_type'],
            $file['byte_size'],
            $file['sha256'],
            HeaderUtils::DISPOSITION_ATTACHMENT,
            $file['filename'],
        );
    }

    public function downloadSelected(Request $request): Response
    {
        $this->authorizeAdmin();

        $rawIds = $request->query('ids', []);
        if (! is_array($rawIds)) {
            $rawIds = explode(',', (string) $rawIds);
        }

        $ids = collect($rawIds)
            ->map(static function (mixed $value): ?int {
                if (! is_scalar($value)) {
                    return null;
                }

                $id = filter_var($value, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

                return $id === false ? null : (int) $id;
            })
            ->filter(static fn (?int $id): bool => $id !== null)
            ->unique()
            ->values()
            ->all();
        sort($ids, SORT_NUMERIC);

        abort_if($ids === [], 422, 'Select at least one available file to download.');

        $records = MediaAsset::query()
            ->whereIn('id', $ids)
            ->get()
            ->keyBy(static fn (MediaAsset $asset): int => (int) $asset->getKey());

        abort_unless(
            $records->count() === count($ids),
            409,
            'One or more selected files are no longer available for download.',
        );

        $files = [];
        foreach ($ids as $id) {
            $asset = $records->get($id);
            abort_unless($asset instanceof MediaAsset, 409, 'One or more selected files are no longer available for download.');
            $files[] = $this->originalFile(
                $asset,
                409,
                'One or more selected files are no longer available for download.',
            );
        }

        if (count($files) === 1) {
            $file = $files[0];

            return $this->sendfile(
                $file['path'],
                $file['mime_type'],
                $file['byte_size'],
                $file['sha256'],
                HeaderUtils::DISPOSITION_ATTACHMENT,
                $file['filename'],
            );
        }

        return $this->archiveResponse($files);
    }

    public function variant(MediaVariant $mediaVariant): Response
    {
        $this->authorizeAdmin();
        $mediaVariant->loadMissing('mediaAsset');

        /** @var MediaAsset|null $asset */
        $asset = $mediaVariant->getRelationValue('mediaAsset');
        abort_unless(
            $asset !== null
            && $asset->getAttribute('state') === 'available'
            && $mediaVariant->getAttribute('state') === 'available',
            404,
        );

        $disk = Storage::disk(config('media.disk'));
        $storageKey = (string) $mediaVariant->getAttribute('storage_key');
        abort_unless($disk->exists($storageKey), 404);

        $path = $disk->path($storageKey);
        abort_unless(is_file($path) && is_readable($path), 404);

        return $this->sendfile(
            $path,
            (string) $mediaVariant->getAttribute('mime_type'),
            (int) $mediaVariant->getAttribute('byte_size'),
            (string) $mediaVariant->getAttribute('sha256'),
        );
    }

    private function authorizeAdmin(): void
    {
        $user = Auth::guard('web')->user();

        abort_unless($user instanceof User && (bool) $user->getAttribute('is_admin'), 403);
    }

    /**
     * @return array{path:string,filename:string,mime_type:string,byte_size:int,sha256:string}
     */
    private function originalFile(MediaAsset $mediaAsset, int $failureStatus = 404, string $failureMessage = ''): array
    {
        abort_unless($mediaAsset->getAttribute('state') === 'available', $failureStatus, $failureMessage);

        $disk = Storage::disk(config('media.disk'));
        $storageKey = (string) $mediaAsset->getAttribute('storage_key');
        abort_unless($storageKey !== '' && $disk->exists($storageKey), $failureStatus, $failureMessage);

        $path = $disk->path($storageKey);
        abort_unless(is_file($path) && is_readable($path), $failureStatus, $failureMessage);

        return [
            'path' => $path,
            'filename' => $this->safeFilename($mediaAsset),
            'mime_type' => (string) $mediaAsset->getAttribute('mime_type'),
            'byte_size' => (int) $mediaAsset->getAttribute('byte_size'),
            'sha256' => (string) $mediaAsset->getAttribute('sha256'),
        ];
    }

    private function sendfile(
        string $path,
        string $mimeType,
        int $byteSize,
        string $sha256,
        string $disposition = HeaderUtils::DISPOSITION_INLINE,
        ?string $filename = null,
    ): Response {
        $contentDisposition = $filename === null
            ? $disposition
            : $this->contentDisposition($disposition, $filename);

        return response('', 200, [
            'Content-Type' => $mimeType,
            'Content-Length' => (string) max(0, $byteSize),
            'ETag' => '"'.$sha256.'"',
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => $contentDisposition,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, max-age=3600',
            'X-Sendfile' => $path,
        ]);
    }

    /**
     * @param list<array{path:string,filename:string,mime_type:string,byte_size:int,sha256:string}> $files
     */
    private function archiveResponse(array $files): Response
    {
        $temporaryPath = tempnam(sys_get_temp_dir(), 'media-download-');
        abort_unless(is_string($temporaryPath) && $temporaryPath !== '', 500, 'The download archive could not be created.');

        $zip = new ZipArchive();
        $opened = $zip->open($temporaryPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        if ($opened !== true) {
            @unlink($temporaryPath);
            abort(500, 'The download archive could not be created.');
        }

        try {
            $usedNames = [];
            foreach ($files as $file) {
                $archiveName = $this->uniqueArchiveFilename($file['filename'], $usedNames);
                if (! $zip->addFile($file['path'], $archiveName)) {
                    throw new RuntimeException('Unable to add an original media file to the download archive.');
                }
            }

            if (! $zip->close()) {
                throw new RuntimeException('Unable to finalize the download archive.');
            }
        } catch (Throwable $exception) {
            $zip->close();
            @unlink($temporaryPath);
            report($exception);
            abort(500, 'The download archive could not be created.');
        }

        return response()
            ->download(
                $temporaryPath,
                'media-files-'.now()->format('Ymd-His').'.zip',
                ['Content-Type' => 'application/zip'],
            )
            ->deleteFileAfterSend(true);
    }

    /** @param array<string, true> $usedNames */
    private function uniqueArchiveFilename(string $filename, array &$usedNames): string
    {
        $key = mb_strtolower($filename);
        if (! isset($usedNames[$key])) {
            $usedNames[$key] = true;

            return $filename;
        }

        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $stem = pathinfo($filename, PATHINFO_FILENAME);
        $suffix = $extension === '' ? '' : '.'.$extension;
        $counter = 2;

        do {
            $candidate = $stem.' ('.$counter.')'.$suffix;
            $counter++;
        } while (isset($usedNames[mb_strtolower($candidate)]));

        $usedNames[mb_strtolower($candidate)] = true;

        return $candidate;
    }

    private function safeFilename(MediaAsset $mediaAsset): string
    {
        $filename = trim((string) $mediaAsset->getAttribute('original_filename'));
        $filename = basename(str_replace('\\', '/', $filename));
        $filename = preg_replace('/[\x00-\x1F\x7F]/u', '_', $filename) ?? '';

        if ($filename === '' || $filename === '.' || $filename === '..') {
            return 'media-'.$mediaAsset->getKey();
        }

        return $filename;
    }

    private function contentDisposition(string $disposition, string $filename): string
    {
        $fallback = Str::ascii($filename);
        $fallback = preg_replace('/[^A-Za-z0-9._ -]/', '_', $fallback) ?? '';
        $fallback = str_replace('%', '_', $fallback);

        return HeaderUtils::makeDisposition(
            $disposition,
            $filename,
            $fallback !== '' ? $fallback : 'download',
        );
    }
}
