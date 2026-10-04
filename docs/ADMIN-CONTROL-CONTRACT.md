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

Raw core Filament fields are allowed only when a field cannot be represented by the factory or when a framework integration requires the concrete construction point. In that case call `AdminControl::decorate()` on the field so it still participates in the shared contract.

The admin panel also registers the adapter globally. This protects existing schemas while they are migrated and prevents an ordinary raw Filament field from silently falling back to a second visual language. New code should still prefer the factory because it makes ownership explicit.

## Specialized canonical controls

These are intentionally specialized and remain canonical:

- `MediaAssetSelect` for reusable Storage media selection;
- `ArtworkMaterialSelect` for material presets and creation;
- `AdminRichText` for rich text plus canonical Storage media insertion;
- `AdminColorControl` for the General appearance color workflow.

Do not create alternative media pickers, rich-text editors, material selectors or color controls for one page.

## Native/Blade adapter

Workspace searches, filters, pager controls and compact inline controls may remain native HTML where Filament state is not useful. They must use the shared admin field classes owned by `resources/css/admin/data-workspace.css`, `resources/css/admin/forms.css` and `resources/css/admin/task-surfaces.css` rather than page-local control styling.

A native control is not a separate design system. It is another renderer of this contract.

Native single-selects and pager selects use the shared `admin-selects.js` popup layer. Opening a dropdown must never add document height, change the page scrollbar, call window scrolling APIs or reserve layout space for the popup. The popup stays viewport-positioned and constrains overflow to its own internal scrollbar.

## Persistence semantics

Persistence must never be driven by a debounce timer. Read-only search is the one deliberate debounce use-case and is defined separately below. Animation, clocks and browser render scheduling are presentation concerns; they are not persistence timers.

Use discrete semantic commits:

- text / email / URL / numeric input: normal change or blur; Enter may explicitly commit where supported;
- textarea: normal change or blur;
- select / checkbox / toggle: discrete selection/state change;
- date / datetime: completed picker change;
- Media File selection: completed asset selection/removal;
- color: completed color value change, never each intermediate drag sample;
- file upload: successful completed upload/attach;
- reorder: completed move/drop;
- rich text: semantic editor change/blur according to the canonical editor flow, not a timer.

Always normalize and compare with the persisted value before writing. Activity/Audit records successful writes; it is not the persistence trigger.

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
8. toolbar group headings are structural anchors. Query/Filter headings remain visible through Minimal; task/Selection headings may yield only at Minimal after their controls have already compacted to icons;
9. Clear belongs to Query/Filter, not Task actions. Its full label is retained through Minimal unless the page-specific contract explicitly removes the whole filter/reset role;
10. overlapping controls or headings are never a supported responsive state. Metric-grid toolbars use the six-cell ruler with 24 quarter-cell subtracks when needed; Search donates subdivisions only at the four named states, and filters are never continuously squeezed below their readable minimum.

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
