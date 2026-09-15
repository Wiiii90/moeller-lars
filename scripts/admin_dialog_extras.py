from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]


def replace_once(path: Path, old: str, new: str, label: str) -> None:
    text = path.read_text()
    count = text.count(old)
    if count != 1:
        raise SystemExit(f'{label}: expected one match, found {count}')
    path.write_text(text.replace(old, new, 1))


# Activity nested Undo confirmation: the containing viewer is migrated by the
# main finalizer, which also adds AdminDialog/AdminDialogSize imports.
activity = ROOT / 'app/Filament/Pages/Activity.php'
replace_once(
    activity,
    """            $actions[] = Action::make('undoActivityEvent')
                ->label('Undo')
                ->icon(AdminIcon::Refresh->value)
                ->iconButton()
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Undo change?')
                ->modalDescription((string) $event['undo']['confirmation'])
                ->action(fn (): mixed => $this->undo($receiptId));""",
    """            $actions[] = AdminDialog::confirm(
                Action::make('undoActivityEvent')
                    ->label('Undo')
                    ->icon(AdminIcon::Refresh->value)
                    ->iconButton()
                    ->color('gray')
                    ->action(fn (): mixed => $this->undo($receiptId)),
                heading: 'Undo change?',
                description: (string) $event['undo']['confirmation'],
                submitLabel: 'Undo',
                danger: false,
                size: AdminDialogSize::Mini,
                icon: AdminIcon::Refresh,
            );""",
    'Activity undo confirmation',
)

# Gallery multi-upload creates artwork drafts, so it belongs to the canonical
# Create family rather than a bespoke 7XL Filament modal.
direct_upload = ROOT / 'app/Filament/Pages/Concerns/GalleryWorkspaceDirectUpload.php'
text = direct_upload.read_text()
anchor = 'use App\\Domain\\Media\\MediaTypePolicy;\n'
imports = 'use App\\Filament\\Support\\Dialogs\\AdminDialog;\nuse App\\Filament\\Support\\Dialogs\\AdminDialogSize;\n'
if imports not in text:
    if text.count(anchor) != 1:
        raise SystemExit('GalleryWorkspaceDirectUpload: import anchor changed')
    text = text.replace(anchor, anchor + imports, 1)
if text.count("        return Action::make('batchAddArtworks')") != 1:
    raise SystemExit('GalleryWorkspaceDirectUpload: batch action changed')
text = text.replace(
    "        return Action::make('batchAddArtworks')",
    "        return AdminDialog::create(\n            Action::make('batchAddArtworks')",
    1,
)
for line in (
    "            ->modalSubmitActionLabel(fn (): string => 'Add '.count($this->pendingBatchArtworkMedia).' artworks')\n",
    "            ->modalCancelActionLabel('Cancel')\n",
    '            ->modalWidth(Width::SevenExtraLarge)\n',
):
    if text.count(line) != 1:
        raise SystemExit(f'GalleryWorkspaceDirectUpload: expected legacy line {line.strip()}')
    text = text.replace(line, '', 1)
tail = """                    ->success()
                    ->send();
            });
    }

    private function isGalleryPrimaryVisual"""
new_tail = """                    ->success()
                    ->send();
            }),
            'Add artworks',
            AdminDialogSize::Large,
        );
    }

    private function isGalleryPrimaryVisual"""
if text.count(tail) != 1:
    raise SystemExit('GalleryWorkspaceDirectUpload: batch action tail changed')
text = text.replace(tail, new_tail, 1)
if 'Width::' not in text:
    text = text.replace('use Filament\\Support\\Enums\\Width;\n', '')
direct_upload.write_text(text)

# Filament Select's inline "create option" modal is still an Action; route its
# chrome through AdminDialog::create instead of owning a submit label locally.
material_select = ROOT / 'app/Filament/Resources/Artworks/Support/ArtworkMaterialSelect.php'
text = material_select.read_text()
anchor = 'use App\\Domain\\Artwork\\ArtworkMaterialPresetService;\n'
imports = 'use App\\Filament\\Support\\Dialogs\\AdminDialog;\nuse App\\Filament\\Support\\Dialogs\\AdminDialogSize;\n'
if imports not in text:
    if text.count(anchor) != 1:
        raise SystemExit('ArtworkMaterialSelect: import anchor changed')
    text = text.replace(anchor, anchor + imports, 1)
old = """            ->createOptionAction(fn (Action $action): Action => $action
                ->label('Add material')
                ->modalSubmitActionLabel('Add material'));"""
new = """            ->createOptionAction(fn (Action $action): Action => AdminDialog::create(
                $action->label('Add material'),
                'Add material',
                AdminDialogSize::Mini,
            ));"""
if text.count(old) != 1:
    raise SystemExit('ArtworkMaterialSelect: create-option action changed')
material_select.write_text(text.replace(old, new, 1))

print('Additional dialog families migrated: Activity undo, Gallery batch create, Material create-option.')
