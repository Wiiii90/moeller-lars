@php
    $summary = $publication['summary'];
    $preflight = $publication['preflight'];
    $details = app(\App\Domain\Publication\PublicationService::class)->pendingDetails();
@endphp

<div class="admin-detail-dialog activity-publication-review">
    <dl class="admin-detail-dialog__meta">
        <div>
            <dt>Pending changes</dt>
            <dd>{{ number_format($summary['total']) }}</dd>
        </div>
        <div>
            <dt>Preflight</dt>
            <dd>{{ $preflight['label'] }}</dd>
        </div>
    </dl>

    @if ($summary['groups'] !== [])
        <div class="admin-detail-dialog__field">
            <span>What will publish</span>
            <div class="activity-publication-review__groups">
                @foreach ($summary['groups'] as $group)
                    <article>
                        <div>
                            <strong>{{ $group['area'] }}</strong>
                            <span>{{ number_format($group['count']) }}</span>
                        </div>
                        <small>{{ $group['entity'] }}</small>
                    </article>
                @endforeach
            </div>
        </div>
    @else
        <div class="admin-detail-dialog__field">
            <span>What will publish</span>
            <p>No working-state changes currently differ from the live snapshot.</p>
        </div>
    @endif

    @if ($details['rows'] !== [])
        <div class="admin-detail-dialog__field">
            <span>Changed records</span>
            <div class="activity-publication-review__records">
                @foreach ($details['rows'] as $row)
                    <article>
                        <div>
                            <strong>{{ $row['label'] }}</strong>
                            <span>{{ ucfirst($row['change']) }}</span>
                        </div>
                        <small>{{ $row['area'] }} · {{ $row['entity'] }} · #{{ $row['row_key'] }}</small>
                        @if ($row['fields'] !== [])
                            <small>Fields: {{ implode(', ', $row['fields']) }}</small>
                        @endif
                    </article>
                @endforeach
            </div>
            @if ($details['truncated'])
                <p class="admin-detail-dialog__context">Only the first {{ number_format(count($details['rows'])) }} changed records are shown.</p>
            @endif
        </div>
    @endif

    @if ($preflight['blockers'] !== [])
        <div class="admin-detail-dialog__field">
            <span>Blocking issues</span>
            <ul class="activity-publication-review__blockers">
                @foreach ($preflight['blockers'] as $blocker)
                    <li>{{ $blocker }}</li>
                @endforeach
            </ul>
        </div>
    @elseif ($summary['total'] > 0)
        <p class="admin-detail-dialog__context">
            <span>The current publication checks found no blocking issue.</span>
            <span aria-hidden="true">·</span>
            <span>Commit still uses the same server-side preflight.</span>
        </p>
    @endif
</div>
