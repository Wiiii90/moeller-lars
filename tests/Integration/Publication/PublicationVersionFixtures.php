<?php

use Illuminate\Support\Facades\DB;

function publicationVersionSettingId(): int
{
    return (int) DB::table('public_content_settings')->where('scope', 'general')->value('id');
}

function publicationVersionSetDisclaimer(?string $value): void
{
    DB::table('public_content_settings')
        ->where('id', publicationVersionSettingId())
        ->update(['legal_disclaimer' => $value]);
}

function publicationVersionDisclaimer(string $schema = 'public'): ?string
{
    $value = DB::table($schema.'.public_content_settings')
        ->where('id', publicationVersionSettingId())
        ->value('legal_disclaimer');

    return is_string($value) ? $value : null;
}
