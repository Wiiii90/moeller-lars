@php
    $changes = is_array($entry['change_items'] ?? null) ? $entry['change_items'] : [];
    $total = max(count($changes), (int) ($entry['change_count'] ?? 0));
    $target = trim((string) ($entry['change_target'] ?? ''));
@endphp

@if ($changes !== [])
    @if ($target !== '')
        <div class="admin-detail-dialog__field admin-dashboard__change-context">
            <span>{{ __('Affected object') }}</span>
            <p>{{ $target }}</p>
        </div>
    @endif
    <div class="admin-detail-dialog__field admin-dashboard__change-context">
        <span>{{ __('Changed fields') }}</span>
        <dl class="admin-dashboard__change-list">
            @foreach ($changes as $change)
                <div class="admin-dashboard__change-item">
                    <dt>{{ $change['label'] }}</dt>
                    <dd>
                        <dl class="admin-dashboard__change-values">
                            <div>
                                <dt>{{ __('Before') }}</dt>
                                <dd>{{ $change['before'] }}</dd>
                            </div>
                            <div>
                                <dt>{{ __('After') }}</dt>
                                <dd>{{ $change['after'] }}</dd>
                            </div>
                        </dl>
                    </dd>
                </div>
            @endforeach
        </dl>
        @if ($total > count($changes))
            <p class="admin-dashboard__change-rest">{{ $total - count($changes) }} {{ __('additional field changes') }}</p>
        @endif
    </div>
@endif
