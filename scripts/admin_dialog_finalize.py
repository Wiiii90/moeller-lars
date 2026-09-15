from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
source_path = ROOT / 'scripts/admin_dialog_migration.py'
helper_path = ROOT / 'app/Filament/Support/Dialogs/AdminDialog.php'

if not source_path.exists():
    raise SystemExit('Expected staged admin_dialog_migration.py')

# Consolidate the pre-contract dialog stylesheet before the stricter migration
# retires it. Shared viewport/modal geometry belongs to dialog-contract.css;
# Media-specific delete-dialog presentation belongs to media.css.
admin_css_path = ROOT / 'resources/css/admin.css'
admin_css = admin_css_path.read_text()
dialog_contract_import = "@import './admin/dialog-contract.css';\n"
if dialog_contract_import not in admin_css:
    anchor = "@import './admin/forms.css';\n"
    if admin_css.count(anchor) != 1:
        raise SystemExit('admin.css forms import changed unexpectedly')
    admin_css = admin_css.replace(anchor, anchor + dialog_contract_import, 1)
    admin_css_path.write_text(admin_css)

dialog_contract_path = ROOT / 'resources/css/admin/dialog-contract.css'
dialog_contract = dialog_contract_path.read_text()
shared_geometry = '''/* Shared Filament viewport/modal geometry. Filament still owns lifecycle,
 * focus, Escape handling and nested interactive behavior. */
html {
    scrollbar-gutter: stable;
}

@media (min-width: 1024px) {
    /* A transformed ancestor becomes the containing block for fixed children.
     * Keep the workspace shift without trapping viewport dialogs in the content. */
    .fi-main {
        position: relative;
        inset-inline-start: calc(0rem - var(--admin-workspace-visual-shift));
        transform: none;
    }
}

.fi-modal-close-overlay {
    position: fixed;
    inset: 0;
}

.fi-modal-window {
    max-height: calc(100dvh - 2rem);
}

.fi-modal-header,
.fi-modal-footer {
    flex: 0 0 auto;
}

.fi-modal-content {
    min-height: 0;
    flex: 1 1 auto;
    overflow-y: auto;
    overscroll-behavior: contain;
    scrollbar-gutter: stable;
}

@supports not (height: 100dvh) {
    .fi-modal-window {
        max-height: calc(100vh - 2rem);
    }
}

@media (max-width: 760px) {
    .fi-modal-window {
        max-height: calc(100dvh - 1rem);
    }

    @supports not (height: 100dvh) {
        .fi-modal-window {
            max-height: calc(100vh - 1rem);
        }
    }
}
'''
if 'html {\n    scrollbar-gutter: stable;' not in dialog_contract:
    dialog_contract_path.write_text(shared_geometry + '\n' + dialog_contract)

media_path = ROOT / 'resources/css/admin/media.css'
media = media_path.read_text()
legacy_import = "@import './dialogs.css';\n\n"
if media.count(legacy_import) != 1:
    raise SystemExit('media.css legacy dialog import changed unexpectedly')
media_delete_rules = '''/* Media-specific delete-dialog content; shared modal geometry/chrome lives in dialog-contract.css. */
.media-delete-dialog {
    display: grid;
    gap: .75rem;
    color: var(--admin-text);
}

.media-delete-dialog > p {
    margin: 0;
    color: var(--admin-muted);
    font-size: .78rem;
    line-height: 1.55;
}

.media-delete-dialog__references {
    display: grid;
    max-height: 15rem;
    overflow-y: auto;
    overscroll-behavior: contain;
    border-block: 1px solid var(--admin-line);
    scrollbar-gutter: stable;
}

.media-delete-dialog__references > div {
    display: grid;
    grid-template-columns: minmax(8rem, .55fr) minmax(0, 1fr);
    gap: .8rem;
    padding: .6rem 0;
    border-bottom: 1px solid var(--admin-line);
}

.media-delete-dialog__references > div:last-child {
    border-bottom: 0;
}

.media-delete-dialog__references strong,
.media-delete-dialog__references span {
    min-width: 0;
    font-size: .73rem;
}

.media-delete-dialog__references strong {
    color: var(--admin-text);
    font-weight: 600;
}

.media-delete-dialog__references span {
    color: var(--admin-muted);
}

@media (max-width: 760px) {
    .media-delete-dialog__references > div {
        grid-template-columns: minmax(0, 1fr);
        gap: .2rem;
    }
}
'''
if '.media-delete-dialog {' in media.replace(legacy_import, '', 1):
    raise SystemExit('media.css already contains delete-dialog rules')
