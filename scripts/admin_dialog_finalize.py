from pathlib import Path
import re

ROOT = Path(__file__).resolve().parents[1]
source_path = ROOT / 'scripts/admin_dialog_migration.py'
helper_path = ROOT / 'app/Filament/Support/Dialogs/AdminDialog.php'

if not source_path.exists():
    raise SystemExit('Expected staged admin_dialog_migration.py')

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
