@php
    $settings = \App\Models\PublicContentSetting::general();
    $socialProfiles = collect($settings->getAttribute('social_links'))
        ->filter(static fn (mixed $link): bool => is_array($link)
            && filled($link['platform'] ?? null)
            && filled($link['url'] ?? null));

    $siteIdentityStatus = filled($settings->getAttribute('favicon_media_asset_id')) ? 'Favicon set' : 'Missing';
    $publicEmailStatus = blank($settings->getAttribute('public_email'))
        ? 'Not set'
        : ((bool) $settings->getAttribute('show_public_email') ? 'Visible' : 'Hidden');
    $contactDeliveryStatus = filled($settings->getAttribute('contact_recipient_email')) ? 'Configured' : 'Server fallback';
    $copyrightStatus = filled($settings->getAttribute('default_media_copyright_notice')) ? 'Default set' : 'None';
    $legalStatus = filled($settings->getAttribute('legal_disclaimer')) ? 'Set' : 'Empty';
@endphp

<x-admin.metrics :columns="6" aria-label="General status">
    <x-admin.metric label="Site identity" :value="$siteIdentityStatus">Favicon</x-admin.metric>
    <x-admin.metric label="Public email" :value="$publicEmailStatus">Public contact</x-admin.metric>
    <x-admin.metric label="Contact delivery" :value="$contactDeliveryStatus">Private recipient</x-admin.metric>
    <x-admin.metric label="Social profiles" :value="(string) $socialProfiles->count()">Configured profiles</x-admin.metric>
    <x-admin.metric label="Media copyright" :value="$copyrightStatus">Default notice</x-admin.metric>
    <x-admin.metric label="Legal text" :value="$legalStatus">Global text</x-admin.metric>
</x-admin.metrics>
