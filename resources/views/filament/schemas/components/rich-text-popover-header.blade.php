{{-- One actual Admin header-action control family for all five editor popovers. --}}
<div class="admin-rich-text-popover__header">
    <span>{{ $title }}</span>
    <div class="admin-rich-text-popover__header-actions">
        @if (($removeAction ?? null) === 'link')
            <x-filament::icon-button icon="heroicon-o-link-slash" size="sm" color="gray"
                class="admin-dialog__header-action" :label="__('Remove link')"
                x-show="linkIsExisting" x-on:click.stop="removeLink()" />
        @elseif (($removeAction ?? null) === 'color')
            <x-filament::icon-button icon="heroicon-o-no-symbol" size="sm" color="gray"
                class="admin-dialog__header-action" :label="__('Remove color')"
                x-on:click.stop="applyTextColor(true)" />
        @endif
        <x-filament::icon-button icon="heroicon-o-x-mark" size="sm" color="gray"
            class="admin-dialog__header-action" :label="__('Close')"
            x-on:click.stop="{{ $closeAction }}" />
    </div>
</div>
