@php
    $settings = \App\Models\PublicContentSetting::general();
    $lastChanged = $settings->getAttribute('updated_at');
    $lastChangedValue = $lastChanged instanceof \DateTimeInterface
        ? $lastChanged->format('Y-m-d H:i')
        : 'Never';
    $changesLast30Days = \App\Models\AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->where('entity_type', 'public_content_setting')
        ->where('entity_id', (int) $settings->getKey())
        ->where('occurred_at', '>=', now()->subDays(30))
        ->count();
    $socialProfiles = collect($settings->getAttribute('social_links'))
        ->filter(static fn (mixed $link): bool => is_array($link)
            && filled($link['platform'] ?? null)
            && filled($link['url'] ?? null));
    $visibleSocialProfiles = $socialProfiles
        ->filter(static fn (array $link): bool => (bool) ($link['visible'] ?? true))
        ->count();
    $socialProfilesValue = $visibleSocialProfiles.' / '.$socialProfiles->count();
    $publicEmailStatus = blank($settings->getAttribute('public_email'))
        ? 'Not set'
        : ((bool) $settings->getAttribute('show_public_email') ? 'Visible' : 'Hidden');
    $contactDeliveryStatus = filled($settings->getAttribute('contact_recipient_email')) ? 'Configured' : 'Default recipient';
    $copyrightSet = filled($settings->getAttribute('default_media_copyright_notice'));
    $disclaimerSet = filled($settings->getAttribute('legal_disclaimer'));
    $legalStatus = $copyrightSet && $disclaimerSet ? 'Complete' : (($copyrightSet || $disclaimerSet) ? 'Partial' : 'Empty');
@endphp

<x-admin.metrics :columns="6" aria-label="General status">
    <x-admin.metric label="Last changed" :value="$lastChangedValue" />
    <x-admin.metric label="Changes · 30d" :value="(string) $changesLast30Days" />
    <x-admin.metric label="Public email" :value="$publicEmailStatus" />
    <x-admin.metric label="Contact delivery" :value="$contactDeliveryStatus" />
    <x-admin.metric label="Social profiles" :value="$socialProfilesValue" />
    <x-admin.metric label="Legal" :value="$legalStatus" />
</x-admin.metrics>
