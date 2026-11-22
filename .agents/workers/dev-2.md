# Dev Worker 2 — Custom Pages

- **Working branch:** `dev`
- **Lease:** 39
- **State:** Active — replacement chat registered and current Custom Pages source orientation completed. Previous source integration is retained as recorded below; database migration, runtime and browser acceptance remain outstanding.
- **Scope:** Custom Page content editor, canonical structured layout, Public Custom Page renderer. Coordinate shared Stage/preview with Dev Worker 1; Pages, navigation and protected Home identity with Dev Worker 5.
- **Recent source integration:** through `b5ce45962b0c001864833d3709844e09a7453975`, with final source/readback reconciliation still required on fresh `dev` before test handoff.

## User decisions
- **Admin copy preference (2026-11-21):** Never use the middle dot/interpunct as a separator. Choose a better layout or ordinary grammatical punctuation instead of swapping in a different decorative glyph. Rule is maintained in branch-local `AGENTS.md` and `docs/admin/ui.md`. The Custom Page Grid Stage and area select labels were reconciled in the same scoped change. Pages Stage/menu editor is a separately owned future discussion with Dev Worker 5; do not implement it from this request.
- Ordinary lists and CV data use the shared Rich Text editor, not a separate List component.
- Page-template capabilities should eventually be embedded as **page-local configurations/content** inside Custom Pages, not as implicit references to other Pages or hidden Gallery subpages.
- Layout uses one `CustomPageSetting.blocks` canonical document with responsive row-major Grids/areas, shared content components, central public renderer and Stage preview. No parallel layouts datastore.
- Decorative media and separate Divider should not be separate layout composition mechanisms.
- An optional, understated Public footer supporting Contact and Legal remains a **global Public follow-up**, not an implicit Custom Pages-only component.

## Completed implementation on dev
- `ed78ebd3`, `637c3eec`: Remove obsolete `PublicContentSetting::navigationNestingEnabled()` from Custom Page Settings, save and Navigation parent options; use `SiteSection::canContainSitePages()`.
- `a4ac971b`: Split Image and Video editor/component types while sharing canonical Media storage, validation, public rendering, cleanup, media references, and checkpoint conversion. Migration: `2026_11_21_140000_split_custom_page_image_video_types.php`.
- `637eebf3`: Remove standalone List from canonical runtime, editor, media references and Public renderer; delete obsolete `ComponentListEditor`. **The user confirmed no standalone List entries exist, so no separate List data-conversion path is required or retained.**
- `d78fd526`: Establish Grid structure and area placement in the single content document, with stable Grid/area IDs; one to four columns, one to twelve rows, responsive Public CSS, column/row editing, Stage area/member actions and per-area separators. Maintain the shared Stage and existing Public renderer, not an independent preview or page hierarchy.
- `7b81fd1f`: Convert decorative Image/Video child trees to nested local Grids with their original media and authored content. Retire the old nested Image-specific editor, sorting, serialization, removal and Public float; migrate Divider components into layout border structures. Keep working/committed publication and historical checkpoint restore conversion. Migration: `2026_11_21_160000_canonicalize_custom_page_layouts.php`.
- `3856bb65`: Preserve recursively projected nested Grid content across the shared Blade renderer's recursive includes.
- `d0bacb73`: Remove residual Divider editor/conversion options and fix the invalid `editingType` named argument in the canonical Edit action.
- `8be7ef17`: Protect occupied Grids from accidental deletion; keep the same predicate across domain, projection, hydration and Admin actions. Delete the obsolete speculative List translator.
- `03a0e67d`: Make multi-selection Delete match the domain's atomic deletion set: a Grid and all its selected unpublished children can be removed together, but a Grid retaining content remains protected.
- `7c6536a1`: Remove duplicate Grid-area member projection; keep centrally managed Legal Disclaimer read-only in the Stage.
- `b5ce4596`: Preserve `layout_area_id` and `layout_divider_before` through component type conversion, including nested Grids; retire the media-child-only conversion flag.
- Remove the defunct `variant` default from the Custom Page Add-component form after standalone Divider retirement.
- The reusable Grid supports optional equal/65%-35% two-column layouts and row-major stacking in narrow containers; the existing Stage exposes content editing and placement into addressable areas.

- **2026-11-21 UI consolidation:** Removed the Stage `Activate` button, its single-selection eligibility variable and `activateSelectedComponent()` Livewire handler. The button only opened the same canonical Edit action already offered by the component rows and Grid areas. It did not change publication or activation state. All direct Edit controls remain; no alternative action or extra interaction owner was added. Source-only verification; browser review still pending.

## Architecture and data compatibility
- Source owner: `CustomPageSetting` for schema and publication checks, `CustomPageEditorialService` for audited mutation, shared `component-sequence.blade.php` for Public and Stage presentation.
- Grid content stays as individually authored source blocks with `layout_area_id`, never cloned into Grid `areas[]` at persistence. `layoutProjection()` supplies nested render-time children only.
- `HistoricalCustomPageBlockCanonicalizer` is used **only at migration/restore boundaries**, with deterministic historical Grid/area IDs. Retired Image children and Divider records are not accepted as active editable content.
- Actual decorative media Child and Divider payloads have a deliberate data conversion; no standalone List instances exist per user. Both `public` and `committed` schemas have migration steps, and historical checkpoints are translated on restore.
- Grid placement cycles and missing areas are validation errors. The existing positional component action targets remain; stable persisted identifiers currently apply to Grid and areas, not all content components.

