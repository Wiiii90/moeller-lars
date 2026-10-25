<?php

use App\Models\PublicContentSetting;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;

function publicationWorkflowSetGradientBaseline(string $end = '#C9C3C3'): void
{
    $appearance = [
        'background_mode' => 'gradient',
        'background_gradient_start' => '#555555',
        'background_gradient_end' => $end,
        'background_gradient_angle' => 2,
    ];

    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.public_content_settings')
            ->where('scope', PublicContentSetting::SCOPE_GENERAL)
            ->update($appearance);
    }
}

function publicationWorkflowUseRenderableHome(): void
{
    foreach (['public', 'committed'] as $schema) {
        DB::table($schema.'.home_presentation_settings')->update([
            'template' => 'custom',
        ]);
    }
}

function publicationWorkflowAssertPrivateNoCache(TestResponse $response): void
{
    $directives = array_map('trim', explode(',', (string) $response->headers->get('Cache-Control')));
    $expected = ['private', 'no-store', 'max-age=0', 'must-revalidate'];
    sort($directives);
    sort($expected);

    expect($directives)->toBe($expected);
}
