<div class="media-delete-dialog">
    <p>{{ $selectedCount }} selected {{ \Illuminate\Support\Str::plural('file', $selectedCount) }}.</p>

    @if ($referencedFileCount > 0)
        <p>
            {{ $referencedFileCount }} {{ $referencedFileCount === 1 ? 'is' : 'are' }} currently referenced.
            Deleting {{ $selectedCount === 1 ? 'it' : 'them' }} will also remove {{ $referenceCount === 1 ? 'its media reference' : 'their media references' }}.
        </p>

        <div class="media-delete-dialog__references" aria-label="Selected media references that will be removed">
            @foreach ($references as $reference)
                <div>
                    <strong>{{ $reference['filename'] }}</strong>
                    <span>{{ $reference['type'] }} — {{ $reference['label'] }}</span>
                </div>
            @endforeach
        </div>
    @else
        <p>The selected files and generated variants will be removed.</p>
    @endif
</div>
