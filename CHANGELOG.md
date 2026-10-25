# Changelog

Technical release notes are maintained in `resources/release-notes/versions/`
and displayed in the Dashboard. This index does not duplicate their content.

## Historical pre-release milestones

- [v0.3.0 — Admin and editorial foundation](resources/release-notes/versions/v0.3.0.md) — completed **2026-09-18**
- [v0.2.0 — Galleries, viewer and media management](resources/release-notes/versions/v0.2.0.md) — completed **2026-08-20**
- [v0.1.0 — Application and media foundations](resources/release-notes/versions/v0.1.0.md) — completed **2026-08-16**

These dates are the latest closing dates of issues assigned to each milestone.
They do not establish Production deployments or immutable Git release tags.
The historic notes account for accepted milestone issues; subsequent `dev`
changes belong to future milestones until a specific candidate is approved.

## Release communication contract

- Every approved Production version and patch version requires reviewed,
  technical notes grouped by applicable headings such as `Added`,
  `Changed`, `Fixed`, `Removed`, `Security`, `Performance` and `Internal`.
- Every major milestone also requires an artist-facing Announcement, with a
  direct link to its Changelog; hotfix Announcements remain optional.
- Prepare drafts from the actual previous-to-next release Git range and
  associated accepted issues. Group intermediate corrective commits by their
  resulting shipped change. Review and approve before release qualification.
- Validation previews are not standalone Production releases. Preserve
  protected Validation editorial data and canonical originals across previews.
- Only Git-tracked release-note bodies and Dashboard metadata are authoritative.
  Do not duplicate these texts in mutable database rows or generate prose in CI.
- Release qualification must establish that the version, documentation,
  Announcement (for milestones), exact release SHA and Dashboard index agree.
  Checks can validate this contract; they must not publish drafts automatically.

The current [pre-release Announcement](resources/release-notes/announcements/pre-release.md)
introduces the administration before the first Production cutover.
