<div class="media-delete-dialog">
    @if ($references === [])
        <p>The file and generated variants will be removed.</p>
    @else
        <p>
            This file is currently used in {{ count($references) }} {{ \Illuminate\Support\Str::plural('place', count($references)) }}.
            Deleting it will also remove these media references.
        </p>

        <div class="media-delete-dialog__references" aria-label="Media references that will be removed">
            @foreach ($references as $reference)
                <div>
                    <strong>{{ $reference['type'] }}</strong>
                    <span>{{ $reference['label'] }}</span>
                </div>
            @endforeach
        </div>
    @endif
</div>
