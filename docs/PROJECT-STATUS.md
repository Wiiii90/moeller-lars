# Project status

Snapshot: **2026-08-18**

This document is an orientation snapshot, not a replacement for GitHub Issues and milestones. It should describe what exists in the repository today and group the remaining acceptance work without duplicating every ticket.

## Implemented foundation

The repository currently contains:

- Laravel 13 / PHP 8.3 modular-monolith application structure;
- PostgreSQL target schema and migrations;
- generic artwork/category domain and public canonical routing;
- secure media ingest, originals/derivatives, metadata, integrity verification and controlled delivery;
- authenticated Filament 5 artist administration with artwork, category, media, CV, Exhibition and Blog resources;
- admin dashboard/artist workspace components and quick actions;
- server-side authorization and immutable audit boundaries;
- constrained rich-text and safe-link rendering;
- Blog lifecycle with disabled-by-default public visibility;
- Contact lifecycle and configurable delivery target;
- CV/biography and Exhibition target domains separated in the data model;
- legacy artwork/media and Vita migration/reconciliation tooling;
- Matomo browser/event tracking with separate tracking/reporting switches;
- Matomo-backed artist analytics reporting plus separate local operational aggregates;
- CI verification and immutable OCI/GHCR release-image build;
- application-side health, persistence, migration and rollback contracts for `server-platform`.

Recent implementation work has substantially advanced the artist administration and analytics surfaces beyond the early scaffold described by older issue text and early README revisions.

## Active acceptance tracks

### Public presentation

Public visual acceptance is still active. The key work is close legacy-derived shell parity, immersive artwork-viewer presentation and richer direct artwork-detail navigation/content. See #10, #16 and #55.

### CV, Exhibitions and migration reconciliation

The target domains are split, but public/editorial acceptance and final legacy Vita normalization/reconciliation remain tracked in #23, #24 and #31.

### Artist administration

The repository now contains a purpose-built dashboard and richer editorial workflows. Final production-usable information-architecture and UX acceptance remains tracked in #51 rather than being inferred merely from the presence of Filament resources.

### Analytics

The Matomo integration and expanded dashboard/reporting implementation exist. Production read-only integration, final dashboard acceptance and platform-provided Matomo validation remain tracked in #29.

### Future category hierarchy

One-level parent/child artwork categories and dropdown/submenu navigation are a separate feature in #52; the current category model remains flat until that work is accepted.

### Release and cutover

The release artifact pipeline exists, but production readiness still depends on public regression comparison, restore/rollback validation, production-readiness review, editorial approval, cutover validation and stabilization. See #34 and #38–#42 plus the corresponding `Wiiii90/server-platform` gates.

## Source-of-truth rule

- Use current code and canonical documents for implemented behaviour.
- Use open GitHub Issues/milestones for unfinished acceptance work.
- Use legacy documents only as source/migration evidence.
- Use `Wiiii90/server-platform` for production topology and operational state.

Do not infer production readiness from a feature being implemented in code. A feature remains incomplete for project purposes until its acceptance/release gates are satisfied.
