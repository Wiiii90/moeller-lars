@php
    $metadata = is_array($event['metadata'] ?? null) ? $event['metadata'] : [];
    $publicationLabel = match ($event['publication_status'] ?? null) {
        'committed' => 'Committed',
        'pending' => 'Staged for next publish',
        'not_pending' => 'No current staged delta',
        default => 'Not publication-tracked',
    };
@endphp

<div class="admin-detail-dialog">
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
        <div>
            <dt>Date</dt>
            <dd>{{ $event['timestamp'] }}</dd>
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

    <div class="admin-detail-dialog__field">
        <span>Publication</span>
        <p>{{ $publicationLabel }}</p>
        @if (($event['publication_status'] ?? null) === 'committed')
            <small>Checkpoint #{{ $event['checkpoint_id'] }} · {{ $event['checkpoint_at'] }}</small>
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
        <span>{{ $event['entity_type'] }} #{{ $event['entity_id'] }}</span>
        <span aria-hidden="true">·</span>
        <span>{{ $event['action_key'] }}</span>
    </p>

    @if ($event['url'] !== null)
        <a class="admin-detail-dialog__link" href="{{ $event['url'] }}">Open record</a>
    @endif
</div>
