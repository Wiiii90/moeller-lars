<?php

use App\Models\AuditEvent;
use App\Models\MediaAsset;


function generalStatusFavicon(): MediaAsset
{
    return MediaAsset::query()->create([
        'storage_key' => 'originals/general-favicon.png',
        'original_filename' => 'general-favicon.png',
        'mime_type' => 'image/png',
        'byte_size' => 4,
        'sha256' => hash('sha256', 'general-favicon.png'),
        'state' => 'available',
        'alt_text' => 'Site favicon',
    ]);
}

function generalSettingsAuditCount(): int
{
    return AuditEvent::query()
        ->where('action', 'public_content_setting.updated')
        ->where('entity_type', 'public_content_setting')
        ->count();
}

function generalPublicStyleNonce($response): string
{
    $csp = (string) $response->headers->get('Content-Security-Policy');
    $matched = preg_match("/style-src[^;]*'nonce-([^']+)'/", $csp, $matches);

    expect($matched)->toBe(1)
        ->and($matches)->toHaveKey(1);

    return $matches[1];
}

function generalAssertPublicStyleUsesNonce(string $html, string $nonce, string $css): void
{
    $matched = preg_match_all('/<style\b([^>]*)>(.*?)<\/style>/si', $html, $styles, PREG_SET_ORDER);
    expect($matched)->toBeGreaterThan(0);

    $authorized = false;
    foreach ($styles as $style) {
        if (! str_contains($style[2], $css)) {
            continue;
        }

        $authorized = preg_match(
            '/\bnonce\s*=\s*(["\'])'.preg_quote($nonce, '/').'\1/i',
            $style[1],
        ) === 1;
        break;
    }

    expect($authorized)->toBeTrue();
}
