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

- `MediaAssetSelect` for reusable Media Files selection;
- `ArtworkMaterialSelect` for material presets and creation;
- `AdminRichText` for rich text plus canonical Media Files insertion;
- `AdminColorControl` for the General appearance color workflow.

Do not create alternative media pickers, rich-text editors, material selectors or color controls for one page.

## Native/Blade adapter

Workspace searches, filters, pager controls and compact inline controls may remain native HTML where Filament state is not useful. They must use the shared admin field classes owned by `resources/css/admin/data-workspace.css`, `resources/css/admin/forms.css` and `resources/css/admin/task-surfaces.css` rather than page-local control styling.

A native control is not a separate design system. It is another renderer of this contract.

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

`wire:model.live.debounce.*`, Filament `->debounce(...)`, `searchDebounce(...)`, `setTimeout`-based persistence and polling-based persistence are forbidden **when they trigger writes**. Changing a timer value does not make timer-driven persistence acceptable.

### Search and remote typeahead

Search is not persistence. The default workspace-search pattern should still avoid timer-driven server reloads when a normal Enter/change/blur interaction gives equivalent usability.

A remote searchable select may use a short debounce solely as **transport throttling** when every keystroke would otherwise issue a server request. This is an explicit exception for query traffic, not for mutation traffic. It must satisfy all of these conditions:

- the callback is read-only;
- selecting or clearing the final value is the only semantic state change;
- the debounce does not save any record or setting;
- removing the throttle would materially increase request fan-out;
- the control remains keyboard accessible through Filament's normal listbox behavior.

`MediaAssetSelect` and remote Hero Artwork search are examples of this typeahead case. Do not replace their throttle with `0`; that merely converts a controlled query into a request per keystroke.

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
