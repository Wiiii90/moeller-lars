@php
    $settings = \App\Models\PublicContentSetting::general();
    $socialProfiles = collect($settings->getAttribute('social_links'))
        ->filter(static fn (mixed $link): bool => is_array($link)
            && filled($link['platform'] ?? null)
            && filled($link['url'] ?? null));

    $backgroundMode = match ($settings->getAttribute('background_mode')) {
        \App\Domain\Content\PublicAppearance::MODE_SOLID => 'Solid',
        \App\Domain\Content\PublicAppearance::MODE_GRADIENT => 'Gradient',
        default => 'Default',
    };
    $faviconStatus = filled($settings->getAttribute('favicon_media_asset_id')) ? 'Set' : 'Not set';
    $publicEmailStatus = blank($settings->getAttribute('public_email'))
        ? 'Not set'
        : ((bool) $settings->getAttribute('show_public_email') ? 'Visible' : 'Hidden');
    $contactDeliveryStatus = filled($settings->getAttribute('contact_recipient_email')) ? 'Configured' : 'Default recipient';
    $copyrightSet = filled($settings->getAttribute('default_media_copyright_notice'));
    $disclaimerSet = filled($settings->getAttribute('legal_disclaimer'));
    $legalStatus = $copyrightSet && $disclaimerSet ? 'Complete' : (($copyrightSet || $disclaimerSet) ? 'Partial' : 'Empty');
@endphp

<x-admin.metrics class="admin-metrics--open-bottom" :columns="6" aria-label="General status">
    <x-admin.metric label="Favicon" :value="$faviconStatus">Site icon</x-admin.metric>
    <x-admin.metric label="Background" :value="$backgroundMode">Public site</x-admin.metric>
    <x-admin.metric label="Public email" :value="$publicEmailStatus">Public contact</x-admin.metric>
    <x-admin.metric label="Contact delivery" :value="$contactDeliveryStatus">Private recipient</x-admin.metric>
    <x-admin.metric label="Social profiles" :value="(string) $socialProfiles->count()">Configured profiles</x-admin.metric>
    <x-admin.metric label="Legal" :value="$legalStatus">Copyright + disclaimer</x-admin.metric>
</x-admin.metrics>
