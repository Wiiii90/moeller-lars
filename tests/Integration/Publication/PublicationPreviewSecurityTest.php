<?php

use App\Domain\Admin\AdminSettingsService;
use App\Domain\Media\MediaAssetEditorialService;
use App\Domain\Publication\PublicationMediaCleanupService;
use App\Domain\Publication\PublicationService;
use App\Domain\Publication\PublicationSnapshot;
use App\Filament\Support\AdminActivityFeed;
use App\Http\Middleware\ProtectArtistPreview;
use App\Livewire\Admin\PublicationStateControl;
use App\Models\AuditEvent;
use App\Models\MediaAsset;
use App\Models\MediaVariant;
use App\Models\PublicationCheckpoint;
use App\Models\PublicationCheckpointEvent;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Livewire\Livewire;

require_once __DIR__.'/PublicationWorkflowFixtures.php';

beforeEach(function (): void {
    $this->actor = User::factory()->admin()->create();
    $this->actingAs($this->actor, 'web');

    Route::middleware('web')->get('/__publication/probe/value', static function () {
        return response((string) PublicContentSetting::general()->getAttribute('legal_disclaimer'));
    });

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
