<span class="media-type-placeholder" aria-hidden="true" style="display: inline-grid; gap: .38rem; place-items: center; color: var(--admin-faint); font-size: .58rem; line-height: 1.2; text-align: center;">
    @if ($kind === 'audio')
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <path d="M9 18V5l10-2v13" />
            <circle cx="6" cy="18" r="3" />
            <circle cx="16" cy="16" r="3" />
        </svg>
    @elseif ($kind === 'video')
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <rect x="3" y="5" width="18" height="14" rx="1" />
            <path d="m10 9 5 3-5 3V9Z" />
        </svg>
    @else
        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" aria-hidden="true">
            <rect x="4" y="4" width="16" height="16" rx="1" />
            <path d="m7 16 4-4 3 3 2-2 2 3" />
        </svg>
    @endif
    <span>{{ $typeLabel }}</span>
</span>
