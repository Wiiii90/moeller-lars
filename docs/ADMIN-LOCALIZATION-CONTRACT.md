# Admin language and regional formats

The authenticated Filament SPA supports per-user `admin_language` values:
`en` (English) and `de` (Deutsch). This is independent of the same user's
`admin_region` preference (de-DE/en-GB/en-US) for dates and numbers.
Both are managed in Account. The top-right user-badge dropdown retains
its existing Theme → Account → Logout order, with the new Language action
inserted directly after Account. Its label is simply "Language" (or "Sprache"
in German), without displaying the current value. Both entry points update
the same users.admin_language preference.
Changing Language requires a full SPA page reload, because a pre-existing
Livewire/Alpine tree contains already rendered server-side labels.

`SetAdminLocale` is a persistent authenticated admin middleware, including
Livewire updates. Public routes MUST NOT inherit a signed-in editor's language
or region. Public date and numeric formats are controlled in General via
`regional_format`; the public language is unchanged (English).

The default source language for artist-admin UI is English.
Laravel translations live in `lang/de.json` and German validation/auth
messages in `lang/de`. All own visible labels, headings, option labels,
errors, tooltips, navigation, dialogs, and Blade text should use Laravel
translation at **render time**, not be stored translated in canonical
publication/audit data. Where information originates from artist content,
never translate or mutate it.

Filament package translations are used for framework-owned controls.
New artist-facing strings need an English source and German translation
in the same change; do not add a fake placeholder language switch.
Do not rely on APP_LOCALE=de as a substitute for personal preferences,
and do not change public language when an admin changes theirs.

End-to-end review must include new sessions, navigation, action modals,
date/number controls, validation, storage, journal, gallery, custom pages,
general, dashboard and Livewire navigations. The code has not been
browser-accepted simply because translations and middleware exist.
