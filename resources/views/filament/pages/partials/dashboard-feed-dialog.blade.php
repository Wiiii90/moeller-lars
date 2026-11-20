@if (isset($groupEntries))
    <div class="admin-detail-dialog admin-detail-dialog--feed admin-dashboard__group-details">
        @foreach ($groupEntries as $groupEntry)
            @php
                $memberBody = trim((string) ($groupEntry['body'] ?? ''));
                $memberTitle = trim((string) ($groupEntry['title'] ?? ''));
                $memberMessage = $memberBody === $memberTitle ? '' : $memberBody;
                $memberLink = $groupEntry['link'] ?? null;
                $memberLinkLabel = $groupEntry['link_label'] ?? null;
                $memberPath = is_string($memberLink) ? parse_url($memberLink, PHP_URL_PATH) : null;
                $memberAdminLink = is_string($memberPath)
                    && ($memberPath === '/admin' || str_starts_with($memberPath, '/admin/'));
            @endphp
            <section class="admin-dashboard__group-detail">
                <div class="admin-dashboard__group-detail-meta">
                    <time datetime="{{ $groupEntry['date'] }}">{{ $groupEntry['date_display'] }} {{ $groupEntry['time_display'] }}</time>
                    <span>{{ $groupEntry['status'] }}</span>
                </div>
                <strong>{{ $memberTitle }}</strong>
                @if ($memberMessage !== '')
                    <p>{{ $memberMessage }}</p>
                @endif
                @if (is_string($memberLink) && $memberLink !== '' && is_string($memberLinkLabel) && $memberLinkLabel !== '')
                    <a class="admin-detail-dialog__link" href="{{ $memberLink }}" @if ($memberAdminLink) wire:navigate @endif>{{ $memberLinkLabel }}</a>
                @endif
            </section>
        @endforeach
    </div>
@else
@php
    $body = trim((string) ($entry['body'] ?? ''));
    $title = trim((string) ($entry['title'] ?? ''));
    $message = $entry['type'] === 'notification' && $body === $title ? '' : $body;
    $linkPath = is_string($entry['link'] ?? null) ? parse_url($entry['link'], PHP_URL_PATH) : null;
    $linkNavigatesInAdmin = is_string($linkPath)
        && ($linkPath === '/admin' || str_starts_with($linkPath, '/admin/'));
@endphp

<div class="admin-detail-dialog admin-detail-dialog--feed">
    <dl class="admin-detail-dialog__meta admin-detail-dialog__meta--four">
        <div>
            <dt>{{ __('Type') }}</dt>
            <dd>{{ $entry['type_label'] }}</dd>
        </div>
        <div>
            <dt>{{ __('Date') }}</dt>
            <dd>{{ $entry['date_display'] }}</dd>
        </div>

        @if ($entry['type'] === 'contact')
            <div>
                <dt>{{ __('Time') }}</dt>
                <dd>{{ $entry['time_display'] }}</dd>
            </div>
            <div>
                <dt>{{ __('Sender') }}</dt>
                <dd class="admin-detail-dialog__sender">
                    <strong>{{ $entry['sender_name'] }}</strong>
                    <a href="mailto:{{ $entry['sender_email'] }}">{{ $entry['sender_email'] }}</a>
                </dd>
            </div>
        @elseif ($entry['type'] === 'notification')
            <div>
                <dt>{{ __('Time') }}</dt>
                <dd>{{ $entry['time_display'] }}</dd>
            </div>
            <div>
                <dt>{{ __('Status') }}</dt>
                <dd>{{ str_starts_with($entry['status'], 'Unread') ? 'Unread' : 'Read' }} — {{ ucfirst($entry['notification_status']) }}</dd>
            </div>
        @else
            <div>
                <dt>{{ __('Sender') }}</dt>
                <dd>{{ __('System') }}</dd>
            </div>
            <div>
                <dt>{{ __('Status') }}</dt>
                <dd>{{ $entry['status'] }}</dd>
            </div>
        @endif
    </dl>

    @if ($message !== '')
        <div class="admin-detail-dialog__field">
            <span>{{ __('Message') }}</span>
            @if (in_array($entry['type'], ['announcement', 'changelog'], true))
                <div class="admin-detail-dialog__release-notes">
                    @php
                        $releaseRenderer = app(\App\Domain\Content\SafeRichTextRenderer::class);
                        $releaseRenderer->assertValid($message, allowEmbeddedMedia: false);
                    @endphp
                    {!! $releaseRenderer->render($message) !!}
                </div>
            @else
                <p>{{ $message }}</p>
            @endif
        </div>
    @endif

    @if ($entry['type'] === 'contact')
        <p class="admin-detail-dialog__context">
            <span>{{ str_starts_with($entry['status'], 'Unread') ? 'Unread' : 'Read' }}</span>
            @if ($entry['mail_delivery_status'])
                <span aria-hidden="true">—</span>
                <span>Mail {{ strtolower($entry['mail_delivery_status']) }}@if ($entry['mail_delivered_at']) · {{ $entry['mail_delivered_at'] }}@endif</span>
            @endif
        </p>
    @endif

    @if (is_string($entry['related_key'] ?? null) && is_string($entry['related_label'] ?? null))
        <button type="button" class="admin-detail-dialog__related-link" wire:click="openRelatedFeedEntry('{{ $entry['related_key'] }}')">
            {{ $entry['related_label'] }}
        </button>
    @endif

    @if ($entry['type'] !== 'contact' && $entry['link'] !== null && $entry['link_label'] !== null)
        <a class="admin-detail-dialog__link" href="{{ $entry['link'] }}" @if ($linkNavigatesInAdmin) wire:navigate @endif>{{ $entry['link_label'] }}</a>
    @endif
</div>

@endif
