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
            <dt>Activity events</dt>
            <dd>{{ number_format($commit['event_count']) }}</dd>
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
        @if ($commit['events'] !== [])
            <div class="activity-commit-dialog__events">
                @foreach ($commit['events'] as $event)
                    <article>
                        <strong>{{ $event['action'] }}</strong>
                        <span>{{ $event['area'] }} · {{ $event['entity_type'] }} #{{ $event['entity_id'] }}</span>
                        <small>{{ $event['actor'] }} · {{ $event['timestamp'] }}</small>
                    </article>
                @endforeach
            </div>
        @else
            <p>—</p>
        @endif
    </div>
</div>
