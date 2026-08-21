<x-filament-widgets::widget>
    <section class="artist-workspace" aria-label="Contact and mail delivery health">
        <header class="artist-workspace__head">
            <div>
                <p class="artist-workspace__kicker">Contact &amp; delivery</p>
                <h2>Contact form readiness</h2>
                <p>Published Contact components and non-sensitive mail-delivery readiness from the same runtime contract used by submissions.</p>
            </div>
            <div class="artist-dashboard__quick-actions" aria-label="Contact settings actions">
                <a class="artist-action" href="{{ $generalUrl }}">General</a>
                <a class="artist-action" href="{{ $pagesUrl }}">Pages</a>
            </div>
        </header>

        <div class="artist-workspace__summary" aria-label="Contact health summary">
            <div>
                <strong>{{ $publishedPlacements }}</strong>
                <span>Published placements</span>
            </div>
            <div>
                <strong>{{ $formPlacements }}</strong>
                <span>Form placements</span>
            </div>
            <div>
                <strong>{{ $formState }}</strong>
                <span>Global form state</span>
            </div>
            <div>
                <strong>{{ $delivery['recipient_ready'] ? $delivery['recipient_source'] : 'Missing' }}</strong>
                <span>Delivery recipient</span>
            </div>
            <div>
                <strong>{{ $delivery['sender_ready'] && $delivery['mailer_ready'] ? 'Ready' : 'Unavailable' }}</strong>
                <span>Mail transport</span>
            </div>
        </div>

        @if (! $delivery['ready'])
            <p class="artist-dashboard__quiet">Contact submissions cannot be delivered until recipient, sender identity and mail transport are all ready.</p>
        @endif
    </section>
</x-filament-widgets::widget>