## Verification / acceptance
- GitHub commit/ref updates and current file readback verified in source.
- Targeted source bracket-structure scans of 14 PHP files and matching Blade conditional/loop counts completed. These are not `php -l`, a runtime test, or proof of correctness.
- **Not executed:** database migrations on the persistent Windows development checkout, PHP unit/integration tests, browser review, validation deploy, Continuous Integration or server changes. The repository source is available through the connected GitHub tools; the Windows checkout and its application/database runtime are not mounted in this worker environment.
- **Known Testing-branch follow-up:** `tests/Unit/Content/HistoricalCustomPageBlockCanonicalizerTest.php` still invokes removed List API; `tests/Integration/Content/CustomPageComponentsTest.php` still models retired List/Divider/Image-child behavior. Testing owns their redesign, not an application-code compatibility shim. Both are source-inspected, not executed.
- **Source contracts reviewed:** stage members match projected editable types; single and bulk Grid delete availability matches canonical remaining-member validation; type conversions preserve area and separator ownership; old List translation and List migration are absent. These source checks are not browser acceptance.
- **Testing acceptance required:** migration against representative Working and Committed content, legacy checkpoint restore, multi-level Grid Public rendering, responsive 1/2/3/4-column variations, Stage add/edit/reorder/selection, Media deletion and publication, Settings dialog, Image/Video MIME validation.

## User correction — no old standalone List records
- Do not inspect, model, preserve, or invent old standalone List records as a feature dependency. All authored CV/list content already uses the central Rich Text editor. The speculative 2026-11-21 List migration and historical List translation are retired without creating another replacement component.

## Future work not silently included in this feature slice
- Embed real Gallery/Hero/Journal/Exhibitions presentation template instances as local content configurations, without creating hidden SiteSection records or referencing unrelated Pages.
- A global optional understated footer with Contact/Legal variants.
- Upgrade content action targeting from positional indexes to stable component identity after dedicated concurrency design and acceptance.

## Current source orientation (2026-11-21)
- Admin entrypoint: `app/Filament/Pages/CustomPageWorkspace.php`, with actions, forms, projection and Settings behavior in `app/Filament/Pages/Concerns/CustomPageWorkspace*.php`.
- Canonical content/document owner: `app/Models/CustomPageSetting.php`; audited mutation owner: `app/Domain/Content/CustomPageEditorialService.php`.
- Visible Stage: `resources/views/filament/pages/partials/custom-page-layout-stage.blade.php`; page editor and Preview: `resources/views/filament/pages/custom-page-workspace.blade.php`.
- Public consumer: `app/Http/Controllers/PublicSiteSectionController.php`, `resources/views/pages/custom.blade.php` and recursive `resources/views/pages/partials/component-sequence.blade.php`.
- Current source exposes distinct Image/Video component types, Grid areas and a Settings form without a nesting-enabled switch. The Stage has direct Edit actions, not an Activate button.
- This was a bounded remote source review only. No application edits, runtime tests, migrations, browser inspection or deployment were performed in this replacement chat.

## Stage redesign and cross-owned Pages integration (2026-11-22)
- **User authorization:** Implement a direct, three-mode Custom Page Stage and a similar Pages Stage. The middle modes must be interactive spatial/menu structures, not another rendering of their already sortable tables. No dialogs inside the Stage. Consistently replace the old author-facing Grid component with Layout across its model, forms, Public renderer, Stage and existing data.
- **Source commits:** `87a518747b3161c4511227a1a78fe4c6126b5f1e` (Layout canonical type + forward-only migration in public and committed), `f6c2d708ea184e7be2a68f29fded0187b95753f6` (Custom Create/Layout/Settings canvas, Pages Create/Navigation/Settings, native Stage drag and placement, selectable Layout column in existing Custom component table), `b02011f2cb2b150ae681e897c9e3508655a07b5a` (non-destructive coordinate-aware layout resize, canonical Pages Stage focus independent of filtered table, inline quick text), `99f140f113826e5927e7232405e61105b96bf12c` (shared Home/Custom component conversion), `f08eb97a730b15bb7eebeaa918f1ab20a8305ea9` (direct component/area boundary controls).
- **Ownership:** Dev-2 made the narrowly user-authorized Pages Stage edits in `SitePages` and its Blade view. Dev-5 remains the owner of Pages hierarchy, types/templates and Page settings; no changes to the Dev-5 worker record. Shared Stage styles are maintained at the existing CSS owner; public Custom layout remains within the existing Public component sequence.
- **Canonical contract:** Layout is a content block (`type='layout'`) supporting aligned rows and independently growing columns; standalone full-width components and multiple sequential/nested Layouts remain valid. Resizing remaps occupied/removed layout areas in the transactional audited mutation rather than losing content. Public renderer uses the same projection as the visual Stage. The table retains detailed Edit dialogs and now exposes direct Layout-area assignment. Pages' Stage reuses the canonical `SiteSectionOrderService` and existing Page creation/settings services, without any parallel menu model.
- **Incomplete and intentionally not claimed:** No automatic arbitrary cross-area text reflow, drag-resize handles or universal visual content editor; quick media components are created unpublished and edited through the existing detailed table; neither Stage has browser-rendered acceptance. Existing manual/legacy layout separator data remains retained rather than destroyed. Stable non-positional identities for all content components remain a separate concurrency improvement.
- **Verification:** Latest source readback: 15 direct model/editor/renderer files and Blade conditional/loop pair counts checked; no obsolete active Grid type in the checked Custom Page source. No PHP runtime, data migration, unit/integration, browser test, CI or deployment was executed. Testing on `testing` owns test changes and migration rehearsal.

## Next
- Validate the authorized Custom Page/Pages Stage source changes with scoped migration and browser acceptance in an explicitly approved runtime; coordinate the Pages Stage handoff with Dev-5.
- Before every future change, reread current `dev` HEAD, roster lease and all overlapping owners; reconcile current source before writing.
