from __future__ import annotations

from pathlib import Path
import re
import sys

ROOT = Path(__file__).resolve().parents[1]
NEEDLE = 'AdminDialog::editCommit('
AUTOSAVE_IMPORT = 'use App\\Filament\\Support\\Dialogs\\InteractsWithAdminEditDialogAutosave;\n'
AUTOSAVE_USE = '    use InteractsWithAdminEditDialogAutosave;\n\n'


def fail(message: str) -> None:
    raise SystemExit(message)


def replace_once(path: str, old: str, new: str) -> None:
    target = ROOT / path
    text = target.read_text()
    count = text.count(old)
    if count != 1:
        fail(f'{path}: expected one exact match, found {count}')
    target.write_text(text.replace(old, new, 1))


def add_import(path: str, anchor: str, import_line: str) -> None:
    target = ROOT / path
    text = target.read_text()
    if import_line in text:
        return
    if anchor not in text:
        fail(f'{path}: import anchor not found: {anchor.strip()}')
    target.write_text(text.replace(anchor, anchor + import_line, 1))


def matching_call_end(text: str, open_paren: int) -> int:
    pairs = {')': '(', ']': '[', '}': '{'}
    stack = ['(']
    quote: str | None = None
    escaped = False
    line_comment = False
    block_comment = False
    i = open_paren + 1
    while i < len(text):
        ch = text[i]
        nxt = text[i + 1] if i + 1 < len(text) else ''
        if line_comment:
            if ch == '\n':
                line_comment = False
            i += 1
            continue
        if block_comment:
            if ch == '*' and nxt == '/':
                block_comment = False
                i += 2
            else:
                i += 1
            continue
        if quote is not None:
            if escaped:
                escaped = False
            elif ch == '\\':
                escaped = True
            elif ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            i += 1
            continue
        if ch == '/' and nxt == '/':
            line_comment = True
            i += 2
            continue
        if ch == '/' and nxt == '*':
            block_comment = True
            i += 2
            continue
        if ch in '([{':
            stack.append(ch)
        elif ch in ')]}':
            if not stack or stack[-1] != pairs[ch]:
                fail('Unbalanced delimiter while parsing editCommit')
            stack.pop()
            if not stack:
                return i
        i += 1
    fail('Unterminated editCommit call')
    return -1


def split_top_level_args(source: str) -> list[str]:
    pairs = {')': '(', ']': '[', '}': '{'}
    stack: list[str] = []
    quote: str | None = None
    escaped = False
    line_comment = False
    block_comment = False
    cuts: list[int] = []
    i = 0
    while i < len(source):
        ch = source[i]
        nxt = source[i + 1] if i + 1 < len(source) else ''
        if line_comment:
            if ch == '\n':
                line_comment = False
            i += 1
            continue
        if block_comment:
            if ch == '*' and nxt == '/':
                block_comment = False
                i += 2
            else:
                i += 1
            continue
        if quote is not None:
            if escaped:
                escaped = False
            elif ch == '\\':
                escaped = True
            elif ch == quote:
                quote = None
            i += 1
            continue
        if ch in ("'", '"'):
            quote = ch
            i += 1
            continue
        if ch == '/' and nxt == '/':
            line_comment = True
            i += 2
            continue
        if ch == '/' and nxt == '*':
            block_comment = True
            i += 2
            continue
        if ch in '([{':
            stack.append(ch)
        elif ch in ')]}':
            if not stack or stack[-1] != pairs[ch]:
                fail('Unbalanced editCommit argument')
            stack.pop()
        elif ch == ',' and not stack:
            cuts.append(i)
        i += 1

    args: list[str] = []
    start = 0
    for cut in cuts:
        args.append(source[start:cut].strip())
        start = cut + 1
    args.append(source[start:].strip())
    while args and args[-1] == '':
        args.pop()
    return args


