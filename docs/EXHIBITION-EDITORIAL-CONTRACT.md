# Exhibition editorial contract

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
- `starts_on` and `ends_on` remain structured dates that control
  Upcoming/Current/Past; `date_text` remains a presentation override and
  must not redefine exhibition timing.
- Admin uses the existing native Filament date picker with ISO persistence
  and German input language hint. Native typing behavior is browser-dependent
  and needs browser acceptance, not just source inspection.

Changes use the existing transactional editorial/audit/notification path.
Public preview may include drafts; normal Public is publication-filtered.

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
