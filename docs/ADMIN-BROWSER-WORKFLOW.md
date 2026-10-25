# Admin browser workflow and shared-stage contract

This is the direct-`dev` implementation/browser workflow. Documentation-only verification on `docs` is a separate source-audit process; the artist's Validation data must never be reset or seeded for convenience.

This document records the durable working rules that emerged from the current admin browser-reconciliation work. It complements `AGENTS.md` and `docs/ADMIN-UI.md`; it does not replace either one.

## Direct-dev browser collaboration

All normal source work occurs on `dev`, including work by separate scoped coding chats. Start from the exact current HEAD and inspect the confirmed browser symptom and responsible source. Fix the canonical owner rather than applying page-local patches; protect accepted adjacent pages. Reconcile concurrent `dev` advances before writing. Build once for a coherent set of changes and repeat browser review; do not infer acceptance from source review or a healthy container.

Application editing remains on `dev` and must coordinate concurrent coding work through its current branch-owned source/worker procedures. The separate `docs`, `testing` and `refactor` workstreams are not substitutes for direct development. No CI/Actions or release is authorized by an ordinary browser-repair task.

## Source of truth during browser repair

Use this order:

1. exact current source at the active SHA;
2. the user's browser observation against the built candidate;
3. accepted shared UI contracts and reference surfaces;
4. unfinished issue scope;
5. historical implementation discussion only when current source is ambiguous.

A source-level hypothesis, running container or implementation handoff is not browser acceptance.

## Shared Visual Stage contract

Large admin visualization/editorial stages use one shared outer geometry.

Authorities:

- `resources/css/admin.css` owns the canonical `--admin-visual-stage-height` token;
- `resources/css/admin/stage.css` consumes that token and owns shared stage/divider geometry;
- `resources/css/admin/data-workspace.css` owns the common stage-to-follow-up rhythm;
- feature CSS owns only the internal composition that is genuinely specific to that feature.

Rules:

- define the desktop stage height in one place only;
- do not reduce or increase individual stage heights with page-local overrides;
- if the product wants the shared stage shorter or taller, change the shared token once;
- vertical divider top/bottom inset comes from the shared stage divider inset, not per-page pixel tuning;
- a page may have a different internal column composition, but its outer stage height and follow-up rhythm still use the shared contract;
- content that can exceed a Stage pane's available height must scroll or clip **inside that pane**; it must not append a fourth pseudo-section below the Stage, extend the Stage height, or silently truncate meaningful rows merely to fit;
- a pane may reserve a final action rail while variable content remains bounded independently. Storage keeps Media Distribution scrollable in its own pane and places Refresh / `Free storage` beneath Total Capacity;
- schema-backed Filament pages must neutralize framework grid gaps around the stage/follow-up boundary instead of compensating with page-local margins;
- `admin-visual-stage-block` / `admin-visual-stage-followup` are the shared mechanism for that schema-backed boundary;
- General may own its internal desktop divider, but it must not own a separate outer stage height or post-stage spacing system;
- responsive Visual Stages never generically stack their desktop panes: each feature owns an intentional Narrow/Minimal composition that preserves required operations and may omit redundant visualization/parallel preview.

The current smaller desktop stage is intentional. Do not reintroduce an older/taller value through a second token declaration or later override.

## Shared icon semantics

`App\Filament\Support\AdminIcon` is the semantic icon catalog for admin navigation and common actions.

Use the catalog instead of scattering equivalent Heroicon literals across pages/resources. A semantic catalog entry should remain unambiguous; do not reuse the same symbol for unrelated meanings merely because the icon is visually convenient.

Feature-specific icons are allowed when no shared semantic exists, but shared navigation/action meanings should stay centralized.

## Visual change review

The browser review is evidence against **one current built candidate**. Begin with the actual reference page when the user names an accepted Gallery, Storage or other design. Describe the control/content/action composition concretely; identify same-row groupings, shared tokens, responsive collapse conditions and neighboring surfaces that must remain unchanged. Do not convert unaccepted proposals, arbitrary pixel measurements or a previous repair attempt into normative UI requirements.

