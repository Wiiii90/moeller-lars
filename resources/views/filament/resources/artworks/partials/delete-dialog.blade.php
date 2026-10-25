<div class="media-delete-dialog">
    @if ($asset === null)
        <p>This Artwork has no available primary media file. Only the Artwork slot will be deleted.</p>
    @else
        <p>The original file <strong>{{ $asset->original_filename }}</strong> is stored separately. Deleting the Artwork alone leaves it reusable in Storage.</p>
        @if ($references !== [])
            <p>If you also delete the file, the following {{ count($references) }} {{ \Illuminate\Support\Str::plural('reference', count($references)) }} will be removed. Other published Artworks using this file will become drafts and lose their Gallery assignment.</p>
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
    @endif
</div>
