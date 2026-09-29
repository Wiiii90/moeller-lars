@php
    $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
    $changeSummary = is_array($event['change_summary'] ?? null) ? $event['change_summary'] : null;
    $orderingProjection = is_array($event['ordering_projection'] ?? null) ? $event['ordering_projection'] : null;
    unset($metadata['change_summary'], $metadata['target_label'], $metadata['ordering']);
    $publicationLabel = match ($event['publication_status'] ?? null) {
        'committed' => 'Committed',
        'pending' => 'Staged for next publish',
        'not_pending' => 'No current staged delta',
        default => 'Not publication-tracked',
    };
@endphp

<div class="admin-detail-dialog admin-detail-dialog--activity">
    <dl class="admin-detail-dialog__meta">
        <div>
            <dt>Area</dt>
            <dd>{{ $event['area'] }}</dd>
        </div>
        <div>
            <dt>Change type</dt>
            <dd>{{ $event['family'] }}</dd>
        </div>
        <div>
            <dt>Actor</dt>
            <dd>{{ $event['actor'] }}</dd>
        </div>
    </dl>

    <div class="admin-detail-dialog__field">
        <span>Change</span>
        <p>{{ $event['action'] }}</p>
    </div>

    <div class="admin-detail-dialog__field">
        <span>Target</span>
        <p>{{ $event['target'] }}</p>
    </div>

    @if (is_array($orderingProjection) && ($orderingProjection['event_count'] ?? 1) > 1)
        <div class="admin-detail-dialog__field">
            <span>Ordering sequence</span>
            <p>{{ $orderingProjection['event_count'] }} raw reorder operations reduced to one net ordering change.</p>
            @if (($orderingProjection['started_at'] ?? null) && ($orderingProjection['ended_at'] ?? null))
                <small>{{ $orderingProjection['started_at'] }} → {{ $orderingProjection['ended_at'] }}</small>
            @endif
        </div>
    @endif

    @if (is_array($changeSummary) && ($changeSummary['items'] ?? []) !== [])
        <div class="admin-detail-dialog__field">
            <span>Changed values</span>
            <dl class="admin-detail-dialog__meta">
                @foreach ($changeSummary['items'] as $change)
                    <div>
                        <dt>{{ $change['label'] }}</dt>
                        <dd>{{ $change['before'] }} → {{ $change['after'] }}</dd>
                    </div>
                @endforeach
            </dl>
            @if ($changeSummary['truncated'] ?? false)
                <small>{{ $changeSummary['count'] }} changes recorded; showing the first {{ count($changeSummary['items']) }}.</small>
            @endif
        </div>
    @endif

    <div class="admin-detail-dialog__field">
        <span>Publication</span>
        <p>{{ $publicationLabel }}</p>
        @if (($event['publication_status'] ?? null) === 'committed')
            <small>Commit {{ $event['checkpoint_short_hash'] ?? '#'.$event['checkpoint_id'] }} · {{ $event['checkpoint_at'] }}</small>
            @if ($event['checkpoint_message'])
                <small>{{ $event['checkpoint_message'] }}</small>
            @endif
        @endif
    </div>

    @if ($metadata !== [])
        <div class="admin-detail-dialog__field">
            <span>Event details</span>
            <dl class="admin-detail-dialog__meta">
                @foreach ($metadata as $key => $value)
                    <div>
                        <dt>{{ str((string) $key)->replace('_', ' ')->headline() }}</dt>
                        <dd>
                            @if (is_bool($value))
                                {{ $value ? 'Yes' : 'No' }}
                            @elseif (is_scalar($value) || $value === null)
                                {{ $value ?? '—' }}
                            @else
                                {{ json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        </div>
    @endif

    <p class="admin-detail-dialog__context">
        <span>{{ $event['timestamp'] }}</span>
        <span aria-hidden="true">·</span>
        <span>{{ $event['entity_type'] }} #{{ $event['entity_id'] }}</span>
        <span aria-hidden="true">·</span>
        <span>{{ $event['action_key'] }}</span>
    </p>
</div>
