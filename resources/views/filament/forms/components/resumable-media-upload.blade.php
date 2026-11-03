@php
    $path = $getStatePath();
@endphp
<div
    wire:ignore
    x-data="{
        phase: 'idle',
        progress: 0,
        completed: 0,
        result: '',
        async uploadFile(input) {
            await window.adminMediaUploadField(this, input, $wire, @js($path), @js($imagesOnly));
        },
    }"
    x-bind:aria-busy="(phase === 'uploading' || phase === 'processing').toString()"
    class="media-workspace__resumable-field"
>
    <input
        type="file"
        accept="{{ $mediaAccept }}"
        aria-label="Upload new media file"
        x-on:change="uploadFile($el)"
        x-bind:disabled="phase === 'uploading' || phase === 'processing'"
        @disabled($isDisabled())
    />
    <p
        aria-live="polite"
        x-text="phase === 'uploading'
            ? 'Uploading ' + progress + '%'
            : phase === 'processing'
                ? 'Transfer complete. Checking and processing media…'
                : result"
    ></p>
    <div
        class="media-workspace__upload-progress"
        x-show="phase === 'uploading' || phase === 'processing'"
        x-bind:class="{ 'is-processing': phase === 'processing' }"
        x-cloak
        role="progressbar"
        aria-label="Media upload status"
        aria-valuemin="0"
        aria-valuemax="100"
        x-bind:aria-valuenow="phase === 'uploading' ? progress : null"
    >
        <span class="media-workspace__upload-progress-track" aria-hidden="true">
            <span class="media-workspace__upload-progress-fill" x-bind:style="'width: ' + progress + '%'"></span>
        </span>
    </div>
    <p>Interrupted uploads can be resumed by selecting the same file.</p>
</div>
