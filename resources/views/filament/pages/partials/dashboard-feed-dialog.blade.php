@php
    $body = trim((string) ($entry['body'] ?? ''));
    $title = trim((string) ($entry['title'] ?? ''));
    $message = $entry['type'] === 'notification' && $body === $title ? '' : $body;
@endphp

<div class="admin-detail-dialog">
    <dl class="admin-detail-dialog__meta">
        <div>
            <dt>Type</dt>
            <dd>{{ $entry['type_label'] }}</dd>
        </div>
        <div>
            <dt>Date</dt>
            <dd>{{ $entry['date_display'] }}</dd>
        </div>

        @if ($entry['type'] === 'contact')
            <div>
                <dt>Sender</dt>
                <dd class="admin-detail-dialog__sender">
                    <strong>{{ $entry['sender_name'] }}</strong>
                    <a href="mailto:{{ $entry['sender_email'] }}">{{ $entry['sender_email'] }}</a>
                </dd>
            </div>
        @elseif ($entry['type'] === 'notification')
            <div>
                <dt>Status</dt>
                <dd>{{ str_starts_with($entry['status'], 'Unread') ? 'Unread' : 'Read' }} · {{ ucfirst($entry['notification_status']) }}</dd>
            </div>
        @elseif ($entry['link'] !== null && $entry['link_label'] !== null)
            <div>
                <dt>Reference</dt>
                <dd><a href="{{ $entry['link'] }}">{{ $entry['link_label'] }}</a></dd>
            </div>
        @endif
    </dl>

    <div class="admin-detail-dialog__field">
        <span>Title</span>
        <p>{{ $title !== '' ? $title : '—' }}</p>
    </div>

    <div class="admin-detail-dialog__field">
        <span>Message</span>
        <p>{{ $message !== '' ? $message : '—' }}</p>
    </div>

    @if ($entry['type'] === 'contact')
        <p class="admin-detail-dialog__context">
            <span>{{ str_starts_with($entry['status'], 'Unread') ? 'Unread' : 'Read' }}</span>
            @if ($entry['mail_delivery_status'])
                <span aria-hidden="true">·</span>
                <span>Mail {{ strtolower($entry['mail_delivery_status']) }}@if ($entry['mail_delivered_at']) · {{ $entry['mail_delivered_at'] }}@endif</span>
            @endif
        </p>
    @endif

    @if ($entry['type'] !== 'contact' && $entry['type'] !== 'notification' && $entry['link'] !== null && $entry['link_label'] !== null)
        <a class="admin-detail-dialog__link" href="{{ $entry['link'] }}">{{ $entry['link_label'] }}</a>
    @endif
</div>