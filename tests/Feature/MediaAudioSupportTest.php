<?php

use App\Domain\Media\MediaIngestService;
use App\Domain\Media\MediaTypePolicy;
use App\Models\MediaAsset;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function audioFixture(string $kind): UploadedFile
{
    $bytes = match ($kind) {
        'mp3' => "ID3\x04\x00\x00\x00\x00\x00\x00".str_repeat("\xFF\xFB\x90\x64".str_repeat("\0", 413), 3),
        'm4a' => pack('N', 24).'ftypM4A '."\x00\x00\x00\x00".'M4A isom'.pack('N', 24).'moov'.str_repeat("\0", 8).'mp4a',
        'ogg' => 'OggS'."\x00\x02".str_repeat("\0", 20)."\x01vorbis".str_repeat("\0", 64),
        'wav' => 'RIFF'.pack('V', 36).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 44100, 88200, 2, 16).'data'.pack('V', 0),
    };

    return UploadedFile::fake()->createWithContent('fixture.'.$kind, $bytes);
}

it('defines the canonical audio policy and browser-native labels', function (): void {
    expect(MediaTypePolicy::AUDIO_MIME_TYPES)->toBe([
        'audio/mpeg',
        'audio/mp4',
        'audio/ogg',
        'audio/wav',
    ])->and(MediaTypePolicy::extensionFor('audio/mpeg'))->toBe('mp3')
        ->and(MediaTypePolicy::extensionFor('audio/mp4'))->toBe('m4a')
        ->and(MediaTypePolicy::extensionFor('audio/ogg'))->toBe('ogg')
        ->and(MediaTypePolicy::extensionFor('audio/wav'))->toBe('wav');
});

it('ingests supported audio from content-derived MIME and stores no generated thumbnail', function (string $kind, string $mime): void {
    Storage::fake(config('media.disk'));

    $asset = app(MediaIngestService::class)->ingest(audioFixture($kind));

    expect($asset->mime_type)->toBe($mime)
        ->and($asset->state)->toBe('available')
        ->and($asset->variants()->count())->toBe(0)
        ->and($asset->copyright_notice_mode)->toBe(MediaAsset::COPYRIGHT_INHERIT)
        ->and($asset->copyright_notice)->toBeNull()
        ->and(Storage::disk(config('media.disk'))->exists($asset->storage_key))->toBeTrue();
})->with([
    ['mp3', 'audio/mpeg'],
    ['m4a', 'audio/mp4'],
    ['ogg', 'audio/ogg'],
    ['wav', 'audio/wav'],
]);

it('rejects an MP4 container with a video track instead of classifying it as M4A audio', function (): void {
    Storage::fake(config('media.disk'));
    $bytes = pack('N', 24).'ftypM4A '."\x00\x00\x00\x00".'M4A isom'.pack('N', 32).'moov'.str_repeat("\0", 8).'mp4a'.'hvc1';
    $upload = UploadedFile::fake()->createWithContent('not-audio.m4a', $bytes);

    expect(fn () => app(MediaIngestService::class)->ingest($upload))
        ->toThrow(ValidationException::class);
});
