# Admin control contract

This document defines the canonical control grammar for the artist admin. It applies to Filament forms, dialog schemas, workspace filters, table controls and specialized editor controls.

## Core rule

There is one visual control language. A field may be rendered by Filament or by Blade/native HTML, but equivalent controls must share the same geometry, typography, focus treatment, validation treatment and semantic commit behavior.

Do not create page-local input/select/checkbox/toggle styling when the shared control contract fits.

## Filament adapter

Use `App\Filament\Support\Controls\AdminControl` for ordinary fields in admin schemas:

- `AdminControl::text()`
- `AdminControl::email()`
- `AdminControl::url()`
- `AdminControl::number()`
- `AdminControl::textarea()`
- `AdminControl::select()`
- `AdminControl::checkbox()`
- `AdminControl::toggle()`
- `AdminControl::date()`
- `AdminControl::dateTime()`
- `AdminControl::file()`

The factory returns native Filament fields. Filament continues to own state, validation, accessibility and popup behavior. The factory only applies the shared admin wrapper contract.

`AdminControl::register()` is the panel-wide visual authority for ordinary Filament fields. TextInput, Textarea, Select, Checkbox, Toggle, Date/DateTime, FileUpload, MarkdownEditor, ColorPicker and OneTimeCodeInput therefore enter the same wrapper/control grammar even when a framework integration or an existing schema constructs the concrete Filament field directly.

New project-owned schema code should prefer the factory helpers when they express the field clearly because ownership is explicit. Direct core Filament construction is not a second visual language and does not require a page-local styling path; use `AdminControl::decorate()` only for a field type that is outside the globally registered set.

Boolean fields are part of the same grammar: Checkbox/Toggle and their labels share one vertical field axis; framework default top offsets must not create a second alignment system. Required Select fields keep validation semantics but do not allow their neutral placeholder as a selectable state. In an Edit dialog persisted required state is already populated, so the UI suppresses required-star decoration without weakening validation. Optional semantic null states such as a page parent of `Top level` remain available when the domain explicitly supports them.

## Specialized canonical controls

These are intentionally specialized and remain canonical:

- `MediaAssetSelect` for reusable Storage media selection;
- `ArtworkMaterialSelect` for material presets and creation;
- `AdminRichText` for rich text plus canonical Storage media insertion;
- `AdminColorControl` for the General appearance color workflow.

Do not create alternative media pickers, rich-text editors, material selectors or color controls for one page.

## Rich Text and reusable media selection

The single rich-text editor is [`App\Filament\Support\AdminRichText`](../app/Filament/Support/AdminRichText.php), backed by Filament's native `MarkdownEditor`. It uses an explicit **adjacent Insert image control**, not Filament's built-in `attachFiles` upload; `fileAttachments(false)` prevents a second uncontrolled upload pipeline. Embedded canonical Storage images use [`RichTextMediaReference::markdown()`](../app/Domain/Content/RichTextMediaReference.php), serialized for example as:

```markdown
![](media:123)
```

