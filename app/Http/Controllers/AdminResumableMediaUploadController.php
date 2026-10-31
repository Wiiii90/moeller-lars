<?php

namespace App\Http\Controllers;

use App\Domain\Admin\AdminNotifier;
use App\Domain\Media\ResumableMediaUploadService;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class AdminResumableMediaUploadController extends Controller
{
    public function start(Request $request, ResumableMediaUploadService $uploads): JsonResponse
    {
        $userId = $this->adminId();
        $data = $request->validate([
            'filename' => ['required', 'string', 'max:240'],
            'size' => ['required', 'integer', 'min:1'],
            'resume_id' => ['nullable', 'uuid'],
        ]);

        return response()->json($uploads->begin(
            $userId,
            $data['filename'],
            (int) $data['size'],
            $data['resume_id'] ?? null,
        ));
    }

    public function chunk(Request $request, ResumableMediaUploadService $uploads, string $id): JsonResponse
    {
        $userId = $this->adminId();
        $offset = $request->header('X-Upload-Offset');
        if (! is_string($offset) || ! ctype_digit($offset) || strlen($offset) > 16) {
            throw ValidationException::withMessages(['media' => 'Invalid upload offset.']);
        }
        $length = $request->header('Content-Length');
        if ($length !== null && (! is_string($length) || ! ctype_digit($length)
            || (int) $length < 1 || (int) $length > ResumableMediaUploadService::CHUNK_BYTES)) {
            throw ValidationException::withMessages(['media' => 'Invalid media chunk size.']);
        }

        $stream = $request->getContent(true);
        try {
            return response()->json($uploads->append($userId, $id, (int) $offset, $stream));
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    public function finish(ResumableMediaUploadService $uploads, string $id): JsonResponse
    {
        return response()->json($uploads->finish($this->adminId(), $id));
    }

    /** Errors inside native Filament upload fields also use the canonical
     * durable Dashboard/header notification transport (no second UI store).
     */
    public function reportFailure(Request $request, AdminNotifier $notifier): JsonResponse
    {
        $this->adminId();
        $data = $request->validate(['message' => ['required', 'string', 'max:400']]);
        $notifier->notification(
            title: 'Media upload failed',
            body: $data['message'],
            status: 'danger',
        );
        return response()->json(['reported' => true]);
    }

    private function adminId(): int
    {
        $user = Auth::guard('web')->user();
        abort_unless($user instanceof User && (bool) $user->getAttribute('is_admin'), 403);

        return (int) $user->getKey();
    }
}
