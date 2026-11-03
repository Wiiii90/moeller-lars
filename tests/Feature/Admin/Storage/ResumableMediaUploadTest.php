<?php

use App\Models\MediaAsset;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;

beforeEach(function (): void {
    // The upload service stages files outside the configured Storage disk.
    // Redirect Laravel's entire storage root to a fresh, owned temporary
    // directory before it can inspect or prune any live staging artifacts.
    app()->instance('resumable-media-test.original-storage-root', storage_path());
    $root = sys_get_temp_dir().'/moeller-resumable-test-'.Str::uuid();
    app()->useStoragePath($root);
    File::ensureDirectoryExists(storage_path('app/private/incoming-media'));
    Storage::fake('resumable-media-suite');
    config([
        'media.disk' => 'resumable-media-suite',
        'media.quota_bytes' => 10_000_000_000,
    ]);
    $this->actingAs(User::factory()->admin()->create(), 'web');
});

afterEach(function (): void {
    $root = storage_path();
    try {
        if (str_starts_with($root, sys_get_temp_dir().'/moeller-resumable-test-')) {
            File::deleteDirectory($root);
        }
    } finally {
        app()->useStoragePath(app('resumable-media-test.original-storage-root'));
        app()->forgetInstance('resumable-media-test.original-storage-root');
    }
});

/** One real image payload also exercises the canonical thumbnail and dedup owner. */
function resumableTestImage(): string
{
    $path = UploadedFile::fake()->image('uploaded.jpg', 80, 64)->getRealPath();
    $bytes = file_get_contents($path);
    if (! is_string($bytes) || $bytes === '') {
        throw new RuntimeException('Could not prepare isolated upload fixture.');
    }

    return $bytes;
}

/** @return array<string,mixed> */
function resumableTestStart($test, string $filename, int $size, ?string $resumeId = null): array
{
    $payload = ['filename' => $filename, 'size' => $size];
    if ($resumeId !== null) {
        $payload['resume_id'] = $resumeId;
    }

    return $test->postJson(route('admin.media-upload.start'), $payload)->assertOk()->json();
}

function resumableTestChunk($test, string $id, int $offset, string $bytes): TestResponse
{
    return $test->call('POST', route('admin.media-upload.chunk', ['id' => $id]),
        [], [], [], [
            'HTTP_X_UPLOAD_OFFSET' => (string) $offset,
            'HTTP_ACCEPT' => 'application/json',
            'CONTENT_TYPE' => 'application/octet-stream',
        ], $bytes);
}

it('denies non-admin access to private staging before creating upload records', function (): void {
    $this->actingAs(User::factory()->create(), 'web');
    $this->postJson(route('admin.media-upload.start'), [
        'filename' => 'private.jpg',
        'size' => 100,
    ])->assertForbidden();

    expect(File::files(storage_path('app/private/incoming-media')))->toBeEmpty()
        ->and(MediaAsset::query()->count())->toBe(0);
});

it('transfers, finalizes and deduplicates a real image with durable idempotent receipts', function (): void {
    $bytes = resumableTestImage();
    $start = resumableTestStart($this, 'artwork.jpg', strlen($bytes));
    expect($start['offset'])->toBe(0)
        ->and($start['complete'])->toBeFalse()
        ->and($start['asset_id'])->toBeNull();

    $chunk = resumableTestChunk($this, $start['id'], 0, $bytes)->assertOk()->json();
    expect($chunk['offset'])->toBe(strlen($bytes))
        ->and($chunk['chunk_hashes'])->toBe([hash('sha256', $bytes)]);

    // Recovery after a lost finish response must report the persisted asset.
    $firstFinish = $this->postJson(route('admin.media-upload.finish', ['id' => $start['id']]))
        ->assertOk()->json();
    $resume = resumableTestStart($this, 'artwork.jpg', strlen($bytes), $start['id']);
    $finishAgain = $this->postJson(route('admin.media-upload.finish', ['id' => $start['id']]))
        ->assertOk()->json();

    $asset = MediaAsset::query()->findOrFail($firstFinish['asset_id']);
    $preview = $asset->variants()->sole();
    $disk = Storage::disk(config('media.disk'));
    expect($firstFinish['duplicate'])->toBeFalse()
        ->and($resume['complete'])->toBeTrue()
        ->and($resume['asset_id'])->toBe($asset->id)
        ->and($finishAgain['asset_id'])->toBe($asset->id)
        ->and(MediaAsset::query()->where('sha256', hash('sha256', $bytes))->count())->toBe(1)
        ->and($disk->get($asset->storage_key))->toBe($bytes)
        ->and(hash_file('sha256', $disk->path($preview->storage_key)))->toBe($preview->sha256);

    $other = resumableTestStart($this, 'renamed-image.jpg', strlen($bytes));
    resumableTestChunk($this, $other['id'], 0, $bytes)->assertOk();
    $duplicate = $this->postJson(route('admin.media-upload.finish', ['id' => $other['id']]))
        ->assertOk()->json();
    expect($duplicate['duplicate'])->toBeTrue()
        ->and($duplicate['asset_id'])->toBe($asset->id)
        ->and(MediaAsset::query()->count())->toBe(1);
});

