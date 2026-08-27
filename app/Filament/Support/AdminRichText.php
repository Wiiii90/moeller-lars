<?php

namespace App\Filament\Support;

use App\Domain\Content\RichTextMediaReference;
use App\Models\MediaAsset;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Components\View;
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

        $pickerKey = substr(sha1($name), 0, 12);
        $pickerName = '__rich_text_media_'.$pickerKey;

        $editor = MarkdownEditor::make($name)
            ->label($label)
            ->toolbarButtons($toolbar)
            ->fileAttachments(false)
            ->nullable()
            ->columnSpanFull()
            ->extraFieldWrapperAttributes([
                'class' => 'admin-rich-text',
                'data-admin-rich-text-editor' => $pickerKey,
            ]);

        if ($maxLength !== null) {
            $editor->maxLength($maxLength);
        }

        if (! $allowEmbeddedMedia) {
            return [$editor];
        }

        $editor->extraAlpineAttributes([
            'data-admin-rich-text-editor-input' => $pickerKey,
            'x-init' => <<<JS
                \$el.addEventListener('admin-rich-text-image-insert', (event) => {
                    const markdown = event.detail?.markdown
                    if (typeof markdown !== 'string' || markdown === '') return

                    const current = String(state ?? '').trim()
                    state = current === '' ? markdown : current + '\n\n' + markdown
                })

                const bindImageToolbarAction = () => {
                    const toolbarButton = \$el.querySelector('.editor-toolbar .upload-image')
                    const scope = \$el.closest('.fi-modal-window, form, .fi-page') ?? document
                    const insertFlow = scope.querySelector('[data-admin-rich-text-image-insert="{$pickerKey}"]')
                    if (! toolbarButton || ! insertFlow || toolbarButton.dataset.adminImageBound === 'true') return false

                    toolbarButton.dataset.adminImageBound = 'true'
                    toolbarButton.setAttribute('title', 'Insert image')
                    toolbarButton.setAttribute('aria-label', 'Insert image')
                    toolbarButton.addEventListener('click', (event) => {
                        event.preventDefault()
                        event.stopImmediatePropagation()
                        insertFlow.dispatchEvent(new CustomEvent('admin-rich-text-image-open'))
                    }, true)

                    return true
                }

                if (! bindImageToolbarAction()) {
                    const observer = new MutationObserver(() => {
                        if (bindImageToolbarAction()) observer.disconnect()
                    })
                    observer.observe(\$el.closest('.fi-modal-window, form, .fi-page') ?? document.body, { childList: true, subtree: true })
                    setTimeout(() => observer.disconnect(), 3000)
                }
            JS,
        ]);

        $picker = MediaAssetSelect::makeId($pickerName, 'Image from Media Files', imagesOnly: true)
            ->hiddenLabel()
            ->nullable()
            ->dehydrated(false)
            ->live()
            ->extraFieldWrapperAttributes([
                'class' => 'admin-rich-text-media-picker',
                'data-admin-rich-text-media-picker' => $pickerKey,
                'x-show' => "source === 'media'",
            ])
            ->afterStateUpdated(function (mixed $state, Get $get, Set $set, $livewire) use ($name, $pickerName, $pickerKey): void {
                if ($state === null || $state === '') {
                    return;
                }

                $id = filter_var($state, FILTER_VALIDATE_INT);
                if ($id === false || $id <= 0) {
                    throw ValidationException::withMessages([$pickerName => 'Choose an image from Media Files.']);
                }

                $valid = MediaAsset::query()
                    ->whereKey((int) $id)
                    ->where('state', 'available')
                    ->where('mime_type', 'like', 'image/%')
                    ->exists();
                if (! $valid) {
                    throw ValidationException::withMessages([$pickerName => 'Choose an available image from Media Files.']);
                }

                $currentState = $get($name);
                $current = trim(is_string($currentState) ? $currentState : '');
                $reference = RichTextMediaReference::markdown((int) $id);
                $set($name, $current === '' ? $reference : $current."\n\n".$reference);
                $set($pickerName, null);
                $livewire->dispatch('admin-rich-text-image-close', key: $pickerKey);
            });

        $insertFlow = Group::make([
            View::make('filament.support.rich-text-image-insert'),
            $picker,
        ])
            ->columnSpanFull()
            ->extraAttributes([
                'class' => 'admin-rich-text-image-insert',
                'data-admin-rich-text-image-insert' => $pickerKey,
                'x-cloak' => true,
                'x-show' => 'open',
                'x-data' => <<<JS
                    {
                        key: '{$pickerKey}',
                        open: false,
                        source: 'media',
                        externalUrl: '',
                        externalError: '',
                        submitExternal(element) {
                            const value = this.externalUrl.trim()
                            let parsed

                            try {
                                parsed = new URL(value)
                            } catch (error) {
                                this.externalError = 'Enter a valid HTTP or HTTPS image URL.'
                                return
                            }

                            if (! ['http:', 'https:'].includes(parsed.protocol) || parsed.username !== '' || parsed.password !== '') {
                                this.externalError = 'Enter a valid HTTP or HTTPS image URL.'
                                return
                            }

                            const source = parsed.href.replaceAll('(', '%28').replaceAll(')', '%29')
                            const scope = element.closest('.fi-modal-window, form, .fi-page') ?? document
                            const editor = scope.querySelector('[data-admin-rich-text-editor-input="' + this.key + '"]')
                            if (! editor) {
                                this.externalError = 'The editor is not available.'
                                return
                            }

                            editor.dispatchEvent(new CustomEvent('admin-rich-text-image-insert', {
                                detail: { markdown: '![](' + source + ')' },
                            }))
                            this.externalUrl = ''
                            this.externalError = ''
                            this.open = false
                        },
                    }
                JS,
                'x-on:admin-rich-text-image-open' => "open = true; source = 'media'; externalError = ''; \$nextTick(() => \$el.querySelector('[role=\"combobox\"]')?.focus())",
                'x-on:admin-rich-text-image-close.window' => "if (\$event.detail.key === key) { open = false; externalUrl = ''; externalError = ''; }",
                'x-on:click.outside' => 'open = false',
                'x-on:keydown.escape.window' => 'open = false',
            ]);

        return [$editor, $insertFlow];
    }
}
