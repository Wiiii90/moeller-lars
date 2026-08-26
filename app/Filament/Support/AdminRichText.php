<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextMediaReference;
use App\Models\MediaAsset;
use Filament\Actions\Action;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Component;
use Illuminate\Validation\ValidationException;

final class AdminRichText
{
    /** @return array<int, Component> */
    public static function schema(
        string $name,
        string $label,
        ?int $maxLength = null,
        bool $allowEmbeddedMedia = true,
    ): array {
        $toolbar = [
            ['bold', 'italic', 'link'],
            ['heading'],
            ['bulletList', 'orderedList'],
            ['undo', 'redo'],
        ];

        if ($allowEmbeddedMedia) {
            $toolbar[2][] = 'attachFiles';
        }

        $editor = MarkdownEditor::make($name)
            ->label($label)
            ->toolbarButtons($toolbar)
            ->fileAttachments(false)
            ->nullable()
            ->columnSpanFull()
            ->extraFieldWrapperAttributes([
                'class' => 'admin-rich-text',
            ]);

        if ($maxLength !== null) {
            $editor->maxLength($maxLength);
        }

        if ($allowEmbeddedMedia) {
            $editor->extraAlpineAttributes([
                'x-init' => <<<'JS'
                    const bindMediaToolbarAction = () => {
                        const toolbarButton = $el.querySelector('.editor-toolbar .upload-image')
                        const actionButton = $el.closest('.admin-rich-text')?.querySelector('[data-admin-rich-text-media-action]')
                        if (! toolbarButton || ! actionButton || toolbarButton.dataset.adminMediaBound === 'true') return false

                        toolbarButton.dataset.adminMediaBound = 'true'
                        toolbarButton.setAttribute('title', 'Insert image from Media Files')
                        toolbarButton.setAttribute('aria-label', 'Insert image from Media Files')
                        toolbarButton.addEventListener('click', (event) => {
                            event.preventDefault()
                            event.stopImmediatePropagation()
                            actionButton.click()
                        }, true)

                        return true
                    }

                    if (! bindMediaToolbarAction()) {
                        const observer = new MutationObserver(() => {
                            if (bindMediaToolbarAction()) observer.disconnect()
                        })
                        observer.observe($el, { childList: true, subtree: true })
                        setTimeout(() => observer.disconnect(), 3000)
                    }
                JS,
            ]);

            $editor->aboveContent(
                Action::make('insertRichTextMedia_'.substr(sha1($name), 0, 12))
                    ->label('Insert image from Media Files')
                    ->extraAttributes([
                        'data-admin-rich-text-media-action' => 'true',
                        'style' => 'display:none',
                    ])
                    ->modalHeading('Insert image from Media Files')
                    ->modalSubmitActionLabel('Insert image')
                    ->modalCancelActionLabel('Cancel')
                    ->schema([
                        MediaAssetSelect::makeId('media_asset_id', 'Image', imagesOnly: true)->required(),
                    ])
                    ->action(function (array $data, Component $schemaComponent, mixed $schemaComponentState): void {
                        $id = filter_var($data['media_asset_id'] ?? null, FILTER_VALIDATE_INT);
                        if ($id === false || $id <= 0) {
                            throw ValidationException::withMessages(['media_asset_id' => 'Choose an image from Media Files.']);
                        }

                        $valid = MediaAsset::query()
                            ->whereKey((int) $id)
                            ->where('state', 'available')
                            ->where('mime_type', 'like', 'image/%')
                            ->exists();
                        if (! $valid) {
                            throw ValidationException::withMessages(['media_asset_id' => 'Choose an available image from Media Files.']);
                        }

                        $current = trim(is_string($schemaComponentState) ? $schemaComponentState : '');
                        $reference = RichTextMediaReference::markdown((int) $id);
                        $schemaComponent->state($current === '' ? $reference : $current."\n\n".$reference);
                    }),
            );
        }

        return [$editor];
    }
}
