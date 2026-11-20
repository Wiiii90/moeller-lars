<div class="media-delete-dialog">
    @if (isset($selectedCount))
        <p>{{ $selectedCount }} {{ \Illuminate\Support\Str::plural('Artwork', $selectedCount) }} selected. Deleting the Artwork alone preserves its original file in Storage.</p>
        <p>Optionally delete {{ $fileCount }} associated original {{ \Illuminate\Support\Str::plural('file', $fileCount) }} from Storage. Files still used elsewhere will also lose their references.</p>
    @elseif ($asset === null)
        <p>This Artwork has no available primary media file. Only the Artwork will be deleted.</p>
    @else
        <p>The original file <strong>{{ $asset->original_filename }}</strong> is stored separately. Deleting the Artwork alone leaves it reusable in Storage.</p>
    @endif
    @if ($references !== [])
        <p>Deleting the original {{ isset($selectedCount) ? 'files' : 'file' }} will remove the following {{ count($references) }} {{ \Illuminate\Support\Str::plural('reference', count($references)) }}. Other published Artworks using {{ isset($selectedCount) ? 'these files' : 'this file' }} may become unpublished and lose their Gallery assignment.</p>
        <div class="media-delete-dialog__references" aria-label="Media references affected by optional file deletion">
            @foreach ($references as $reference)
                <div>
                    <strong>{{ $reference['type'] }}</strong>
                    <span>{{ $reference['label'] }}</span>
                </div>
            @endforeach
        </div>
    @else
        <p>There are no other recorded content references.</p>
    @endif
    <p>Deletion changes Working first. Previously committed public content is updated by the next Site Commit.</p>
</div>
