<div class="admin-detail-dialog activity-commit-dialog">
    <dl class="admin-detail-dialog__meta">
        <div>
            <dt>Status</dt>
            <dd>{{ $commit['live'] ? 'LIVE' : ($commit['restorable'] ? 'Restorable' : 'Historical metadata') }}</dd>
        </div>
        <div>
            <dt>Commit</dt>
            <dd><code>{{ $commit['short_hash'] }}</code></dd>
        </div>
        <div>
            <dt>Published</dt>
            <dd>{{ $commit['timestamp'] }}</dd>
        </div>
        <div>
            <dt>Actor</dt>
            <dd>{{ $commit['actor'] }}</dd>
        </div>
        <div>
            <dt>Changes</dt>
            <dd>{{ number_format($commit['change_count']) }}</dd>
        </div>
        <div>
            <dt>Activities</dt>
            <dd>{{ number_format($commit['activity_count']) }}</dd>
        </div>
    </dl>

    <div class="admin-detail-dialog__field">
        <span>Message</span>
        <p>{{ $commit['message'] ?? 'No commit message' }}</p>
    </div>

    <div class="admin-detail-dialog__field">
        <span>Lineage</span>
        <p>
            Parent: {{ $commit['parent_short_hash'] ?? '—' }}
            @if ($commit['source_hash'])
                · Source: {{ substr($commit['source_hash'], 0, 10) }}
            @endif
        </p>
    </div>

    <div class="admin-detail-dialog__field">
        <span>Integrity</span>
        <p class="activity-commit-dialog__hashes">
            <code title="{{ $commit['hash'] }}">Commit {{ $commit['hash'] ?: 'legacy' }}</code>
            <code title="{{ $commit['snapshot_hash'] ?? '' }}">Snapshot {{ $commit['snapshot_hash'] ?? 'not retained' }}</code>
        </p>
    </div>

    @if ($commit['legacy'])
        <p class="admin-detail-dialog__context">
            This commit predates retained publication snapshots. Its metadata remains permanent, but restoring it would be unsafe and is therefore unavailable.
        </p>
    @elseif (! $commit['restorable'])
        <p class="admin-detail-dialog__context">
            The snapshot is retained, but its publication schema no longer matches the current schema. It remains inspectable but cannot be restored automatically.
        </p>
    @endif

    <div class="admin-detail-dialog__field">
        <span>Included activity</span>
        @if ($commit['activities'] !== [])
            <div class="activity-commit-dialog__activities">
                @foreach ($commit['activities'] as $activity)
                    <article>
                        <strong>{{ $activity['action'] }}</strong>
                        <span>{{ $activity['area'] }} · {{ $activity['target'] }}</span>
                        <small>
                            {{ $activity['actor'] }} · {{ $activity['timestamp'] }}
                            @if (($activity['ordering_projection']['event_count'] ?? 1) > 1)
                                · {{ number_format($activity['ordering_projection']['event_count']) }} reorder operations
                            @endif
                        </small>
                    </article>
                @endforeach
            </div>
        @else
            <p>—</p>
        @endif
    </div>
</div>
