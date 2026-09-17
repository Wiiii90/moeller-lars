@php
    use App\Domain\Admin\AdminActionReceiptRetentionPolicy;
    use App\Domain\Media\MediaCapacityService;
    use App\Domain\Media\MediaStorageUnits;

    $siteSnapshot = app(MediaCapacityService::class)->cachedSnapshotIfAvailable();
    $siteMeasured = is_array($siteSnapshot) && ($siteSnapshot['measurement_available'] ?? false) === true;
    $databaseStorage = $siteMeasured && is_array($siteSnapshot['database'] ?? null)
        ? $siteSnapshot['database']
        : [];

    $formatStorage = static fn (mixed $bytes): string => MediaStorageUnits::formatBytes(
        is_int($bytes) ? $bytes : (is_numeric($bytes) ? (int) $bytes : null),
    );

    $publicationLogical = $databaseStorage['publication_logical_payload_bytes'] ?? null;
    $publicationDeduplication = $databaseStorage['publication_deduplication_percent'] ?? null;
    $undoBudget = AdminActionReceiptRetentionPolicy::MAX_BYTES_PER_USER;
@endphp

@if ($siteMeasured)
    <section class="admin-storage__attention" aria-label="Site storage account">
        <div class="admin-storage__attention-row">
            <span>Site used</span>
            <strong>{{ $formatStorage($siteSnapshot['site_used_bytes'] ?? null) }} of {{ $formatStorage($siteSnapshot['quota_bytes'] ?? null) }}</strong>
        </div>
        <div class="admin-storage__attention-row">
            <span>Generated variants</span>
            <strong>{{ $formatStorage($siteSnapshot['generated_bytes'] ?? null) }} · rebuildable</strong>
        </div>
        <div class="admin-storage__attention-row">
            <span>Live database</span>
            <strong>{{ $formatStorage($databaseStorage['live_content_bytes'] ?? null) }}</strong>
        </div>
        <div class="admin-storage__attention-row">
            <span>Activity history</span>
            <strong>{{ $formatStorage($databaseStorage['activity_bytes'] ?? null) }} · permanent metadata</strong>
        </div>
        <div class="admin-storage__attention-row">
            <span>Undo history</span>
            <strong>{{ $formatStorage($databaseStorage['undo_bytes'] ?? null) }} of {{ $formatStorage($undoBudget) }} budget</strong>
        </div>
        <div class="admin-storage__attention-row">
            <span>Publication history</span>
            <strong>
                {{ $formatStorage($databaseStorage['publication_bytes'] ?? null) }}
                @if (is_numeric($publicationLogical) && (int) $publicationLogical > 0 && is_numeric($publicationDeduplication))
                    · {{ number_format((float) $publicationDeduplication, 1) }}% payload deduplication
                @endif
            </strong>
        </div>
        <div class="admin-storage__attention-row">
            <span>Other application data</span>
            <strong>{{ $formatStorage($databaseStorage['other_bytes'] ?? null) }}</strong>
        </div>
        <div class="admin-storage__attention-row">
            <span>Physical database footprint</span>
            <strong>{{ $formatStorage($databaseStorage['physical_database_bytes'] ?? null) }} · operational, not billed directly</strong>
        </div>
        <livewire:admin.site-storage-reclaim-control />
    </section>
@endif