The Insert image UI exposes **two supported choices** under the existing central editor: an available Storage image selected by `MediaAssetSelect`, **or** a valid external HTTP(S) image URL. External image URLs are not arbitrary unsafe schemes: [`SafeRichTextRenderer`](../app/Domain/Content/SafeRichTextRenderer.php) accepts external images only under `SafeLinkPolicy`, which rejects invalid URLs, credentials, control characters and unsupported schemes. The Alpine inserter likewise verifies HTTP(S) and rejects username/password URL components before inserting Markdown. External URLs are not `MediaAsset` IDs and do not confer canonical Storage lifecycle ownership. **Do not inaccurately forbid the supported external editor option**, nor introduce a second parser, upload subsystem or unrelated editor. Editor-valid HTTP(S) is **not** proof of browser loading under strict [Public CSP `img-src`](architecture/public-security-privacy-boundaries.md#4-rich-text-url-validierung-vs-csp-ladefreigabe); the Public/security owner must resolve the rendering policy.

The editor bridge commits on a **semantic blur/change or completed image insertion** in an Edit dialog, never per keystroke or an autosave timer. The canonical AST/parser and Validation layers own safe Rich Text semantics. Asset ALT is authoritative unless the specific consumer actually supports an occurrence-level override; structured Journal Cover/Gallery currently use Storage ALT at runtime.

[`MediaAssetSelect`](../app/Filament/Support/MediaAssetSelect.php) is the single reusable Storage selector. Its `make()` / `makeId()` modes use Filament searchable controls with `getSearchResultsUsing()`, the shared **`AdminControl::SEARCH_DEBOUNCE_MS`**, a query restricted to `state = available`, and consumer-specific `imagesOnly` or allowed-MIME filters. Existing selected IDs are resolved individually for a label; search results are bounded and loaded only on query. **Never `pluck()`/preload the full Storage library** just because a Select supports it. Search is read-only; choosing the final asset is the semantic edit action.

## Native/Blade adapter

Workspace searches, filters, pager controls and compact inline controls may remain native HTML where Filament state is not useful. They must use the shared admin field classes owned by `resources/css/admin/data-workspace.css`, `resources/css/admin/forms.css` and `resources/css/admin/task-surfaces.css` rather than page-local control styling. Table-level direct editors use the shared `admin-inline-select` / `admin-inline-input` geometry; static values aligned with those controls use `admin-inline-control-value`.

A native control is not a separate design system. It is another renderer of this contract.

### Toolbar interaction states

`x-admin.controls` is the only ordinary editorial toolbar presentation owner.
Its hover, focus, open and selected treatment is scoped to
`.admin-data-controls` in `resources/css/admin/controls.css`; it must never
change ordinary table row Actions, Selection popup actions or dialog buttons.

- Search and inline filter input/select controls have one neutral baseline.
  Hover emphasizes that baseline, while focus (including an opened native
  dropdown) uses `--admin-control-border-focus`.
- Toolbar actions (Settings, Add, Preview, Clear, View and the shared Filters
  trigger) use the same **single 1px bottom border** as Search and native
  filter fields: neutral on hover, accented for active View, focused actions
  and expanded Filters. Never simulate a second or thicker underline using
  box shadows, backgrounds or additional pseudo-elements.
- Selection underlines the **whole group**, including Selected, count badge and
  the final table-selection padding/rail, using exactly one 1px pseudo-element.
  Its button must not draw another underline; the open/focus state changes
  color, never thickness.
- Disabled task actions do not gain a hover underline. The active/expanded
  underline is presentation-only; state remains with the existing Livewire or
  Alpine owner. No new click or persistence mechanism is allowed.
- Storage View-specific hover/active fills are obsolete; its native
  `aria-pressed` state participates in the shared toolbar grammar.

Native single-selects and pager selects stay on one native render path. Shared CSS in `resources/css/admin/layouts.css`, `resources/css/admin/data-workspace.css` and `resources/css/admin/task-surfaces.css` owns their trigger, typography and popup theming; Chromium's customizable-select surface is progressive enhancement only. Do not add a JavaScript-generated replacement select, duplicate popup DOM or a second presentation path. Opening a dropdown must never add document height, change the page scrollbar, call window scrolling APIs or reserve layout space for the popup.

## Persistence semantics

Persistence must never be driven by a debounce timer. Read-only search is the one deliberate debounce use-case and is defined separately below. Animation, clocks and browser render scheduling are presentation concerns; they are not persistence timers.

Use discrete semantic commits:

- text / email / URL / numeric input: normal change or blur; Enter may explicitly commit where supported;
- textarea: normal change or blur;
- select / checkbox / toggle: discrete selection/state change;

Filament 5's Toggle renders an Alpine-backed switch button, not a native
input. `AdminControl::register()` gives Toggles a single post-click semantic
`change` event **only inside** `AdminDialog::edit()` so its existing
`wire:change` owner performs the one normal editorial save. This bridge
does not call an Action, save directly, intercept Filament's click, or use
timers. Live entanglement alone is state synchronization, not persistence.
Gallery and Exhibition map switches use the same central control contract.
- date / datetime: completed picker change;
- Media File selection: completed asset selection/removal;
- color: completed color value change, never each intermediate drag sample;
- file upload: successful completed upload/attach;
- reorder: completed move/drop;
- rich text: semantic editor change/blur according to the canonical editor flow, not a timer.

Always normalize and compare with the persisted value before writing. Activity/Audit records successful writes; it is not the persistence trigger.
Nor is it the Notification trigger: after validating a changed persisted
value, the owning admin Action/callback must call the canonical
`AdminNotifier` to deliver the mailbox + immediate header projection.
Keep that call in one owner. Duplicate modal `wire:change` and
`afterStateUpdated` paths must never produce two writes/Notifications.
Test changed, no-op and invalid values plus close/reopen in Livewire.

`wire:model.live.debounce.*`, Filament `->debounce(...)`, `searchDebounce(...)`, `setTimeout`-based persistence and polling-based persistence are forbidden **when they trigger writes**. Changing a timer value does not make timer-driven persistence acceptable.

### Search and remote typeahead

Search is read-only and intentionally live. All ordinary admin workspace search inputs use the shared `x-admin.search-input` primitive with one canonical **300 ms** debounce. The debounce may only update search/filter projection state; it must never submit a normal form, navigate the page, write editorial data or trigger persistence.

Remote searchable Filament selects use the same 300 ms transport throttle through `AdminControl::SEARCH_DEBOUNCE_MS`. Their search callback is read-only; choosing or clearing the final option is the semantic state change.

Rules:

- one canonical 300 ms search debounce, not page-specific values;
- no `requestSubmit()`, browser navigation or full-page GET from a debounced search;
- no debounce on persisted text, numeric, rich-text, color or settings fields;
- text-like persisted controls commit on change/blur; Enter may explicitly commit where supported;
- select/toggle/checkbox/media choices commit on their discrete change;
- Storage measurements refresh from upload completion or an explicit refresh action, never from a timer or polling loop.

`MediaAssetSelect` and Hero Artwork search are the canonical remote-typeahead examples.

## Toolbar geometry contract

Standard search/filter/action/selection rows use `x-admin.controls`. Metric-backed toolbars use its shared six-unit ruler through `metric-grid`; feature CSS must not recreate that geometry with page-local `grid-template-columns`, arbitrary span profiles or a second Selection layout.

Toolbar sizing is always **content minimum + shared ruler**:

1. derive each non-search control's minimum from its longest legitimate visible value/label plus icon, gap, padding and native control affordance;
2. round that minimum **up** to the smallest shared ruler subdivision that fits. The six-unit ruler may be subdivided into 12/24 equivalent tracks when finer placement is required, but the outer ruler remains divisible by both 2 and 3;
3. keep Search at the far left and elastic. When another control genuinely needs more room, Search yields whole ruler subdivisions first;
4. keep filter/action groups compact inside their assigned region. Spare width is not permission to stretch one or two buttons across arbitrary bandwidth;
5. keep Selection terminal at the far right. Its selected-count circle reserves `--admin-table-selection-width` and stays on the exact same horizontal axis as the table select-all and row checkboxes;
6. preserve full dropdown values and action labels whenever their calculated minimum fits. Ellipsis is an emergency fallback, not normal sizing;
7. when full utility labels no longer fit, use the shared complete-label → icon-only/overflow transition before clipping, adding a second toolbar row or introducing horizontal scrolling;
8. toolbar group headings are structural anchors. Query/Filter headings remain visible through Minimal. Task/Selection headings may yield at Narrow together with their action/value labels when doing so creates one stable icon cluster instead of a later collision state;
9. Clear belongs to Query/Filter, not Task actions. Its full label is retained through Minimal when the calculated Query/Filter region can still fit it;
10. overlapping controls or headings are never a supported responsive state. Metric-grid toolbars use the six-cell ruler with 24 quarter-cell subtracks when needed; Search donates subdivisions only at named state boundaries, filters gain space before their legitimate values become unreadable, and intentional empty ruler tracks may separate Query/Filter from a compact task/Selection icon cluster.

Responsive metric presentation does not replace the underlying ruler. A visible three- or two-metric state still uses a refinable ruler from which six-/three-/two-column alignment can be reconstructed.

Before changing a toolbar, verify the longest content, its intrinsic minimum, the ruler subdivision chosen for it and the remaining Search allocation. Do not choose spans by screenshot appearance. If the shared primitive cannot represent the required composition, extend the shared primitive rather than bypassing it locally.

## Dialog ownership

Dialog content uses the same controls as route-backed forms. Dialog width, header actions and lifecycle are owned by `ADMIN-DIALOG-CONTRACT.md`; controls do not implement their own dialog chrome.

## Accessibility and state

The shared contract must preserve:

- native labels and descriptions;
- validation messages;
- required/disabled/read-only state;
- visible keyboard focus;
- popup/listbox keyboard behavior;
- native Filament modal focus trapping and Escape handling.

Do not replace framework semantics merely to match presentation.

## Public Appearance controls (source 434975e1bd09)

Reuse the established native shared Admin color/number controls. `AdminAngleControl` couples a text-editable degree value to an on-demand rotary popover without creating a second palette or overriding canonical dialog/keyboard interactions. `PublicAppearance` owns mode and stop validation for solid, linear, radial and conic paint, including repeating spans and up to twelve stops. General presents them through one compact Color/Position-table role; gray native Add/Remove controls remain canonical. Product/browser approval of the rotary interaction and responsive Stage presentation is independent of source presence.

