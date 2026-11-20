# Dev Worker 11 — public-site presentation and CMS-consistent small fixes

**Working branch:** `dev` | **Lease:** 27 | **State:** Active — Public Gallery image prefetch correction source-implemented; browser and latency acceptance pending.
**User assignment:** Handle a continuing sequence of small, user-reported issues on the public-facing pages of the artist homepage. Resolve each coherently through the existing content management system (CMS) architecture rather than making isolated presentation patches. No specific defect was assigned in the activation request.

## Scope and owner boundaries
- Own bounded public-page rendering, layout, responsive presentation and interaction fixes that do not already belong to another worker. The public Blade/views, authored styles/scripts and existing domain projections are the starting points, not new or duplicated presentation systems.
- Trace each issue from the public observation through its canonical CMS configuration, Working/Committed publication boundary, public projection and actual renderer. Retain preview/committed parity, artist-defined settings, accessibility, the strict Public content security policy and existing shared primitives.
- Coordinate before touching an active neighboring owner's area: Dev Worker 1 (Exhibitions), 4 (Gallery/Artwork), 5 (Home and page types), 6 (Home Hero selection), 7 (General appearance and embedded Preview), 9 (public navigation) or 10 (image rights). Do not take over their active work through this general assignment.
- Do not change unrelated Admin editors, shared publication/persistence contracts, media lifecycle or product architecture without an explicit defect-driven scope and coordination. No routine test-source or general documentation edits, CI, deployment, Validation changes or promotion to `main`; production code only on `dev`.

## Activation evidence
- Read `dev/AGENTS.md`, `dev/.agents/skills/dev/SKILL.md`, `dev/.agents/README.md` and the branch-local roster; allocated the lowest free Dev slot and a fresh monotonic lease.
- No application source files changed; no runtime or browser checks claimed.

## Public keyboard issue (2026-11-20)
- User reports text-caret insertion when clicking ordinary public copy and an apparently endless arrow-key navigation loop; also requests a broad later accessibility review, including Admin.
- The public layout contains normal display content, not an editing root. User confirmed the blinking text cursor was browser caret browsing (F7), not an application defect. No global caret suppression or user-select hacks added.
- `resources/js/public-navigation.js` intercepted all four arrow keys on navigation links, shifted its cyclic window at the edge and moved focus repeatedly. It also intercepted ArrowDown on submenu parents. This conflicts with ordinary document scrolling and native landmark navigation.
- Removed only these keydown overrides. Native Tab/Shift+Tab traverse the visible links and previous/next buttons; Enter/Space invoke their native click behavior; Escape and the dedicated Back control still exit subnavigation. Cyclic window layout/controls and Dev Worker 9's hover countdown remain unchanged.
- Bounded source review: artwork viewer handles arrows only inside its open dialog; Admin dialog geometry uses Alt+arrow on its focused header or focused resize grip, not a global navigation override. Broad end-to-end Admin keyboard acceptance remains unverified and should not be treated as completed.
- User subsequently reported no remaining problem with these issues in their browser and accepted the focused correction. This is user-observed product feedback, not a dedicated end-to-end keyboard accessibility test. No source tests edited, no CI, no deploy, no independent local browser run. Comprehensive keyboard, assistive-technology, and Admin accessibility checks remain open.

## Public Gallery image priority and latency — 2026-11-20
- User reports that later artworks sometimes display before the first, and that scrolling leaves temporary blank image spaces. Requested first viewport plus further scroll lookahead and genuinely deferred media requests; server pagination is acceptable only if necessary.
- Existing source already renders the entire canonical Gallery/Viewer sequence while keeping later image URLs in `data-gallery-src`. Only the first two images have initial `src`; `IntersectionObserver` requests later images when close to view. Replacing this with a second paging/image renderer is not justified without measured initial document/variant latency and would change Viewer completeness, committed/preview reads and Gallery ordering.
- In `resources/js/public-gallery-loading.js`, speculative slots were freed immediately after image `load`, before asynchronous `decode()` finished and the image could be revealed. The resulting queue could request successive lower-priority images while earlier artwork was still invisible. Buffer scheduling also had no canonical-order tie-break for equally distant visible candidates, and the 960px lookahead was shorter than a tall viewport.
- Corrected the existing owner: hold the two speculative request slots until image decode/error completion; rank equally near candidates by existing DOM/Artwork order, and activate newly visible images in that same order. Lookahead remains at least 960px and now reaches 1.2 times the initial viewport height on taller screens. All visible requests bypass the speculative budget as before. First two server-marked eager images, Viewer order/DOM, original URLs, native no-JavaScript fallback, Home fitting and Artwork detail remain unchanged.
- This is real delayed image-file requesting, **not server-side pagination**; all Artwork HTML still arrives initially. Independent image response/variant regeneration delays can still leave a blank region and cannot be proven resolved without network timings on actual Preview. No media-delivery, thumbnail regeneration, publication or rights changes attempted.
- Source-only review, no runtime/browser/network timing checks, tests edited, Continuous Integration or deployment. Testing should check simultaneous observer entry ordering, asynchronous decoded images delaying speculative release, rapid scroll, first image priority, broken files, cached images, no-JavaScript mode, video cards, responsive resize and Livewire route disposal.

## Next actions
1. Obtain user browser feedback and network response waterfall for first/next Gallery images on the current built dev preview; distinguish image priority/decode from slow or regenerated thumbnails.
2. If real document HTML/variant response timing proves source-side paging is needed, design it with Dev Worker 4 and preserve the canonical Viewer sequence and publication state rather than introducing a parallel image renderer.
3. Address further scoped Public defects and coordinate separately with Admin owners on a broader keyboard accessibility pass.
