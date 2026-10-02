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

Persistence must never be driven by a debounce timer.

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

Timer-driven interaction is not allowed in authored application code. Do not use Livewire/Alpine debounce modifiers, Filament debounce configuration, `setTimeout`, `setInterval`, `requestAnimationFrame`, polling, or equivalent delayed/batched scheduling for persistence, search, previews, overlays, upload feedback, menu behavior or visual refresh. Use the discrete event that owns the state change, observers for structural changes, and direct refresh from that event.

### Search and typeahead

Workspace search commits on Enter/change/blur. It must not submit or reload on every keystroke and must not use a debounce timer. Searchable framework controls must not add project-level debounce configuration; if a remote picker cannot operate acceptably without timer-based request throttling, redesign that picker around explicit user-triggered search instead of adding a timer exception.

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