media = media.replace(legacy_import, '', 1).rstrip() + '\n\n' + media_delete_rules
media_path.write_text(media)

# The first direct runner already converted the 18 editCommit call sites. Rebuild
# only the helper's pre-migration shape in the temporary workspace so the stricter
# staged migration can apply its remaining canonicalization and acceptance checks.
helper = helper_path.read_text()
current_edit = '''    /**
     * Edit dialogs use autosave and therefore have no submit/cancel footer.
     * Optional session-level Undo actions are supplied by the caller through
     * Filament's extra modal footer actions and are lifted into the header rail.
     */
    public static function edit(
        Action $action,
        AdminDialogSize $size = AdminDialogSize::Small,
    ): Action {
        return self::base($action, AdminDialogType::Edit, $size)
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }
'''
pre_migration_edit = '''    /**
     * Edit dialogs use autosave and therefore have no submit/cancel footer.
     * Optional session-level Undo actions are supplied by the caller through
     * Filament's extra modal footer actions and are lifted into the header rail.
     */
    public static function edit(
        Action $action,
        AdminDialogSize $size = AdminDialogSize::Small,
    ): Action {
        return self::base($action, AdminDialogType::Edit, $size)
            ->modalSubmitAction(false)
            ->modalCancelAction(false);
    }

    /**
     * Transitional helper for an edit that still has atomic persistence.
     * New edit flows must prefer edit(); this exists so presentation can be
     * centralized before a risky domain workflow is converted to autosave.
     */
    public static function editCommit(
        Action $action,
        string $submitLabel,
        AdminDialogSize $size = AdminDialogSize::Small,
    ): Action {
        return self::committedTask($action, AdminDialogType::Edit, $submitLabel, $size);
    }
'''
current_base = '''        $attributes = ['class' => implode(' ', $classes)];

        if ($type === AdminDialogType::Edit) {
            // Native change events give text/textarea blur commits and immediate
            // select/toggle commits without timer-driven persistence.
            $attributes['wire:change'] = 'persistMountedAdminEdit';
        }

        return $action
            // Filament still owns modal state, focus, Escape and the native X.
            // The shared CSS width modifier is the actual visual authority.
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes($attributes);'''
pre_migration_base = '''        return $action
            // Filament still owns modal state, focus, Escape and the native X.
            // The shared CSS width modifier is the actual visual authority.
            ->modalWidth(Width::Large)
            ->extraModalWindowAttributes(['class' => implode(' ', $classes)]);'''

if helper.count(current_edit) != 1:
    raise SystemExit('AdminDialog current edit block changed unexpectedly')
if helper.count(current_base) != 1:
    raise SystemExit('AdminDialog current base block changed unexpectedly')
helper = helper.replace(current_edit, pre_migration_edit, 1)
helper = helper.replace(current_base, pre_migration_base, 1)
helper_path.write_text(helper)

source = source_path.read_text()
marker = '# 2. Add the single event-driven autosave implementation used by canonical edit dialogs.'
if source.count(marker) != 1:
    raise SystemExit('Staged migration marker changed unexpectedly')
suffix = marker + source.split(marker, 1)[1]

# Remove the now-superseded staged script before its own source-level acceptance
# scan. The finalizer itself is excluded through __file__ in that scan.
source_path.unlink()

converted_total = 18
converted_files = ['already converted by the first direct retirement pass']


def fail(message: str) -> None:
    raise SystemExit(message)


exec(compile(suffix, '<admin-dialog-finalization>', 'exec'), globals())
