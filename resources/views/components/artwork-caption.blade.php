@props(['artwork', 'rows' => null, 'detailUrl' => null])

@php
    $rows ??= \App\Domain\Artwork\ArtworkInformation::rows($artwork);
@endphp

@if ($rows !== [])
    <span class="artwork-caption">
        @foreach ($rows as $row)
            <span class="artwork-caption__row">
                @foreach (['left', 'center', 'right'] as $alignment)
                    @if ($row[$alignment] !== [])
                        <span class="artwork-caption__group artwork-caption__group--{{ $alignment }}">
                            @foreach ($row[$alignment] as $item)
                                <span class="artwork-caption__field artwork-caption__field--{{ $item['field'] }}">
                                    @if ($item['field'] === 'title' && filled($detailUrl))
                                        <a class="artwork-caption__title-link" href="{{ $detailUrl }}">{{ $item['text'] }}</a>
                                    @else
                                        {{ $item['text'] }}
                                    @endif
                                </span>
                            @endforeach
                        </span>
                    @endif
                @endforeach
            </span>
        @endforeach
    </span>
@endif
