<div style="display:grid;place-items:center;min-height:24rem;background:#111;padding:1rem;">
    @if ($imageUrl)
        <img
            src="{{ $imageUrl }}"
            alt="{{ $alt }}"
            style="display:block;max-width:100%;max-height:72vh;width:auto;height:auto;object-fit:contain;"
        >
    @else
        <p style="margin:0;color:#bbb;">This image is currently unavailable.</p>
    @endif
</div>