def migrate_edit_commit_calls(text: str) -> tuple[str, int]:
    count = 0
    cursor = 0
    while True:
        start = text.find(NEEDLE, cursor)
        if start < 0:
            return text, count
        open_paren = start + len(NEEDLE) - 1
        end = matching_call_end(text, open_paren)
        args = split_top_level_args(text[open_paren + 1:end])
        if len(args) not in (2, 3):
            fail(f'editCommit expected 2 or 3 arguments, got {len(args)} near offset {start}')
        if not args[1].lstrip().startswith(("'", '"')):
            fail(f'editCommit submit label is not a string literal near offset {start}')
        replacement = f'AdminDialog::edit({args[0]}'
        if len(args) == 3:
            replacement += f', {args[2]}'
        replacement += ')'
        text = text[:start] + replacement + text[end + 1:]
        cursor = start + len(replacement)
        count += 1


def inject_autosave_trait(path: Path, text: str) -> str:
    if AUTOSAVE_IMPORT not in text:
        marker = 'use App\\Filament\\Support\\Dialogs\\AdminDialog;\n'
        if marker not in text:
            fail(f'{path}: missing AdminDialog import')
        text = text.replace(marker, marker + AUTOSAVE_IMPORT, 1)
    if 'use InteractsWithAdminEditDialogAutosave;' not in text:
        declaration = re.search(r'(?m)^(?:(?:final|abstract)\s+)?(?:class|trait)\s+[A-Za-z0-9_]+[^\n]*\n\{\n', text)
        if declaration is None:
            fail(f'{path}: cannot locate class/trait declaration')
        text = text[:declaration.end()] + AUTOSAVE_USE + text[declaration.end():]
    return text


# 1. Convert every transitional editCommit call and attach autosave capability to its host class/trait.
converted_total = 0
converted_files: list[str] = []
for path in sorted((ROOT / 'app' / 'Filament').rglob('*.php')):
    text = path.read_text()
    expected = text.count(NEEDLE)
    if expected == 0:
        continue
    migrated, converted = migrate_edit_commit_calls(text)
    if converted != expected or NEEDLE in migrated:
        fail(f'{path}: unsafe editCommit migration {converted}/{expected}')
    migrated = inject_autosave_trait(path, migrated)
    path.write_text(migrated)
    converted_total += converted
    converted_files.append(str(path.relative_to(ROOT)))

if converted_total != 18:
    fail(f'Expected exactly 18 editCommit flows, found {converted_total}: {converted_files}')

# 2. Add the single event-driven autosave implementation used by canonical edit dialogs.
autosave_path = ROOT / 'app/Filament/Support/Dialogs/InteractsWithAdminEditDialogAutosave.php'
autosave_path.write_text(r'''<?php

namespace App\Filament\Support\Dialogs;

use BackedEnum;
use DateTimeInterface;
use Filament\Actions\Action;
use Filament\Support\Exceptions\Cancel;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use JsonSerializable;
use Livewire\Attributes\Locked;
use Stringable;
use Throwable;

trait InteractsWithAdminEditDialogAutosave
{
    /** @var array<string, string> */
    #[Locked]
    public array $adminEditDialogPersistedFingerprints = [];

    public function persistMountedAdminEdit(): void
    {
        $action = $this->getMountedAction();
        if (! $action instanceof Action) {
            return;
        }

        $schema = $this->getMountedActionSchema(mountedAction: $action);
        if ($schema === null) {
            return;
        }

        $action->beginDatabaseTransaction();

        try {
            $action->callBeforeFormValidated();
            $state = $schema->getState();
            $action->callAfterFormValidated();
            $action->data($state);

            $key = $this->adminEditDialogFingerprintKey($action);
            $fingerprint = $this->adminEditDialogFingerprint($state);
            if (($this->adminEditDialogPersistedFingerprints[$key] ?? null) === $fingerprint) {
                $action->commitDatabaseTransaction();

                return;
            }

            $action->callBefore();
            $action->call(['form' => $schema, 'schema' => $schema]);
            $action->callAfter();
            $this->afterActionCalled($action);
            $action->commitDatabaseTransaction();

            // Only a successfully completed domain action becomes the next
            // no-op baseline. Validation/errors never advance this receipt.
            $this->adminEditDialogPersistedFingerprints[$key] = $fingerprint;
        } catch (Halt $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $action->rollBackDatabaseTransaction()
                : $action->commitDatabaseTransaction();
        } catch (Cancel $exception) {
            $exception->shouldRollbackDatabaseTransaction()
                ? $action->rollBackDatabaseTransaction()
                : $action->commitDatabaseTransaction();
        } catch (ValidationException $exception) {
            $action->rollBackDatabaseTransaction();

            throw $exception;
        } catch (Throwable $exception) {
            $action->rollBackDatabaseTransaction();

            throw $exception;
        }
    }

    private function adminEditDialogFingerprintKey(Action $action): string
    {
        $record = method_exists($action, 'getRecord') ? $action->getRecord() : null;
        $recordIdentity = $record instanceof Model
            ? $record::class.':'.(string) $record->getKey()
            : 'none';

        return $action->getName().':'.$recordIdentity.':'.$this->adminEditDialogFingerprint($action->getArguments());
    }

    private function adminEditDialogFingerprint(mixed $value): string
    {
        return hash('sha256', serialize($this->normalizeAdminEditDialogValue($value)));
    }

    private function normalizeAdminEditDialogValue(mixed $value): mixed
    {
        if ($value instanceof BackedEnum) {
            return $value->value;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if ($value instanceof Model) {
            return [$value::class, $value->getKey()];
        }

        if ($value instanceof JsonSerializable) {
            return $this->normalizeAdminEditDialogValue($value->jsonSerialize());
        }

        if ($value instanceof Stringable) {
            return (string) $value;
        }

        if (is_array($value)) {
            if (! array_is_list($value)) {
                ksort($value);
            }

            return array_map(fn (mixed $item): mixed => $this->normalizeAdminEditDialogValue($item), $value);
        }

        if (is_object($value)) {
            return [$value::class, spl_object_id($value)];
        }

        return $value;
    }
}
''')

