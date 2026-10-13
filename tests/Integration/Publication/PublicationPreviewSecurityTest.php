<?php

use App\Http\Middleware\ProtectArtistPreview;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

require_once __DIR__.'/PublicationWorkflowFixtures.php';

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');

    Route::middleware(['web', ProtectArtistPreview::class])->get('/preview/__publication/probe/value', static function () {
        return response((string) PublicContentSetting::general()->getAttribute('legal_disclaimer'));
    });
});

it('keeps Preview authentication and private indexing protections unchanged', function (): void {
    Auth::guard('web')->logout();

    $this->get('/preview/__publication/probe/value')->assertNotFound();

    $preview = $this->actingAs($this->actor, 'web')
        ->get('/preview/__publication/probe/value');
    $preview
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow, noarchive')
        ->assertHeader('Pragma', 'no-cache');
    publicationWorkflowAssertPrivateNoCache($preview);
});


it('denies a signed-in non-admin access to Working preview content', function (): void {
    $secret = 'Private unfinished legal copy';
    PublicContentSetting::general()->update(['legal_disclaimer' => $secret]);

    $this->actingAs(User::factory()->create(), 'web')
        ->get('/preview/__publication/probe/value')
        ->assertNotFound()
        ->assertDontSee($secret);

    $this->actingAs($this->actor, 'web')
        ->get('/preview/__publication/probe/value')
        ->assertOk()
        ->assertSee($secret);
});