it('rolls back incomplete chunks and resumes strictly from the acknowledged offset', function (): void {
    $bytes = resumableTestImage();
    $start = resumableTestStart($this, 'partial.jpg', strlen($bytes));
    $partial = substr($bytes, 0, max(1, intdiv(strlen($bytes), 2)));

    resumableTestChunk($this, $start['id'], 0, $partial)
        ->assertUnprocessable()->assertJsonValidationErrors('media');
    $status = resumableTestStart($this, 'partial.jpg', strlen($bytes), $start['id']);
    expect($status['offset'])->toBe(0)
        ->and($status['chunk_hashes'])->toBe([])
        ->and(MediaAsset::query()->count())->toBe(0);

    resumableTestChunk($this, $start['id'], 1, $bytes)
        ->assertUnprocessable()->assertJsonValidationErrors('media');
    $this->postJson(route('admin.media-upload.finish', ['id' => $start['id']]))
        ->assertUnprocessable()->assertJsonValidationErrors('media');

    resumableTestChunk($this, $start['id'], 0, $bytes)->assertOk();
    $finish = $this->postJson(route('admin.media-upload.finish', ['id' => $start['id']]))
        ->assertOk()->json();
    expect($finish['complete'])->toBeTrue()
        ->and(MediaAsset::query()->count())->toBe(1);
});

it('keeps unfinished staging scoped to its owner and cancellation never deletes canonical media', function (): void {
    $bytes = resumableTestImage();
    $owner = auth('web')->user();
    $start = resumableTestStart($this, 'private.jpg', strlen($bytes));
    $otherAdmin = User::factory()->admin()->create();

    $this->actingAs($otherAdmin, 'web');
    $this->postJson(route('admin.media-upload.start'), [
        'filename' => 'private.jpg',
        'size' => strlen($bytes),
        'resume_id' => $start['id'],
    ])->assertUnprocessable()->assertJsonValidationErrors('media');
    resumableTestChunk($this, $start['id'], 0, $bytes)
        ->assertUnprocessable()->assertJsonValidationErrors('media');
    $this->postJson(route('admin.media-upload.cancel', ['id' => $start['id']]))
        ->assertUnprocessable()->assertJsonValidationErrors('media');

    $this->actingAs($owner, 'web');
    resumableTestChunk($this, $start['id'], 0, $bytes)->assertOk();
    $this->postJson(route('admin.media-upload.cancel', ['id' => $start['id']]))
        ->assertOk()->assertJson(['cancelled' => true]);
    expect(MediaAsset::query()->count())->toBe(0)
        ->and(File::exists(storage_path('app/private/incoming-media/'.$start['id'].'.part')))->toBeFalse();

    $new = resumableTestStart($this, 'private.jpg', strlen($bytes));
    resumableTestChunk($this, $new['id'], 0, $bytes)->assertOk();
    $saved = $this->postJson(route('admin.media-upload.finish', ['id' => $new['id']]))
        ->assertOk()->json();
    $this->postJson(route('admin.media-upload.cancel', ['id' => $new['id']]))
        ->assertUnprocessable()->assertJsonValidationErrors('media');
    expect(MediaAsset::query()->findOrFail($saved['asset_id'])->state)->toBe('available');
});

it('fails closed when a byte-identical registered original has a missing required preview', function (): void {
    $bytes = resumableTestImage();
    $start = resumableTestStart($this, 'registered.jpg', strlen($bytes));
    resumableTestChunk($this, $start['id'], 0, $bytes)->assertOk();
    $saved = $this->postJson(route('admin.media-upload.finish', ['id' => $start['id']]))
        ->assertOk()->json();

    $asset = MediaAsset::query()->findOrFail($saved['asset_id']);
    $preview = $asset->variants()->sole();
    Storage::disk(config('media.disk'))->delete($preview->storage_key);

    $second = resumableTestStart($this, 'registered.jpg', strlen($bytes));
    resumableTestChunk($this, $second['id'], 0, $bytes)->assertOk();
    $this->postJson(route('admin.media-upload.finish', ['id' => $second['id']]))
        ->assertUnprocessable()->assertJsonValidationErrors('media');

    expect(MediaAsset::query()->count())->toBe(1)
        ->and($asset->fresh()->state)->toBe('available')
        ->and(Storage::disk(config('media.disk'))->exists($asset->storage_key))->toBeTrue();
});
