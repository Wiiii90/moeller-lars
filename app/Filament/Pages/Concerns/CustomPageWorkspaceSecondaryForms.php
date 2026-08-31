<?php

namespace App\Filament\Pages\Concerns;

use App\Models\CustomPageSetting;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Validation\ValidationException;

trait CustomPageWorkspaceSecondaryForms
{
    /** @return list<mixed> */
    private function contactChildEditorSchema(?string $childType, bool $includeTypeSelect, array $arguments): array
    {
        $fields = [];
        if ($includeTypeSelect) {
            $fields[] = Select::make('child_type')
                ->label('Contact item')
                ->options($this->availableContactChildOptions($arguments))
                ->required()
                ->live();
        } else {
            $fields[] = Hidden::make('child_type')->default($childType)->required();
        }

        $fields[] = Select::make('publication_state')
            ->label('Status')
            ->options(['published' => 'Published', 'unpublished' => 'Unpublished'])
            ->default('published')
            ->required();

        $fields[] = Grid::make(1)->schema(function (Get $get) use ($childType): array {
            $type = $childType ?? (string) $get('child_type');

            return match ($type) {
                'public_email' => [
                    Placeholder::make('public_email_note')
                        ->label('Public Email')
                        ->content('Uses the canonical public email configured in General.'),
                ],
                'social_links' => [
                    Select::make('social_platforms')
                        ->label('Social links from General')
                        ->options($this->availableSocialPlatforms)
                        ->multiple()
                        ->default(array_keys($this->availableSocialPlatforms)),
                ],
                'contact_form' => [
                    Select::make('form_state')
                        ->label('Form presentation')
                        ->options(['enabled' => 'Enabled', 'under_construction' => 'Under construction'])
                        ->default('enabled')
                        ->required()
                        ->live(),
                    TextInput::make('status_text')
                        ->label('Status text')
                        ->maxLength(500)
                        ->required(fn (Get $get): bool => $get('form_state') === 'under_construction')
                        ->visible(fn (Get $get): bool => $get('form_state') === 'under_construction'),
                ],
                default => [],
            };
        });

        return $fields;
    }

    /** @param array<string,mixed> $block */
    private function componentEditorData(array $block): array
    {
        $data = [
            ...$block,
            'publication_state' => CustomPageSetting::componentPublished($block) ? 'published' : 'unpublished',
        ];
        if (($block['type'] ?? null) === 'divider') {
            $data['variant'] = in_array($block['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true)
                ? $block['variant']
                : 'thin';
        }
        if (($block['type'] ?? null) === 'cv_list') {
            $data['cv_entries'] = $this->cvEntryEditorRows();
        }

        return $data;
    }

    /** @return array<string,mixed> */
    private function componentPayload(array $data, ?array $existing = null): array
    {
        $type = $data['type'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::COMPONENT_LABELS)) {
            throw ValidationException::withMessages(['type' => 'Choose a supported component type.']);
        }
        $published = ($data['publication_state'] ?? 'published') === 'published';

        return match ($type) {
            'image' => [
                'type' => 'image',
                'published' => $published,
                'media_asset_id' => is_numeric($data['media_asset_id'] ?? null) ? (int) $data['media_asset_id'] : null,
                'image_decorative' => (bool) ($data['image_decorative'] ?? false),
            ],
            'cv_list' => [
                'type' => 'cv_list',
                'published' => $published,
                'media_asset_id' => is_numeric($data['media_asset_id'] ?? null) ? (int) $data['media_asset_id'] : null,
            ],
            'text' => [
                'type' => 'text',
                'published' => $published,
                'title' => $data['title'] ?? null,
                'body' => $data['body'] ?? null,
            ],
            'list' => [
                'type' => 'list',
                'published' => $published,
                'title' => $data['title'] ?? null,
                'items' => is_array($existing['items'] ?? null) ? array_values($existing['items']) : [],
            ],
            'divider' => [
                'type' => 'divider',
                'published' => $published,
                'variant' => in_array($data['variant'] ?? null, array_keys(self::DIVIDER_LABELS), true) ? $data['variant'] : 'thin',
            ],
            'contact' => [
                'type' => 'contact',
                'published' => $published,
                'children' => $existing !== null
                    ? $this->settings()->contactChildren($existing)
                    : [
                        ['type' => 'public_email', 'published' => true],
                        ['type' => 'social_links', 'published' => true, 'social_platforms' => array_keys($this->availableSocialPlatforms)],
                        ['type' => 'contact_form', 'published' => true, 'form_state' => 'enabled', 'status_text' => null],
                    ],
            ],
            'legal_disclaimer' => ['type' => 'legal_disclaimer', 'published' => $published],
        };
    }

    /** @return array<string,mixed> */
    private function cvEntryPayload(array $data): array
    {
        return [
            'section' => $data['section'] ?? 'CV',
            'title' => $data['title'] ?? null,
            'year_text' => $data['year_text'] ?? null,
            'date_precision' => $data['date_precision'] ?? 'unknown',
            'starts_on' => $data['starts_on'] ?? null,
            'ends_on' => $data['ends_on'] ?? null,
            'organisation' => $data['organisation'] ?? null,
            'location' => $data['location'] ?? null,
            'body' => $data['body'] ?? null,
            'image_media_asset_id' => $data['image_media_asset_id'] ?? null,
            'external_url' => $data['external_url'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function listItemPayload(array $data): array
    {
        return [
            'published' => ($data['publication_state'] ?? 'published') === 'published',
            'date' => $data['date'] ?? null,
            'title' => $data['title'] ?? null,
            'meta' => $data['meta'] ?? null,
            'location' => $data['location'] ?? null,
            'url' => $data['url'] ?? null,
            'body' => $data['body'] ?? null,
        ];
    }

    /** @return array<string,mixed> */
    private function contactChildPayload(array $data): array
    {
        $type = $data['child_type'] ?? null;
        if (! is_string($type) || ! array_key_exists($type, self::CONTACT_CHILD_LABELS)) {
            throw ValidationException::withMessages(['component' => 'Choose a supported Contact item.']);
        }
        $published = ($data['publication_state'] ?? 'published') === 'published';

        return match ($type) {
            'public_email' => ['type' => 'public_email', 'published' => $published],
            'social_links' => [
                'type' => 'social_links',
                'published' => $published,
                'social_platforms' => $this->validatedSocialPlatforms($data['social_platforms'] ?? []),
            ],
            'contact_form' => [
                'type' => 'contact_form',
                'published' => $published,
                'form_state' => ($data['form_state'] ?? 'enabled') === 'under_construction' ? 'under_construction' : 'enabled',
                'status_text' => filled($data['status_text'] ?? null) ? trim((string) $data['status_text']) : null,
            ],
        };
    }
}
