<?php

namespace App\Domain\Admin;

use InvalidArgumentException;

/**
 * One content owner for all native Admin media upload results.
 * AdminNotifier retains sole authority over durable and header delivery.
 */
final class AdminMediaUploadFeedback
{
    public function __construct(private readonly AdminNotifier $notifier) {}

    /**
     * Storage: ready means newly saved originals; reused is disjoint.
     * Gallery: ready means media selected for Artwork, including reused.
     *
     * @param list<string> $errors Each entry includes the filename and the actual reason.
     */
    public function batch(int $ready, int $reused, int $failed, array $errors, string $destination): string
    {
        if (! in_array($destination, ['storage', 'gallery'], true)) {
            throw new InvalidArgumentException('Unknown media upload destination.');
        }

        $ready = max(0, $ready);
        $reused = max(0, $reused);
        $failed = max(0, $failed);
        $accepted = $destination === 'gallery' ? $ready : $ready + $reused;

        $parts = [];
        if ($ready > 0) {
            if ($destination === 'gallery') {
                $parts[] = $ready.' '.($ready === 1 ? 'media file is' : 'media files are').' ready for Artwork creation';
            } else {
                $parts[] = $ready.' '.($ready === 1 ? 'file saved' : 'files saved').' to Storage';
            }
        }
        if ($reused > 0) {
            $parts[] = $reused.' '.($reused === 1 ? 'existing file' : 'existing files').' reused from Storage';
        }
        if ($failed > 0 && ($accepted > 0 || $failed > 1)) {
            $parts[] = $failed.' '.($failed === 1 ? 'file failed' : 'files failed');
        }

        $summary = implode('; ', $parts);
        $lines = [];
        foreach ($errors as $error) {
            if (! is_string($error) || trim($error) === '') {
                continue;
            }

            $lines[] = mb_substr(trim($error), 0, 350);
            if (count($lines) >= 3) {
                break;
            }
        }

        $unreported = max(0, $failed - count($lines));
        if ($unreported > 0 && $lines !== []) {
            $lines[] = $unreported.' '.($unreported === 1 ? 'additional failed file' : 'additional failed files');
        }

        if ($failed > 0 && $accepted === 0) {
            $body = $lines !== []
                ? implode("\n", $lines)
                : ($failed === 1 ? 'The selected file was not saved.' : $failed.' files were not saved.')
                    .' Check the file and try again or contact the site administrator.';
        } else {
            $body = $summary !== '' ? $summary.'.' : 'No media files selected.';
            if ($lines !== []) {
                $body .= "\n".implode("\n", $lines);
            }
        }

        if ($ready === 0 && $reused === 0 && $failed === 0) {
            return $body;
        }

        $this->notifier->notification(
            title: $failed > 0
                ? ($destination === 'gallery'
                    ? ($accepted > 0 ? 'Artwork media ready with issues' : 'Artwork media preparation failed')
                    : ($accepted > 0 ? 'Upload completed with issues' : 'Upload failed'))
                : ($ready > 0
                    ? ($destination === 'gallery' ? 'Media ready for Artwork' : 'Media uploaded')
                    : 'Already in Storage'),
            body: mb_substr($body, 0, 500),
            status: $failed > 0 ? ($accepted > 0 ? 'warning' : 'danger') : ($ready > 0 ? 'success' : 'info'),
        );

        return mb_substr(str_replace("\n", ' · ', $body), 0, 500);
    }

    /** Report a failed single-file field or transfer through the same notifier. */
    public function failure(string $detail): void
    {
        $detail = trim($detail);

        $this->notifier->notification(
            title: 'Upload failed',
            body: mb_substr($detail !== '' ? $detail : 'The file was not saved. Try again or contact the site administrator.', 0, 500),
            status: 'danger',
        );
    }
}