# 3. Make AdminDialog::edit the one canonical edit shell and delete the bridge.
helper = ROOT / 'app/Filament/Support/Dialogs/AdminDialog.php'
text = helper.read_text()
old_edit = '''    /**
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
new_edit = '''    /**
     * Edit dialogs autosave on native change events: text controls commit on
     * blur, while select/toggle controls commit immediately. No timers or
     * hidden submit buttons participate in persistence.
     *
     * @param  array<mixed>|Closure  $windowAttributes
     */
    public static function edit(
        Action $action,
        AdminDialogSize $size = AdminDialogSize::Small,
        array|Closure $windowAttributes = [],
    ): Action {
        $action = self::base($action, AdminDialogType::Edit, $size)
            ->modalSubmitAction(false)
            ->modalCancelAction(false)
            ->extraModalWindowAttributes(['wire:change' => 'persistMountedAdminEdit'], merge: true);

        if ($windowAttributes instanceof Closure || $windowAttributes !== []) {
            $action->extraModalWindowAttributes($windowAttributes, merge: true);
        }

        return $action;
    }
'''
if text.count(old_edit) != 1:
    fail('AdminDialog edit/editCommit block changed unexpectedly')
helper.write_text(text.replace(old_edit, new_edit, 1))

# 4. Activity read-only dialogs -> canonical viewer.
activity_path = ROOT / 'app/Filament/Pages/Activity.php'
activity = activity_path.read_text()
activity = activity.replace(
    'use App\\Filament\\Support\\AdminIcon;\n',
    'use App\\Filament\\Support\\AdminIcon;\nuse App\\Filament\\Support\\Dialogs\\AdminDialog;\nuse App\\Filament\\Support\\Dialogs\\AdminDialogSize;\n',
    1,
)
for name in ('activityDetails', 'publicationReview', 'commitDetails'):
    marker = f"        return Action::make('{name}')"
    if activity.count(marker) != 1:
        fail(f'Activity: expected one {name} action')
    activity = activity.replace(marker, f"        return AdminDialog::viewer(\n            Action::make('{name}')", 1)

# Strip the legacy viewer-owned modal chrome. Keep custom content/header actions.
for legacy in (
    '            ->modalSubmitAction(false)\n',
    '            ->modalCancelAction(false)\n',
    '            ->modalWidth(Width::Large)\n',
):
    activity = activity.replace(legacy, '')
activity, removed_attrs = re.subn(
    r"\n            ->extraModalWindowAttributes\(\[\n                'class' => 'admin-task-dialog admin-dialog--default admin-dialog--header-actions',\n            \]\)",
    '',
    activity,
)
if removed_attrs != 3:
    fail(f'Activity: expected 3 legacy window-attribute blocks, removed {removed_attrs}')
# Each viewer action now ends at the first fluent semicolon after its method marker.
for name in ('activityDetails', 'publicationReview', 'commitDetails'):
    start = activity.index(f"return AdminDialog::viewer(\n            Action::make('{name}')")
    end = activity.index(';', start)
    expr = activity[start:end]
    if expr.rstrip().endswith(')'):
        pass
    activity = activity[:end] + ',\n            AdminDialogSize::Default,\n        )' + activity[end:]
if 'Width::' not in activity:
    activity = activity.replace('use Filament\\Support\\Enums\\Width;\n', '')
activity_path.write_text(activity)

# 5. Pages placement edit -> canonical autosaving edit.
site_path = ROOT / 'app/Filament/Pages/SitePages.php'
site = site_path.read_text()
site = site.replace(
    'use App\\Filament\\Support\\AdminIcon;\n',
    'use App\\Filament\\Support\\AdminIcon;\nuse App\\Filament\\Support\\Dialogs\\AdminDialog;\nuse App\\Filament\\Support\\Dialogs\\AdminDialogSize;\n',
    1,
)
old = '''        return Action::make('editPlacement')
            ->label('Edit')
            ->modalHeading('Edit page placement')
            ->modalDescription('Choose whether this page is top level or belongs under another top-level page.')
            ->fillForm(function (array $arguments): array {
                /** @var SiteSection $section */
                $section = SiteSection::query()->findOrFail((int) ($arguments['section'] ?? 0));

                return [
                    'parent_id' => $section->getAttribute('parent_id'),
                ];
            })
            ->schema([
                Select::make('parent_id')
                    ->label('Parent page')
                    ->options(fn (): array => $this->parentOptions)
                    ->placeholder('Top level')
                    ->native()
                    ->nullable(),
            ])
            ->modalSubmitActionLabel('Save')
            ->action(function (array $data, array $arguments): void {
                /** @var SiteSection $section */
                $section = SiteSection::query()->findOrFail((int) ($arguments['section'] ?? 0));
                if ($section->nodeType() === SiteNodeType::Home) {
                    return;
                }

                $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null
                    ? (int) $data['parent_id']
                    : null;

                try {
                    app(SiteSectionEditorialService::class)->updatePlacement(
                        $section,
                        (string) $section->getAttribute('state'),
                        (bool) $section->getAttribute('show_in_navigation'),
                        $parentId,
                    );
                    Notification::make()->title('Page placement updated')->success()->send();
                } catch (ValidationException $exception) {
                    $this->validationNotification('Page placement unchanged', $exception);
                }

                $this->loadSections();
            });'''
new = '''        return AdminDialog::edit(
            Action::make('editPlacement')
                ->label('Edit')
                ->modalHeading('Edit page placement')
                ->modalDescription('Choose whether this page is top level or belongs under another top-level page.')
                ->fillForm(function (array $arguments): array {
                    /** @var SiteSection $section */
                    $section = SiteSection::query()->findOrFail((int) ($arguments['section'] ?? 0));

                    return [
                        'parent_id' => $section->getAttribute('parent_id'),
                    ];
                })
                ->schema([
                    Select::make('parent_id')
                        ->label('Parent page')
                        ->options(fn (): array => $this->parentOptions)
                        ->placeholder('Top level')
                        ->native()
                        ->nullable(),
                ])
                ->action(function (array $data, array $arguments): void {
                    /** @var SiteSection $section */
                    $section = SiteSection::query()->findOrFail((int) ($arguments['section'] ?? 0));
                    if ($section->nodeType() === SiteNodeType::Home) {
                        return;
                    }

                    $parentId = isset($data['parent_id']) && $data['parent_id'] !== '' && $data['parent_id'] !== null
                        ? (int) $data['parent_id']
                        : null;

                    try {
                        app(SiteSectionEditorialService::class)->updatePlacement(
                            $section,
                            (string) $section->getAttribute('state'),
                            (bool) $section->getAttribute('show_in_navigation'),
                            $parentId,
                        );
                        Notification::make()->title('Page placement updated')->success()->send();
                    } catch (ValidationException $exception) {
                        $this->validationNotification('Page placement unchanged', $exception);
                    }

                    $this->loadSections();
                }),
            AdminDialogSize::Default,
        );'''
if site.count(old) != 1:
    fail('SitePages: editPlacement block changed unexpectedly')
site_path.write_text(site.replace(old, new, 1))

# 6. Skip Home is an explicit routing command, not an edit/save-footer special case.
home_concern_path = ROOT / 'app/Filament/Pages/Concerns/ManagesHomePagePresentation.php'
home_concern = home_concern_path.read_text()
home_concern = home_concern.replace(
    'use App\\Filament\\Support\\AdminIcon;\n',
    'use App\\Filament\\Support\\AdminIcon;\nuse App\\Filament\\Support\\Dialogs\\AdminDialog;\nuse App\\Filament\\Support\\Dialogs\\AdminDialogSize;\n',
    1,
)
old = '''        return Action::make('skipHome')
            ->label('Skip Home')
            ->icon(AdminIcon::SkipHome->value)
            ->modalHeading('Skip Home')
            ->modalDescription('Choose whether Home redirects and where it goes. Without an explicit target, the next eligible page in the current order is used.')
            ->fillForm(fn (): array => $dialog->fill())
            ->schema($dialog->schema())
            ->action(function (array $data) use ($dialog): void {
                $dialog->save($data);
                $this->loadSections();
                Notification::make()->title('Home routing updated')->success()->send();
            })
            ->modalSubmitAction(fn (Action $action): Action => $action
                ->label('Save Skip Home')
                ->icon(AdminIcon::Commit->value)
                ->iconButton()
                ->extraAttributes(['class' => 'admin-dialog__header-action is-primary']))
            ->modalCancelAction(false)
            ->modalWidth(Width::Medium)
            ->extraModalWindowAttributes([
                'class' => 'admin-task-dialog admin-dialog--small admin-dialog--header-actions',
            ]);'''
new = '''        return AdminDialog::command(
            Action::make('skipHome')
                ->label('Skip Home')
                ->icon(AdminIcon::SkipHome->value)
                ->modalHeading('Skip Home')
                ->modalDescription('Choose whether Home redirects and where it goes. Without an explicit target, the next eligible page in the current order is used.')
                ->fillForm(fn (): array => $dialog->fill())
                ->schema($dialog->schema())
                ->action(function (array $data) use ($dialog): void {
                    $dialog->save($data);
                    $this->loadSections();
                    Notification::make()->title('Home routing updated')->success()->send();
                }),
            'Save Skip Home',
            AdminDialogSize::Small,
        );'''
if home_concern.count(old) != 1:
    fail('ManagesHomePagePresentation: skipHome block changed unexpectedly')
home_concern = home_concern.replace(old, new, 1)
if 'Width::' not in home_concern:
    home_concern = home_concern.replace('use Filament\\Support\\Enums\\Width;\n', '')
home_concern_path.write_text(home_concern)

# 7. Artwork gallery relation actions -> create / command / confirm primitives.
relation_path = ROOT / 'app/Filament/Resources/Artworks/RelationManagers/GalleryImagesRelationManager.php'
relation = relation_path.read_text()
relation = relation.replace(
    'use App\\Filament\\Support\\AdminIcon;\n',
    'use App\\Filament\\Support\\AdminIcon;\nuse App\\Filament\\Support\\Dialogs\\AdminDialog;\nuse App\\Filament\\Support\\Dialogs\\AdminDialogSize;\n',
    1,
)
old_upload = '''                Action::make('uploadImage')
                    ->label('Upload image')
                    ->icon(AdminIcon::Upload)
                    ->schema([
                        FileUpload::make('upload')
                            ->label('Image')
                            ->image()
                            ->storeFiles(false)
                            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                            ->maxSize((int) ceil(MediaTypePolicy::imageMaxBytes() / 1024))
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->ingestAdditionalMedia($artwork, $data['upload']);
                    }),'''
new_upload = '''                AdminDialog::create(
                    Action::make('uploadImage')
                        ->label('Upload image')
                        ->icon(AdminIcon::Upload)
                        ->schema([
                            FileUpload::make('upload')
                                ->label('Image')
                                ->image()
                                ->storeFiles(false)
                                ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
                                ->maxSize((int) ceil(MediaTypePolicy::imageMaxBytes() / 1024))
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            /** @var Artwork $artwork */
                            $artwork = $this->getOwnerRecord();
                            app(ArtworkEditorialService::class)->ingestAdditionalMedia($artwork, $data['upload']);
                        }),
                    'Upload image',
                    AdminDialogSize::Default,
                ),'''
old_library = '''                Action::make('addFromLibrary')
                    ->label('Add from library')
                    ->icon(AdminIcon::AddFromLibrary)
                    ->schema([
                        Select::make('media_asset_id')
                            ->label('Available media')
                            ->options(fn (): array => $this->availableMediaOptions())
                            ->searchable()
                            ->required(),
                    ])
                    ->action(function (array $data): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        /** @var MediaAsset $asset */
                        $asset = MediaAsset::query()->findOrFail((int) $data['media_asset_id']);
                        app(ArtworkEditorialService::class)->attachAdditionalMedia($artwork, $asset);
                    }),'''
new_library = '''                AdminDialog::command(
                    Action::make('addFromLibrary')
                        ->label('Add from library')
                        ->icon(AdminIcon::AddFromLibrary)
                        ->schema([
                            Select::make('media_asset_id')
                                ->label('Available media')
                                ->options(fn (): array => $this->availableMediaOptions())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (array $data): void {
                            /** @var Artwork $artwork */
                            $artwork = $this->getOwnerRecord();
                            /** @var MediaAsset $asset */
                            $asset = MediaAsset::query()->findOrFail((int) $data['media_asset_id']);
                            app(ArtworkEditorialService::class)->attachAdditionalMedia($artwork, $asset);
                        }),
                    'Add to gallery',
                    AdminDialogSize::Default,
                ),'''
old_detach = '''                Action::make('detach')
                    ->label('Detach')
                    ->icon(AdminIcon::Detach)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('Remove this image from the artwork gallery. The media asset stays in the library and is not deleted.')
                    ->action(function (ArtworkMedia $record): void {
                        /** @var Artwork $artwork */
                        $artwork = $this->getOwnerRecord();
                        app(ArtworkEditorialService::class)->detachAdditionalMedia($artwork, $record);
                    }),'''
new_detach = '''                AdminDialog::confirm(
                    Action::make('detach')
                        ->label('Detach')
                        ->icon(AdminIcon::Detach)
                        ->color('danger')
                        ->action(function (ArtworkMedia $record): void {
                            /** @var Artwork $artwork */
                            $artwork = $this->getOwnerRecord();
                            app(ArtworkEditorialService::class)->detachAdditionalMedia($artwork, $record);
                        }),
                    heading: 'Detach image',
                    description: 'Remove this image from the artwork gallery. The media asset stays in the library and is not deleted.',
                    submitLabel: 'Detach',
                    danger: true,
                    size: AdminDialogSize::Mini,
                    icon: AdminIcon::Detach,
                ),'''
for label, old_block, new_block in (
    ('uploadImage', old_upload, new_upload),
    ('addFromLibrary', old_library, new_library),
    ('detach', old_detach, new_detach),
):
    if relation.count(old_block) != 1:
        fail(f'GalleryImagesRelationManager: {label} block changed unexpectedly')
    relation = relation.replace(old_block, new_block, 1)
relation_path.write_text(relation)

# 8. Retire stale PublicationCommitDialog naming: this component is a state/event bridge, not a dialog.
renames = {
    'app/Livewire/Admin/PublicationCommitDialog.php': 'app/Livewire/Admin/PublicationStateBridge.php',
    'resources/views/livewire/admin/publication-commit-dialog.blade.php': 'resources/views/livewire/admin/publication-state-bridge.blade.php',
    'resources/views/filament/partials/publication-commit-dialog.blade.php': 'resources/views/filament/partials/publication-state-bridge.blade.php',
}
for source, destination in renames.items():
    src = ROOT / source
    dst = ROOT / destination
    if not src.exists() or dst.exists():
        fail(f'Cannot safely rename {source} -> {destination}')
    src.rename(dst)

for base in ('app', 'resources', 'tests', 'docs'):
    root = ROOT / base
    if not root.exists():
        continue
    for path in root.rglob('*'):
        if not path.is_file() or path.suffix not in {'.php', '.md', '.js'}:
            continue
        try:
            text = path.read_text()
        except UnicodeDecodeError:
            continue
        updated = text.replace('PublicationCommitDialog', 'PublicationStateBridge')
        updated = updated.replace('publication-commit-dialog', 'publication-state-bridge')
        if updated != text:
            path.write_text(updated)

# 9. Delete the orphaned pre-contract stylesheet only when no source references it.
legacy_css = ROOT / 'resources/css/admin/dialogs.css'
if legacy_css.exists():
    references: list[str] = []
    for base in ('app', 'resources', 'vite.config.js'):
        candidate = ROOT / base
        paths = [candidate] if candidate.is_file() else list(candidate.rglob('*'))
        for path in paths:
            if not path.is_file() or path == legacy_css:
                continue
            try:
                text = path.read_text()
            except UnicodeDecodeError:
                continue
            if 'dialogs.css' in text:
                references.append(str(path.relative_to(ROOT)))
    if references:
        fail(f'Refusing to delete resources/css/admin/dialogs.css; references remain: {references}')
    legacy_css.unlink()

# 10. Contract update: the migration bridge no longer exists.
contract = ROOT / 'docs/ADMIN-DIALOG-CONTRACT.md'
contract_text = contract.read_text()
bridge = "\nThe framework helper `AdminDialog::editCommit()` exists only as a migration bridge for existing atomic edit workflows whose domain semantics cannot safely be converted in the same source pass. It must not be used for new dialogs and must be removed from each flow once that flow has canonical autosave/receipt coverage.\n"
if bridge in contract_text:
    contract.write_text(contract_text.replace(bridge, '\n', 1))
elif 'editCommit' in contract_text:
    fail('ADMIN-DIALOG-CONTRACT contains an unexpected editCommit reference')

# 11. Source-level acceptance: no transitional/manual modal mechanics survive in admin PHP.
violations: list[str] = []
for path in sorted((ROOT / 'app' / 'Filament').rglob('*.php')):
    text = path.read_text()
    relative = str(path.relative_to(ROOT))
    if relative == 'app/Filament/Support/Dialogs/AdminDialog.php':
        continue
    for token in (
        'AdminDialog::editCommit',
        '->requiresConfirmation(',
        '->modalWidth(',
        '->modalSubmitAction(',
        '->modalCancelAction(',
        '->modalSubmitActionLabel(',
        '->modalCancelActionLabel(',
        '->extraModalWindowAttributes(',
    ):
        if token in text:
            violations.append(f'{relative}: {token}')

for path in ROOT.rglob('*'):
    if not path.is_file() or '.git' in path.parts or path == Path(__file__):
        continue
    try:
        text = path.read_text()
    except UnicodeDecodeError:
        continue
    if 'PublicationCommitDialog' in text or 'publication-commit-dialog' in text:
        violations.append(f'{path.relative_to(ROOT)}: stale PublicationCommitDialog naming')
    if 'AdminDialog::editCommit' in text:
        violations.append(f'{path.relative_to(ROOT)}: stale editCommit reference')

if violations:
    fail('Dialog migration acceptance failed:\n' + '\n'.join(violations))

print(f'Converted {converted_total} editCommit flows across {len(converted_files)} files.')
print('Manual viewer/create/command/confirm migrations completed.')
print('Publication state bridge renamed and orphan dialog CSS removed.')
