<x-filament-widgets::widget>
    <x-admin.workspace title="Contact form readiness">
        <x-slot:summary>
            <div><strong>{{ $publishedPlacements }}</strong><span>Published placements</span></div>
            <div><strong>{{ $formPlacements }}</strong><span>Form placements</span></div>
            <div><strong>{{ $formState }}</strong><span>Global form state</span></div>
            <div><strong>{{ $delivery['recipient_ready'] ? $delivery['recipient_source'] : 'Missing' }}</strong><span>Delivery recipient</span></div>
            <div><strong>{{ $delivery['sender_ready'] && $delivery['mailer_ready'] ? 'Ready' : 'Unavailable' }}</strong><span>Mail transport</span></div>
        </x-slot:summary>

        @if (! $delivery['ready'])
            <x-admin.empty-state kicker="Delivery unavailable" title="Contact submissions cannot currently be delivered">
                <p>Recipient, sender identity and mail transport must all be ready before the form can deliver submissions.</p>
            </x-admin.empty-state>
        @endif
    </x-admin.workspace>
</x-filament-widgets::widget>