At representative Wide, Compact, Narrow and Minimal content widths, inspect the workspace header, metric strip, search/filter/Selection baseline, count badge, table/grid roles and alignment, drag/Position rails, stable action order, nested parent/child geometry, empty states, dialogs/popovers, scroll/focus, and apparent first-click/navigation latency. Compare to the accepted reference **only for shared dimensions**, preserving each feature's distinct task model. Check responsive content pressure and whether page-local CSS bypasses an existing shared primitive.

**After browser acceptance**, inspect changed Blade, CSS and JavaScript. Record the shared primitives/tokens reused and justify genuinely feature-specific new selectors. Remove only reference-proven abandoned page partials, duplicate theme rules, obsolete controls/aliases, unused CSS/JS and stale implementation-specific tests, while preserving domain/history and compatibility paths. Recheck the resulting implementation. A visually accepted surface can still require source cleanup; neither step implies the other.

## Local preview browser loop

The local preview image contains built frontend assets. Pulling source alone does not update the running CSS/JavaScript.

Durable local interface:

- repository: `P:\moeller-lars`;
- browser: `http://127.0.0.1:8001`;
- image: `moeller-lars-local-preview`;
- Compose service / web container: `preview` / `moeller-lars-local-web`;
- PostgreSQL container commonly used by the preview: `moeller-lars-postgres-1`;
- internal application port: `8080`;
- preview build target: `local-preview` in the root `Dockerfile`;
- Windows helper: `scripts/local-preview.ps1`;
- canonical copy/paste invocation from any PowerShell location: `& 'P:\moeller-lars\scripts\local-preview.ps1'`;
- private media mount destination: `/var/www/html/storage/app/private`.

Normal browser cycle:

1. fast-forward local `dev` to the intended head;
2. run the canonical helper with `& 'P:\moeller-lars\scripts\local-preview.ps1'`;
3. the helper fast-forwards `dev`, builds the `preview` service exactly once, then replaces the old preview container only after that build succeeds;
4. it starts the already-built image with `docker compose up -d --no-build --wait preview`, so container startup cannot trigger a second build;
5. after the container is healthy, the helper reloads the existing local `storage/app/analytics-demo.php` fixture into the preview cache; this is a post-start data step, not another image build;
6. review the exact built candidate in the browser.

Do not rebuild a separate runtime image before the preview build. Docker/BuildKit reuses unchanged PHP, Composer and Node layers from the single graph. The local analytics fixture is deliberately loaded after startup so browser preview data remains available without coupling that data preparation to Docker image construction.

Do not use a full dependency reinstall or broad test suite merely to inspect a CSS/Blade change. The persistent local browser database is not disposable test state; follow the database safety rules in `AGENTS.md` and `docs/RELEASE.md`.

The preview target must not add a second source/dependency/frontend build path. Runtime-writable paths come from the shared runtime image; the host private-media bind mount is attached only when the Compose preview container starts.

## Browser acceptance vocabulary

Use these terms precisely:

- **source reviewed** — code and contracts were inspected;
- **runtime verified** — a concrete runtime behavior was exercised;
- **browser reviewed** — the current rebuilt candidate was inspected in the browser;
- **browser/product accepted** — the user accepted that presentation/behavior;
- **release qualified** — the separate release gate has passed.

When source advances after the last browser build, the new source is pending browser review even if an older candidate was accepted in part.

**Admin mutation acceptance additionally requires functional evidence**:
verify a real semantic field change (including Left → Right on Page Heading)
causes persistence, a single immediate header Notification and a durable
mailbox message; close/reopen preserves the state. Repeat the same value
and an invalid value: no success feedback. Check nested Home List/Contact,
mixed selection and other representative edit surfaces. Test execution
and browser acceptance must be reported separately; source-only work must
not claim either.

## Worker task checkpoints

The currently active Dev worker's **own temporary task file**, when the branch-local system is available, records the actual user assignment and remaining actions, confirmed current source SHA, observed preview/browser acceptance and verification limitations. File ownership shared with parallel Dev workers belongs in their **Dev area coordination**, not in another worker's private task note. No separate chat-handoff prompt/document is needed; the branch-local worker lifecycle, once integrated into `dev`, governs registration and retirement.
