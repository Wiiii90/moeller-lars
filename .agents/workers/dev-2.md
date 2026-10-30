# Dev Worker 2 — Public Gallery artwork fitting

**Branch:** `dev` · **Worker:** Dev Worker 2 · **Lease:** 2 · **Status:** Active
**Assignment (2026-10-30):** Continue the Gallery responsive artwork sizing correction after commits `53809bf9`, `4496fb12`, `0a23d836`. Some portrait artworks grow beyond the viewport height, requiring page scrolling; both artwork and metadata should fit within the available height with consistent spacing like the accepted landscape reference. Preserve responsive narrow-viewport behavior, browser-resize recovery, landscape fill, and independent menu-surface width/Separate Surface settings.
**Current verification:** User screenshots show portrait taller than viewport and accepted landscape presentation. Technical cause and production correction not yet established.
**Ownership:** Existing Public Gallery artwork detail sizing and immediately involved shared menu/paper sizing; no media upload, rights, thumbnail cutover, tests, unrelated admin pages, CI or deployment.
**Next:** Inspect current `dev` source and relevant Public contract, establish a single viewport-fit owner, apply a bounded source correction and report executed vs. unverified checks.
