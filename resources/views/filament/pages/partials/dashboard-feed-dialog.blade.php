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
                <dt>Name</dt>
                <dd>{{ $entry['sender_name'] }}</dd>
            </div>
            <div>
                <dt>E-Mail</dt>
                <dd><a href="mailto:{{ $entry['sender_email'] }}">{{ $entry['sender_email'] }}</a></dd>
            </div>
            <div>
                <dt>Status</dt>
                <dd>{{ $entry['status'] }}</dd>
            </div>
            <div>
                <dt>Mail delivery</dt>
                <dd>
                    {{ ucfirst($entry['mail_delivery_status']) }}
                    @if ($entry['mail_delivered_at'])
                        · {{ $entry['mail_delivered_at'] }}
                    @endif
                </dd>
            </div>
        @endif
    </dl>

    <div class="admin-detail-dialog__message">
        <span>Message</span>
        <p>{{ $entry['body'] }}</p>
    </div>

    @if ($entry['link'] !== null && $entry['link_label'] !== null)
        <a class="admin-action" href="{{ $entry['link'] }}">{{ $entry['link_label'] }}</a>
    @endif
</div>
