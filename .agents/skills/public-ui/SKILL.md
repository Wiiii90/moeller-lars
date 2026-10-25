---
name: public-ui
description: Work on the artist website's public routing, navigation, Home, journals, artwork, custom page components and responsive presentation.
---

# Public website workstream

## Canonical contracts and sources

- **Normative public ownership:** [PUBLIC-IMPLEMENTATION-CONTRACT](../../../docs/public/site.md) and [ARCHITECTURE](../../../docs/ARCHITECTURE.md), combined with the *latest actually accepted* browser result.
- **Runtime entry:** [HTTP/JS inventory](../../../docs/architecture/runtime-entrypoints.md), [routes/web.php](../../../routes/web.php), [resources/views/layouts/app.blade.php](../../../resources/views/layouts/app.blade.php) and [resources/js/app.js](../../../resources/js/app.js).
- **Navigation and hierarchy:** `app/Domain/Content/PublicNavigationService.php`, `app/Routing/SiteNodeRoute.php`, `resources/js/public-navigation.js`. The menu uses fully visible items and cyclic, **item-sized** window changes, not pixel-scrolling or a second DOM navigation tree. Selection must not cause unintended jumps. Child selections keep their appropriate child navigation context.
- **Home and components:** `HomePresentationEditorialService`, `HomeRoutingSettingsService`, `CustomPageSetting` and shared public component renderer. There are **three** Home content templates; Skip Home is a separate routing flag. The [structured component inventory](../../../docs/architecture/structured-components.md) distinguishes persisted `text` from editor-only Heading/Rich Text kinds.
- **Journal, Gallery and exhibitions:** `app/Domain/Content/`, Journal media services, public Blade views and the current authored `resources/css/` styles. Exhibition Map has a canonical structured address/coordinate service; a disabled Map/Gallery feature is not a deletion of its media or location.
- **Media and public eligibility:** [MEDIA contract](../../../docs/media.md), `PublicMedia`, committed LIVE GET/HEAD reads and private Admin/Preview policy. Public presentation may display only eligible, available assets; do not weaken the policy to conceal missing media.
- **Preview vs LIVE:** protected artist Preview can show editorial drafts; public visitors receive committed published content and authorized public media. The [publication architecture](../../../docs/architecture/publication.md) describes this boundary.

## UI work discipline

1. Open the actual rendered Blade/CSS/JS for the task and a real comparable public page, then inspect the current responsive behavior. Do not project Admin-design assumptions into the public site.
2. Prefer shared content shell, typography, heading, divider, artwork and navigation owners over local page overrides. Keep menu/content alignment grounded in the actual browser composition. Avoid duplicate surfaces, stray decorative rules and redundant page headers **where the user has rejected them**; do not freeze an unaccepted proposed design into a global invariant.
3. Changes to visual components must not silently alter data/Publication behavior. For `Contact`, `Home`, `Custom Page`, `Journal` and Media, reuse existing normalization/validation rather than a parallel frontend data model.
4. Treat responsiveness as a real component/viewport layout problem. Horizontal overflow, white sidebands, sticky-scroll scope, overlap and hidden off-screen menu items require architectural fixes. No timers/interval-driven synchronization for persistent navigation/editorial state.
5. Browser-acceptance claims require actual browser evidence. Syntax review, source diagrams or isolated tests alone do not establish visual acceptance. Preserve accepted behavior and remove superseded frontend source only after a reference check.

## Branch and coordination

Application changes belong on `dev` under [AGENTS.md](../../../AGENTS.md). The separate long-lived `docs` workstream **documents** code and merges forward from `dev`, never publishes public-app changes directly; `testing` is independently owned. Do not create unapproved branches or touch CI in the documentation workflow.

This is an **entrypoint to owned contracts**, not a copied second normative UI specification. Update canonical contract and accepted source after each meaningful change; use [documentation skill](../documentation/SKILL.md) for documentation-only tasks.
