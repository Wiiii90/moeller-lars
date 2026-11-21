# Dev Worker 7 — General Admin Page

- **Branch:** `dev`
- **Lease:** 37
- **Status:** Active; source orientation complete; concrete General Page changes not yet specified by user.
- **Claimed scope:** General Admin appearance and settings; coordinate changes to shared Stage, live preview and visual controls with neighboring Dev workers.
- **Registered at:** `4dd7e526487985633de8e42b1f0496be74944a8d`; orientation reviewed on latest available `dev` source.
- **Current implementation:** `app/Filament/Pages/General.php` owns the Filament form, autosave and UI events; `app/Domain/Admin/AdminSettingsService.php` normalizes transactions and audits; `app/Models/PublicContentSetting.php` stores General data and `app/Domain/Content/PublicAppearance.php` validates and projects appearance. `resources/views/filament/pages/general.blade.php` embeds the form. The Appearance Stage uses `general-appearance-switcher.blade.php` and `general-live-preview.blade.php`; the shared `resources/views/components/admin/live-preview.blade.php` refreshes the public Home preview on `general-appearance-updated`. `resources/css/admin/general.css` owns General-specific arrangement. Public consumes General through `app/Domain/Content/PublicSiteContext.php` and `resources/views/layouts/app.blade.php`.
- **Observed state:** Appearance offers Global, Title, Menu and Page panes with a split live preview; other sections include social profiles, Contact, Legal and Regional Formats. Changed fields are autosaved through the canonical settings service; successful appearance saves dispatch the preview refresh event and a notification.
- **Verification:** Source-only orientation. No feature edits, application runtime checks, automated tests, browser review, deployment or user acceptance.
- **Next:** Receive the user's specific General Page changes; trace the affected exact control, persistence owner and direct consumer before making a bounded `dev` change. Recheck roster lease 37 and current `dev` head before every write.
