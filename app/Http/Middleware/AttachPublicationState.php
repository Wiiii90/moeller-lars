<?php

namespace App\Http\Middleware;

use App\Domain\Admin\AdminMutationSnapshotBuffer;
use App\Domain\Publication\PublicationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class AttachPublicationState
{
    public const HEADER = 'X-Publication-Pending';

    public function __construct(
        private readonly AdminMutationSnapshotBuffer $mutations,
        private readonly PublicationService $publication,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->hasHeader('X-Livewire') || ! $this->mutations->publicationStateMayHaveChanged()) {
            return $response;
        }

        $response->headers->set(
            self::HEADER,
            $this->publication->hasPendingChanges() ? '1' : '0',
        );

        return $response;
    }
}
