# Dev Worker 2 — Media upload, integrity and cleanup

**Branch:** `dev` | **Lease:** 6 (active) | **State:** source implementation ready for independent Testing, not runtime-accepted.
**Ownership:** canonical media upload/Storage/domain reference and physical deletion. Gallery/Artwork user-interface ownership remains Dev Worker 4.
**Latest source reviewed for this consolidation:** `bab9f916b3fff441c2f4d2bc63958c38a5f663ec`. Re-read HEAD on Testing because Dev Workers 1/3/4 may still advance dev.

## Current implementation and known gate
- MediaIngestService/ResumableMediaUploadService: original+thumbnail/poster, SHA-256 checksum and dedup; staged chunk digests, resume/cancel, committed upload receipt recovery, nontransactional filesystem cleanup. Large user-approved uploads remain required; never reduce limits as a fix.
- ReferenceQuery, MediaAssetEditorialService, PublicMedia, PublicationMediaCleanupService: nested Home/Custom Page references, transactional removal, fail-closed physical owner checks and deferred publication deletion outbox.
- Free Storage retains every registered original and required preview, including unassigned media. Strict bounded sweep is exclusively for truly uncatalogued ownerless files, plus expired private staging and eligible deleted data, preserving Working/Committed/history/outbox.
- Removed preview regeneration middleware, obsolete public-v1/v2 reader and cutover code. Current one-preview-kind contract now backed by a guarded media_variants schema migration in public + committed, without transform_profile.
- MediaIntegrityService streams checksum and verifies actual available preview; retained logically deleted bytes may be checked without being mislabeled orphan.
- Source regressions already corrected: Home/PublicArtworkQuery removed constant `LARGE_IMAGE_TRANSFORM_PROFILE` (commit `b9730482`); startup migration now scopes `PublicationSchemaGuard::assertParity(['media_variants'])` (commits `e3b65aaf`, `308c778a`). User confirmed local-web starts again.
- **Known distinct issue:** existing `site_sections` public/committed parity drift was observed in local migration logs; full publication/restore parity still detects it, and this media task did not repair it. Do not misrepresent it as solved.
- **Critical acceptance gate:** source reviewed only, no actual browser, PHP/JavaScript test runs, CI, or Validation deployment by Dev Worker 2. Do not deploy changed Media/Storage to artist Validation without isolated test and recoverable database+media backup.

## One consolidated Testing Worker handoff (integrate with other workers' requests)

**A — Upload formats, completeness and repeatability**
- Authenticated native Storage/Gallery/Artwork create, replace, attach, reuse and batch operations; successful upload means registered MediaAsset + correct physical original + required WebP image thumbnail or video poster + correct consumer relation. Check JPEG, PNG, WebP, supported MP4/WebM video, and all supported audio formats without mandatory audio preview.
- Large, non-progressive JPEG within product requirements; high-resolution progressive JPEG with accurate actionable rejection before memory exhaustion; video poster generation and processing timeout/exit cases; isolated runtime with Validation-like constrained memory. No arbitrary lowering of allowed dimensions/bytes.
- Duplicate original reuse, corrupted registered original/preview fails closed, absent or ambiguous preview blocks eligibility, no unexpected extra MediaAsset, no Gallery/Home rendering error.

**B — Interrupted/resumed upload and durable identity**
- 16 MiB chunks, changed middle bytes with identical name/size/timestamp/outer samples, all acknowledged chunk SHA-256 comparisons; completed old receipt vs changed local file; cancel only incomplete staged sessions.
- Partial chunk/manifest write, crash after partial unacknowledged chunk, lost finish response after original DB commit, idempotent retry, concurrent finalize/dedup, missing original/preview and long filename. Verify *DB rows + filesystem + notification* together, not only HTTP response.
- 48-hour staging expiry, >100 staged sessions, real failed unlink/failed cleanup, no active upload deletion and no stale quota reservation.

**C — References, deletion, Free Storage and orphans**
- Home active/inactive List direct media + List Rich Text; Custom Pages nested Image child Text/List and published flags; PublicMedia eligibility matches the canonical reader. Deleting a used medium must not discard unrelated child content or leave dangling references. Remaining references must roll back deletion.
- Physical deletion with original+own variants; queued deletion pointing at another asset's path, retained historical/Committed owner, conflicting keys, malformed owner/outbox entries: fail closed. Registered but unassigned media is **not** an orphan. Legitimate Undo/Publication-retained files are **not** zombies.
- Free Storage preserves all originals and mandatory thumbnails/posters (even unassigned), removes only authorized historical releases, ownerless canonical file and expired staging. Test missing/truncated thumbnails, no regeneration during HTTP requests, truncated stream, large streamed SHA256 integrity, partial cleanup with warning and correct counts, independently failing cleanup audit, and bounded outbox with >100 rows.

**D — Publication schema, browser and notifications**
- Migration `2026_11_03_120000_retire_media_variant_transform_profile` on fresh and pre-existing public/committed data: no transform_profile in new writer/model/schema, one active available preview per (asset,kind); duplicate available kinds force transaction rollback with bytes/checkpoints intact. Old published snapshot rows/restore semantics and original media must remain correct.
- PublicationSchemaGuard full default must still detect unrelated site_sections drift; scoped media-only migration must not fail due to that independent drift. Verify successful repeated local-web boot, Home admin and candidate statistics, Gallery/Artwork/Public views after migration, and no removed constant/column references.
- Batch/single failure notices (Storage, Gallery, Artwork, video) must state accurate saved-vs-linked outcome, reason and retry action, with exactly one durable message/header feedback as required; avoid false success on failed/no-op operation.
- Existing tests expecting deleted previews or direct UploadedFile values are likely stale and require Testing-owned reconciliation against current behavior rather than preservation of outdated expectations.

**Isolation:** Dedicated disposable database/media volume and authenticated local browser; never destructive tests against artist's persistent Validation data. Validation only after explicit authorization with recoverable database+media backup and read-only inventory. Changes by other Dev workers are separate acceptance groups to be merged by Testing, not duplicated here.

## 2026-11-03 — Local-web startup, unrelated Artwork migration
- User's `docker logs moeller-lars-local-web` showed `The use statement with non-compound name 'RuntimeException' has no effect` in `2026_11_03_150000_replace_artwork_date_with_components.php` line 6. Artwork date migration belongs to Dev Worker 4 (lease 7); not caused by media upload changes. Unblocked the cross-worker local preview failure by removing the redundant global `use RuntimeException;` import only. The existing unqualified `throw new RuntimeException` remains valid in a namespace-free PHP migration. No other Artwork semantics changed. Worker 4 should include regression/migration verification in its ongoing handoff; no runtime execution from this chat.
