# Legacy public contract

This is an evidence checklist for the reviewed `P:/larsmoeller` source. It
records observed legacy behaviour and the target decision; it does not make
legacy defects compatibility requirements and does not duplicate the target
specification in [PUBLIC-IMPLEMENTATION-CONTRACT.md](PUBLIC-IMPLEMENTATION-CONTRACT.md).

The comparison method for every row is: inspect the cited source, reproduce the
route/query or rendered fixture from a read-only source snapshot, then compare
the target route, rendered fields, asset reference and failure behaviour. A
browser crawl/screenshot review remains a cutover acceptance step.

## Reviewed public-contract checklist

| Item | Exact legacy source reference | Observed legacy behaviour | Target decision | Comparison / verification |
| --- | --- | --- | --- | --- |
| Root/index dispatcher | `index.php:1-18`; `inc/config.php:147-153` (`else` branch) | Includes constants/config, header, selected content include, and footer. No `site` selects the landing include. | Preserve root as latest-work entry; use a safe explicit route boundary. | Request `/`; assert landing content and no warning/debug output. |
| `site=paintings` | `inc/config.php:57-65` (`paintings` case) | Selects `paintings`, keyword `paintings`, and `inc/entries.php`; metadata is `PAINTINGS`. | Preserve as `/paintings` with permanent legacy mapping. | Request query route and canonical target; compare ordered records and fields. |
| `site=drawings` | `inc/config.php:66-74` (`drawings` case) | Selects `drawings` and `entries.php`; metadata is `DRAWINGS`. | Preserve as `/drawings` with permanent mapping. | Same route/fixture comparison as paintings. |
| `site=prints` | `inc/config.php:75-83` (`prints` case) | Selects `prints` and `entries.php`; metadata is `PRINTS`. | Preserve as `/prints` with permanent mapping. | Same route/fixture comparison as paintings. |
| `site=cyanotype` | `inc/config.php:84-92` (`cyanotype` case) | Dispatcher exposes a category route and `entries.php` query target. | Preserve as `/cyanotype`; it is a dispatcher category, not one of the three confirmed landing tables. | Request route; verify source query/table availability during migration reconciliation. |
| `site=bichromate` | `inc/config.php:93-101` (`bichromate` case) | Exposes the “salt print & gum bichromate” category label. | Preserve as `/bichromate`, separately from the factual table list. | Route fixture and reviewed category mapping. |
| `site=litho` | `inc/config.php:102-110` (`litho` case) | Exposes “etching & lithography”. | Preserve as `/litho`, separately from the factual table list. | Route fixture and reviewed category mapping. |
| `site=photo` | `inc/config.php:111-119` (`photo` case) | Public selector is `photo`, while `$_CATEGORY` is `photos`; query uses the dynamic category path. | Preserve as `/photo`; retain the source selector/table distinction explicitly. | Route fixture plus target-category reconciliation; do not silently rename source evidence. |
| `site=ignis` | `inc/config.php:120-128` (`ignis` case) | Exposes `ignis` and “ignis-serial”. | Preserve as `/ignis`, separately from the landing tables. | Route fixture and reviewed mapping. |
| `site=other` | `inc/config.php:129-137` (`other` case) | Exposes “other photography”. | Preserve as `/other`, separately from the landing tables. | Route fixture and reviewed mapping. |
| `site=vita` | `inc/config.php:138-145` (`vita` case); `inc/vita.php:4-45` | Renders the CV/Vita include, portrait, parsed `txt/vita.txt`, and disclaimer text. | Preserve meaning/order as `/cv`; exhibitions remain a separate target entity/workflow where required. | Compare rendered text, links, portrait and order against a source snapshot. |
| Factual artwork tables/categories | `inc/landingpage.php:22-34` (`FROM paintings`, `drawings`, `prints`); `inc/entries.php:18-19` (dynamic table/path) | The reviewed source directly confirms three artwork tables used by the landing query: `paintings`, `drawings`, `prints`. | Treat these three as factual legacy DB tables/categories. Do not collapse them with the broader dispatcher route set above. | Inventory source DB/export records and reconcile per-table counts; document unavailable table evidence as an exception. |
| Visible navigation | `html/header.html:46-55` (`#mainmenu`) | Visible links are Paintings, Prints, Drawings, and “CV & EXHIBITIONS”; no working Contact link is present in this header. | Preserve labels/order and artistic identity; target may add an explicit Contact surface. | Screenshot/DOM fixture at desktop and mobile widths. |
| Hidden/sitemap-only routes | `sitemap.xml:9-57`; `inc/config.php:18-48` metadata for `CYANOTYPE`, `BICHROMATE`, `LITHO`, `PHOTOGRAPHY`, `IGNIS`, `OTHER`, `LINKS` | Sitemap lists root, `index.php`, category query URLs, Vita, and `links`; several are not visible navigation items. | Verify each intended public category; do not advertise broken/non-public routes. | Compare sitemap inventory, dispatcher cases and target sitemap; classify each route. |
| Broken `links` route | `sitemap.xml:53-56`; `inc/config.php:46-48` only (no `links` switch case in `:53-146`) | Metadata/sitemap advertise `site=links`, but dispatcher has no case and therefore no working content route. | Treat as a known defect; no public target or automatic redirect. | Request route and assert safe not-found/redirect classification; ensure target sitemap omits it. |
| Intended Contact surface and missing handler | `html/contact.html:1-31`; `js/validatecontact.js:1-76`; no `inc/contact.php` in source inventory; no `contact` case in `inc/config.php:53-146` | Form fields are required Name, Email, Comment, optional Website; client validation exists, but dispatcher/handler is missing. | Implement the intended outcome safely; do not reuse mail/configuration details or claim the legacy route worked. | Compare field/validation fixtures and test safe success/error delivery in target. |
| Artwork query fields/rendering | `inc/entries.php:18-32`; `inc/landingpage.php:22-47` | Queries use `filename`, `title`, `date`, `material`, `dimension`, `comment`, and `id`; output includes image, title/year, material/dimension, optional comment, and title-derived ALT. | Preserve meaningful fields and semantics with safe encoding and explicit target schema. | Fixture compares each field, rendered year, optional-field handling and ALT. |
| Category/list ordering | `inc/entries.php:18` (`ORDER BY date DESC`) | Category lists sort by stored date descending. | Preserve date-descending order; target uses reconciled explicit `position` for established ordering. | Compare complete ordered result sets and same-date groups. |
| Home/latest-work query | `inc/landingpage.php:22-34` (`UNION ALL` paintings/drawings/prints, `ORDER BY date DESC LIMIT 1`) | Home combines only the three confirmed tables and selects one newest record. | Preserve this landing set and winner rule; do not silently broaden it to other dispatcher categories. | Compare source query result and target home winner for the approved snapshot. |
| Thumbnail/original convention | `inc/entries.php:19,28`; `inc/landingpage.php:22,26,30,43`; `js/resizeimage.js:225-228` | Thumbnail path is `img/<category>/tn/<filename>`; viewer removes `tn/` to load the corresponding original path. | Retain original filename/path provenance and authoritative original; generated derivatives are rebuildable. | Check thumbnail/original pair existence, filename mapping and rendered URLs. |
| Viewer interactions present in legacy JS | `js/resizeimage.js:210-347` (`.resizable` click, loader, close cross, mousewheel, `+`/`-` key codes, drag, double-click close) | Click opens overlay; image loads with loader; mousewheel and keypad plus/minus scale; drag is configured; close cross and double-click close reset it. No verified Escape, touch/pinch, or previous/next control. | Rebuild reliably; retain artwork-first intent and allowed subtle improvements (keyboard/touch, previous/next). | Interaction tests cover observed controls plus explicitly improved behaviours and failure states. |
| Responsive behaviour/breakpoints | `css/style.css:439-458` (550–980), `:460-480` (980–1400), `:482-577` (≤550); `js/resizeimage.js:285-298` (≤500 viewer sizing) | Layout, menu, form, Vita and viewer sizing change at these source breakpoints; small viewer images use window width. | Preserve composition/readability while improving robustness where needed. | Visual/interaction fixtures at representative desktop, tablet and mobile widths. |
| CV/Vita source and portrait | `inc/vita.php:4-37`; `txt/vita.txt`; `inc/config.php:138-145` | Portrait is `res/lars-moeller.jpg`; text is read from `txt/vita.txt` and passed through the legacy BBCode-like formatter. | Migrate text/meaning/order losslessly into safe structured CV content; preserve portrait provenance. | Text/link/order diff plus portrait checksum/media reconciliation. |
| Sitemap | `sitemap.xml:9-57` | Static HTTP sitemap lists root, query URLs, Vita and broken `links`; it includes no per-artwork routes. | Regenerate canonical HTTPS sitemap from target routes; omit broken/non-public routes. | Compare classified source inventory to generated target sitemap. |
| Robots | `robots.txt:1-3` | `User-agent: *` with an empty `Disallow` (permissive). | Preserve intended discoverability without exposing admin/workshop/draft surfaces; publish canonical sitemap. | Assert target robots response and excluded surfaces. |
| Canonical/redirect behaviour | `.htaccess:1-4` | Host/index rules redirect to `http://`; direct `index.php` is intended to reach root, but HTTPS is not enforced. | Correct to one-hop HTTPS/canonical-host redirects; do not preserve insecure scheme. | HTTP/HTTPS and index/query redirect matrix; assert no loops/fragments/query leakage. |
| Public metadata/ALT | `html/header.html:4-18`; `inc/config.php:2-48`; `inc/entries.php:28`; `inc/landingpage.php:43` | Charset/language/author/title/description/keywords/favicon are emitted; artwork ALT is title; landing image is also title ALT. | Preserve meaningful metadata and title semantics with safe escaping and accessibility correction where needed. | Head/ALT snapshot comparison and accessibility assertions. |
| Unknown/invalid route behaviour | `inc/config.php:53-153` (no default switch case); `index.php:13` (includes `$include`) | Unknown `site` values leave the default include prefix and can produce include/warning/error behaviour rather than a defined public page. | Treat as a defect; return safe not-found/redirect with no debug/database disclosure. | Request unknown/malformed selectors and assert safe status/body/logging. |
| Non-public workshop/admin/development paths | `workshop/index.php:4-15`; `workshop/admin/index.php:28-46`; `admin/index.php:9-36`; `info.php:1` | Workshop and admin contain editable/authenticated tooling; `info.php` exposes `phpinfo()`. These are not public content. | Exclude/contain them; replace legacy auth and never expose debug/admin surfaces. | Deployment route scan and unauthenticated access tests. |

## Ordering and category distinction

The source directly confirms three factual artwork tables/categories—
`paintings`, `drawings`, and `prints`—through the landing query. The dispatcher
also exposes the broader public selector set `cyanotype`, `bichromate`, `litho`,
`photo` (backed by selector `photos`), `ignis`, and `other`; these are not to be
silently collapsed into the three-table fact. Every source-record/category
mapping requires explicit review.

Legacy same-date ordering is undefined. Target `position` may come only from an
approved and reconciled authoritative legacy display/export ordering where one
can actually be established. Never silently substitute source ID, target ID,
insertion order, or arbitrary database order. Unresolved same-date groups are
explicit migration/editorial exceptions.

## Intended contract versus known defects

The intended contract is the artistic public presentation, verified content and
navigation, artwork metadata/media relationship, CV meaning, intended Contact
outcome, and discoverability metadata. Known defects are not compatibility
requirements: insecure authentication and SQL, missing request protections,
unsafe uploads/rendering, debug exposure, HTTP-only redirect behaviour, broken
`links` and Contact handler paths, undefined ordering, and public
workshop/admin/development surfaces. Credentials and secret-bearing
configuration are evidence only and are never copied into project artifacts.
