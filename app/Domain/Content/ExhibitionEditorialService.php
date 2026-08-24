<?php

namespace App\Domain\Content;

use App\Domain\Admin\AdminAuditService;
use App\Domain\Admin\EditorialRichTextValidator;
use App\Models\Exhibition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class ExhibitionEditorialService
{
    public function __construct(
        private readonly AdminAuditService $audit,
        private readonly EditorialRichTextValidator $richText,
        private readonly JournalEntryOrderService $order,
        private readonly SafeLinkPolicy $links,
    ) {}

    /** @param array<string, mixed> $data */
    public function update(Exhibition $exhibition, array $data): Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($exhibition, $data, $actor): Exhibition {
            /** @var Exhibition $fresh */
            $fresh = Exhibition::query()->whereKey($exhibition->getKey())->lockForUpdate()->firstOrFail();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            if (array_key_exists('site_section_id', $data) && (int) $data['site_section_id'] !== $sectionId) {
                throw ValidationException::withMessages([
                    'site_section_id' => 'Move exhibitions between Journals through an explicit editorial workflow.',
                ]);
            }

            $payload = $this->validatedEditorialData($data, $fresh);
            $payload['site_section_id'] = $sectionId;
            $fresh->fill($payload);

            if ($fresh->isDirty()) {
                $fresh->save();
                $this->audit->record($actor, 'exhibition.updated', 'exhibition', $fresh->getKey());
            }

            return $fresh->fresh(['mediaUsages.mediaAsset']);
        });
    }

    public function publish(Exhibition $exhibition): Exhibition
    {
        return $this->transition($exhibition, 'published', ['draft'], 'published');
    }

    public function archive(Exhibition $exhibition): Exhibition
    {
        return $this->transition($exhibition, 'archived', ['draft', 'published'], 'archived');
    }

    public function restoreDraft(Exhibition $exhibition): Exhibition
    {
        return $this->transition($exhibition, 'draft', ['archived'], 'restored_to_draft');
    }

    public function canMove(Exhibition $exhibition, string $direction): bool
    {
        return $this->order->canMove($exhibition, $direction);
    }

    public function move(Exhibition $exhibition, string $direction): bool
    {
        return $this->order->move($exhibition, $direction);
    }

    public function delete(Exhibition $exhibition): void
    {
        $actor = $this->audit->requireActor();

        DB::transaction(function () use ($exhibition, $actor): void {
            /** @var Exhibition $fresh */
            $fresh = Exhibition::query()->whereKey($exhibition->getKey())->lockForUpdate()->firstOrFail();
            if ((string) $fresh->getAttribute('state') === 'published') {
                throw ValidationException::withMessages([
                    'exhibition' => 'Archive this exhibition before deleting it.',
                ]);
            }

            $exhibitionId = (int) $fresh->getKey();
            $sectionId = (int) $fresh->getAttribute('site_section_id');
            $mediaUsageCount = $fresh->mediaUsages()->count();

            // Remove only relationship records. Canonical Media Files are deliberately preserved.
            $fresh->mediaUsages()->delete();
            $fresh->delete();

            $this->audit->record($actor, 'exhibition.deleted', 'exhibition', $exhibitionId, [
                'site_section_id' => $sectionId,
                'detached_media_usages' => $mediaUsageCount,
            ]);
        });
    }

    /** @param list<string> $allowedFrom */
    private function transition(Exhibition $exhibition, string $state, array $allowedFrom, string $action): Exhibition
    {
        $actor = $this->audit->requireActor();

        return DB::transaction(function () use ($exhibition, $state, $allowedFrom, $action, $actor): Exhibition {
            /** @var Exhibition $fresh */
            $fresh = Exhibition::query()->whereKey($exhibition->getKey())->lockForUpdate()->firstOrFail();
            $current = (string) $fresh->getAttribute('state');

            if ($current === $state) {
                return $fresh;
            }
            if (! in_array($current, $allowedFrom, true)) {
                throw ValidationException::withMessages([
                    'state' => 'This exhibition cannot move from '.$current.' to '.$state.'.',
                ]);
            }

            $fresh->setAttribute('state', $state);
            if ($state === 'published' && $fresh->getAttribute('published_at') === null) {
                $fresh->setAttribute('published_at', now());
            }
            $fresh->save();
            $this->audit->record($actor, 'exhibition.'.$action, 'exhibition', $fresh->getKey());

            return $fresh->fresh();
        });
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function validatedEditorialData(array $data, Exhibition $exhibition): array
    {
        $allowed = [
            'site_section_id',
            'slug',
            'title',
            'date_text',
            'opening_text',
            'kind',
            'starts_on',
            'ends_on',
            'venue',
            'city',
            'country',
            'location_text',
            'description',
            'external_url',
            'directions_url',
        ];
        $payload = array_intersect_key($data, array_flip($allowed));

        Validator::make($payload, [
            'site_section_id' => ['required', 'integer', 'min:1'],
            'slug' => [
                'required',
                'string',
                'max:180',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('exhibitions', 'slug')->ignore($exhibition->getKey()),
            ],
            'title' => ['required', 'string', 'max:240'],
            'date_text' => ['required', 'string', 'max:160'],
            'opening_text' => ['nullable', 'string', 'max:500'],
            'kind' => ['nullable', Rule::in(['solo', 'group'])],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date'],
            'venue' => ['nullable', 'string', 'max:240'],
            'city' => ['nullable', 'string', 'max:160'],
            'country' => ['nullable', 'string', 'max:160'],
            'location_text' => ['nullable', 'string', 'max:500'],
            'description' => ['nullable', 'string', 'max:10000'],
            'external_url' => ['nullable', 'string', 'max:2048'],
            'directions_url' => ['nullable', 'string', 'max:2048'],
        ])->validate();

        $this->richText->validate($payload['description'] ?? null, 'description');
        foreach (['external_url', 'directions_url'] as $field) {
            $url = $payload[$field] ?? null;
            if (is_string($url) && $url !== '' && ! $this->links->isAllowed($url)) {
                throw ValidationException::withMessages([$field => 'Use a safe absolute URL.']);
            }
        }

        return $payload;
    }
}
