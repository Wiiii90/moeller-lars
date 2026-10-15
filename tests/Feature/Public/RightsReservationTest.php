<?php

use App\Http\Controllers\PublicSeoController;

it('publishes a valid site-wide TDMRep document', function (): void {
    $response = app(PublicSeoController::class)->tdmReservation();

    expect($response->headers->get('Content-Type'))->toContain('application/json')
        ->and(json_decode($response->getContent(), true, flags: JSON_THROW_ON_ERROR))
        ->toBe([['location' => '/', 'tdm-reservation' => 1]]);
});

it('does not sacrifice conventional search indexing to block training crawlers', function (): void {
    $robots = app(PublicSeoController::class)->robots()->getContent();

    foreach (['GPTBot', 'Google-Extended', 'ClaudeBot', 'CCBot'] as $agent) {
        expect($robots)->toContain("User-agent: {$agent}\nDisallow: /");
    }
    expect($robots)->toContain("User-agent: *\nAllow: /\nDisallow: /admin")
        ->not->toContain('User-agent: Googlebot')
        ->not->toContain('User-agent: OAI-SearchBot');
});
