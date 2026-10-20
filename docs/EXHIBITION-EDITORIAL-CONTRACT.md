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
