<?php

use App\Domain\Admin\AdminMutationSnapshotBuffer;
use App\Domain\Admin\AdminSettingsService;
use App\Domain\Publication\PublicationService;
use App\Http\Middleware\AttachPublicationState;
use App\Models\PublicContentSetting;
use App\Models\User;
use Illuminate\Http\Request;

it('adds publication state to the same Livewire response only after a publication mutation', function (): void {
    $actor = User::factory()->admin()->create();
    $this->actingAs($actor, 'web');

    $middleware = app(AttachPublicationState::class);
    $request = Request::create('/livewire/update', 'POST');
    $request->headers->set('X-Livewire', '');

    $unchanged = $middleware->handle($request, static fn () => response('ok'));
    expect($unchanged->headers->has(AttachPublicationState::HEADER))->toBeFalse();

    app(AdminSettingsService::class)->updatePublicContent(PublicContentSetting::general(), [
        'legal_disclaimer' => 'Response state '.fake()->uuid(),
    ]);

    expect(app(AdminMutationSnapshotBuffer::class)->publicationStateMayHaveChanged())->toBeTrue()
        ->and(app(PublicationService::class)->hasPendingChanges())->toBeTrue();

    $changed = $middleware->handle($request, static fn () => response('ok'));
    expect($changed->headers->get(AttachPublicationState::HEADER))->toBe('1');

    app(PublicationService::class)->commit($actor, 'Response state test');

    $committed = $middleware->handle($request, static fn () => response('ok'));
    expect($committed->headers->get(AttachPublicationState::HEADER))->toBe('0');
});
