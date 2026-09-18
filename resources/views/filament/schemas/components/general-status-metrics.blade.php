@php
    $settings = $generalSettings ?? \App\Models\PublicContentSetting::general();
    $lastChanged = $settings->getAttribute('updated_at');
    $lastChangedValue = $lastChanged instanceof \DateTimeInterface
        ? $lastChanged->format('j M · H:i')
        : 'Never';
    $changesLast30Days = \App\Models\AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->where('entity_type', 'public_content_setting')
        ->where('entity_id', (int) $settings->getKey())
        ->where('occurred_at', '>=', now()->subDays(30))
        ->count();
    $socialProfilesCount = count(\App\Domain\Content\SocialLinks::configured($settings->getAttribute('social_links')));
    $publicEmailStatus = filled($settings->getAttribute('public_email')) ? 'Configured' : 'Not set';
    $contactDeliveryStatus = filled($settings->getAttribute('contact_recipient_email')) ? 'Configured' : 'Not configured';
    $copyrightSet = filled($settings->getAttribute('default_media_copyright_notice'));
    $disclaimerSet = filled($settings->getAttribute('legal_disclaimer'));
    $legalStatus = $copyrightSet && $disclaimerSet ? 'Complete' : (($copyrightSet || $disclaimerSet) ? 'Partial' : 'Empty');
@endphp

<x-admin.metrics :columns="6" class="general-status-metrics" aria-label="General status">
    <x-admin.metric label="Last changed" :value="$lastChangedValue" description="General settings" />
    <x-admin.metric label="Changes · 30d" :value="(string) $changesLast30Days" description="General updates" />
    <x-admin.metric label="Public email" :value="$publicEmailStatus" description="Public contact" />
    <x-admin.metric label="Contact delivery" :value="$contactDeliveryStatus" description="Private recipient" />
    <x-admin.metric label="Social profiles" :value="(string) $socialProfilesCount" description="Configured" />
    <x-admin.metric label="Legal" :value="$legalStatus" description="Copyright + disclaimer" />
</x-admin.metrics>
