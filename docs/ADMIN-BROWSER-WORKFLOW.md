# Admin browser workflow and shared-stage contract

This document records the durable working rules that emerged from the current admin browser-reconciliation work. It complements `AGENTS.md` and `ui-skills.md`; it does not replace either one.

## Collaboration modes

The project supports two intentional ways of working during browser reconciliation.

### Direct interactive dev mode

Use this only when the user explicitly authorizes the main chat to work directly on `dev`.

In this mode the main chat may inspect, implement, commit and push small browser fixes itself. The point is to preserve context between the browser observation, the source diagnosis and the repair instead of translating each small defect through another prompt.

Rules:

- begin from the exact current `dev` head;
- work from one concrete browser symptom inward;
- inspect only the directly responsible source plus one narrow search when ownership is unclear;
- do not stack speculative patches after a failed browser result;
- if a first repair does not change the rebuilt browser result, identify the actual element/selector/source owner before editing again;
- when the defect is shared, repair the shared authority instead of compensating individual pages;
- keep neighboring accepted surfaces unchanged unless the user includes them in scope;
- build once after a coherent fix set and return to browser review;
- if another worker or chat advances `dev`, re-read the exact head before the next write.

Direct work is not permission for a broad repository audit. A change that expands beyond the confirmed symptom should be reported before scope grows.

### Worker orchestration mode

Use workers for large independent source passes, parallel scopes or work whose implementation detail would consume the main review context.

Worker prompts must not translate concrete browser feedback into vague design language. Every implementation prompt should carry:

- the exact base SHA;
- the exact browser symptom or confirmed defect;
- the known source owner/root cause when established;
- the target state in concrete structural terms;
- neighboring surfaces that must remain unchanged;
- shared primitives/tokens/technologies that must be reused;
- explicit non-goals and stop conditions;
- the checks and handoff evidence required.

If the target structure is not yet known, use a bounded audit worker first. Do not ask an implementation worker to “polish”, “make consistent”, “clean up” or “use best judgment” as a substitute for a defined target.

Parallel workers should not own the same shared CSS/theme files unless there is an explicit reconciliation plan.

## Source of truth during browser repair

Use this order:

1. exact current source at the active SHA;
2. the user's browser observation against the built candidate;
3. accepted shared UI contracts and reference surfaces;
4. unfinished issue scope;
5. historical implementation discussion only when current source is ambiguous.

A source-level hypothesis is not browser acceptance. A running container is not browser acceptance. A worker report is not browser acceptance.

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
- schema-backed Filament pages must neutralize framework grid gaps around the stage/follow-up boundary instead of compensating with page-local margins;
- `admin-visual-stage-block` / `admin-visual-stage-followup` are the shared mechanism for that schema-backed boundary;
- General may own its internal matrix divider because its desktop/mobile composition differs, but it must not own a separate outer stage height or post-stage spacing system.

The current smaller desktop stage is intentional. Do not reintroduce an older/taller value through a second token declaration or later override.

## Shared icon semantics

`App\Filament\Support\AdminIcon` is the semantic icon catalog for admin navigation and common actions.

Use the catalog instead of scattering equivalent Heroicon literals across pages/resources. A semantic catalog entry should remain unambiguous; do not reuse the same symbol for unrelated meanings merely because the icon is visually convenient.

Feature-specific icons are allowed when no shared semantic exists, but shared navigation/action meanings should stay centralized.

## Local preview browser loop

The local preview image contains built frontend assets. Pulling source alone does not update the running CSS/JavaScript.

Durable local interface:

- repository: `P:\moeller-lars`;
- browser: `http://127.0.0.1:8001`;
- image: `moeller-lars-local-preview`;
- web container: `moeller-lars-local-web`;
- PostgreSQL container commonly used by the preview: `moeller-lars-postgres-1`;
- internal application port: `8080`;
- preview Dockerfile: `docker/Dockerfile.local-preview`;
- private media mount destination: `/var/www/html/storage/app/private`.

Normal browser cycle:

1. fast-forward local `dev` to the intended head;
2. ensure PostgreSQL is running;
3. rebuild `docker/Dockerfile.local-preview` so Vite assets match that source;
4. replace only `moeller-lars-local-web` while reusing the existing network/environment/media mount;
5. inspect container status/log tail;
6. review the exact built candidate in the browser.

Do not use a full dependency reinstall or broad test suite merely to inspect a CSS/Blade change. The persistent local browser database is not disposable test state; follow the database safety rules in `AGENTS.md` and `docs/RELEASE.md`.

The preview Dockerfile must only change ownership on runtime-writable cache/log directories. Do not recursively change ownership across the complete `storage` tree, because local recovery/snapshot material can be read-only and is not part of the runtime write surface.

## Browser acceptance vocabulary

Use these terms precisely:

- **source reviewed** — code and contracts were inspected;
- **runtime verified** — a concrete runtime behavior was exercised;
- **browser reviewed** — the current rebuilt candidate was inspected in the browser;
- **browser/product accepted** — the user accepted that presentation/behavior;
- **release qualified** — the separate release gate has passed.

When source advances after the last browser build, the new source is pending browser review even if an older candidate was accepted in part.

## Handoff rule

A continuation prompt must state which collaboration mode is active: direct interactive dev, worker orchestration or mixed.

Do not automatically convert a successful direct browser-repair session into a worker-only workflow. Likewise, when workers are active, preserve their exact base/head/review state rather than collapsing them into a vague summary.

The handoff must include the exact current `dev` SHA, current local preview status if known, the last browser-accepted state, source changes not yet browser-reviewed, known incidents that can explain confusing history, and the next concrete review action.
