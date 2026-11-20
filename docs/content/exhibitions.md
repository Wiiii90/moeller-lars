# Exhibition editorial contract

**Source reconciliation:** development source reviewed 20 November 2026. The notes below record current contracts and verified local migration evidence, **not** a passing new browser test or production qualification.

The Exhibitions Journal uses the canonical `Exhibition` model,
`ExhibitionEditorialService`, `JournalEntryEditorSchema` and public
`pages/exhibitions.blade.php` renderer. Do not introduce a second
editor, public list or geocoding path.

## Identity and location

- `title`: exhibition title.
- `organizer`: gallery, institution or other organizing party. This is the
  primary public summary attribution beneath the title.
- `venue`: actual physical exhibition venue, when different from the organizer.
- `location_text`: street address, not a venue or organizer name.
- `postal_code`, `city`, `country`: structured postal information.
- `external_url`: external exhibition information link, not an implicit
  organizer or venue identifier.

The public summary falls back to `venue` for unmigrated historical entries
that still use the ambiguous old field; it never silently appends `city` or
`country` to that byline. The detailed venue and OpenStreetMap map dialog
describe the physical location, not the organizer. Geocoding uses only the structured
street/postal locality/country fields; `organizer` and `venue` never
become address text. Changes to address inputs invalidate stored
coordinates via the editorial service.

The forward migration moves `venue` to `organizer` only for historical
`legacy-public-vita` records, whose migration proves the source field
was `organisation`. Other pre-existing `venue` values remain untouched:
their meaning cannot safely be inferred. They require artist review in the
Editor; their current byline stays visible as a compatibility fallback.
Both Working and Committed schemas receive the same new fields. Do not
rewrite retained publication checkpoints.

The 2026-10-20 legacy address reconciliation may copy the original dual
venue description for "Der blonde Hans" into the structured Address fields,
but only if the original source data remain unchanged. Description text is
not deleted. Public suppresses a description only when its content exactly
duplicates the structured address; no geographic pin is invented.

## Vernissage and dates

- `vernissage_at`: optional structured opening time; never default to today.
- `opening_text`: preserved historical free-text opening description.
  Structured `vernissage_at` takes precedence when present.
- `show_vernissage`: explicit user choice. Existing rows with populated
  vernissage data were initialized to true to preserve intent; new rows
  default to false.
- A structured vernissage appears in Public only until its date and time
  pass. A legacy textual vernissage without a timestamp appears only for
  `upcoming` exhibitions. Hiding the vernissage never deletes its details.
- `starts_on` and `ends_on` are independently normalized precision-aware
  expressions: `YYYY`, `YYYY-MM`, or `YYYY-MM-DD`. `ExhibitionDate`
  owns validation, inclusive ranges, regional display and temporal status.
  An exact start with no end denotes a single day, not an open-ended event;
  an imprecise year/month is never silently changed into an exact day.
  The retired runtime `date_text` is not a second presentation/temporal owner.
- Admin uses the shared `AdminPartialDateControl` for Starts and Ends,
  including the Starts help explaining the one-day case. Do not restore a
  native date-only control that loses year/month precision or a parallel
  string-date path. Historical snapshots are immutable; restoration must
  project precise and imprecise original values without guessing.

Changes use the existing transactional editorial/audit/notification path.
Public preview includes non-archived exhibitions (including Draft and
Unpublished); ordinary Public shows only `state=published` exhibitions
belonging to that Site Section.

**Local migration evidence (3 November 2026, user-provided command output):**
Working and Committed `starts_on`/`ends_on` columns were confirmed
`varchar(10)`; the former `date_text` columns were absent; the publication
schema parity check returned `PARITY_OK`. An earlier conversion preflight
covered 29 Working, 29 Committed and 116 retained checkpoint rows without
recognized ambiguity. This establishes local schema readiness only; it does
not prove each converted date, historical restore behavior or the separate
Validation database was verified. A Validation inventory and user-approved
backup/recovery plan are required before any later migration/deployment.

## Per-field public visibility

The existing exhibition Editor exposes small Publish/Unpublish icon actions beside
the labels for Organizer, Venue, Exhibition website, Street address, Postal code,
City and Country. Each action controls only that field's public presentation;
source values remain editable, retained and available for map geocoding.
All seven fields default to visible for existing and new exhibitions. New
drafts expose the visibility actions after their first save.

These actions use the normal audited exhibition domain and do not introduce
a second geocoding or publication system. Their changes are Working changes
subject to the usual site Commit; they are not instantly deployed to Public.

Public summary attribution, venue facts, website links, address composition and
map-dialog text honor these settings. A hidden Organizer never silently falls
back to Venue when an Organizer value exists: an independently visible Venue
appears as the detail fact instead. Historical entries without an Organizer
keep their Venue summary fallback without a duplicate Venue fact. The map's independently controlled
`map_enabled` option is **not** silently changed by hiding an address label:
an enabled map can still reveal coordinates through OpenStreetMap. Turn off
the map separately if the location itself should be hidden.

Admin labels are translated at render time through Laravel using the user's
`admin_language`. The date pickers, admin table and public date strings use
`RegionalFormats` with the respective independent admin/public region.
The artist-provided `date_text` and legacy opening text remain unchanged.

Visibility icons reconcile the optimistic `JournalWorkspace::entryEditorOriginal`
snapshot for their own flag and record timestamp, retaining edit-dialog
autosave and detection of unrelated concurrent changes. The historic rule
that entries lacking a genuine street address do not render an Address fact
is preserved; hiding an existing street can still show opted-in locality.

## Exhibition editor layout and audit

Exhibition Create and Edit use the shared Journal editor rather than a
separate Gallery or map editor. Organizer and Venue share a responsive
two-field row; the Exhibition website is full width. The structured address
fields (Street address, City, Postal code and Country) share one fixed
four-column row even at narrow widths, as expressly chosen by the artist.
Starts/Ends use the shared precision-aware controls; Vernissage and map
settings remain independent. Gallery presentation spans the form width;
media use the existing AdminTableRepeater with Storage selection and
add/delete/order actions. Live changes use the established Edit autosave;
Blog retains its own field order. No parallel save path may be introduced.

Per-field visibility changes use the canonical `exhibition.updated` audit
action and mutation snapshot, without unsupported audit metadata or a
separate action catalog entry. No-op visibility changes do not create audit
or notification records.

An Exhibition Carousel must contain at least three persisted Gallery media:
the editor disables choosing Carousel below that count, live deletion below
three changes the selection to Slideshow, and the shared Journal media
service also reconciles the saved presentation after media mutations and
direct asset removal. Entries saved earlier with fewer media render via the
public Slideshow fallback until corrected. This requirement applies equally
to Blog, without silently rewriting other authored Gallery layouts.

In an opened Public Exhibition with a cover, the image remains in its own
left column while facts, actions and long rich-text description occupy one
continuous right column at sufficiently wide Content widths. The description
and its divider use the available column width instead of a 38rem text cap
or wrapping under the cover. Narrow widths stack; without a cover, content
uses the full available reading width. Exhibition gallery options remain
Grid, Mosaic, Slideshow and Carousel as distinct selectable presentations.

**Acceptance handoff:** Testing Worker 1 owns the pending source-reconciled
regression qualification: date precision and save/reopen, historical restore,
field/map optimistic concurrency, publication versus preview visibility,
cover/description layout with and without a cover, Carousel 3/5 tracks
and reduction below three media, slideshow/video/modal navigation,
long copyrights and mobile widths. These are outstanding browser/runtime
checks, not assumed passing results. Docs Worker 1 owns any further
canonical prose reconciliation after accepted changes are transferred.
