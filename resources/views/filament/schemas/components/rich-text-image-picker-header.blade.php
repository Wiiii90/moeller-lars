<div class="admin-rich-text-link-dropdown__header">
    <span>{{ __('Image from Storage') }}</span>
    <div class="admin-rich-text-link-dropdown__header-actions">
        <x-filament::icon-button
            icon="heroicon-o-x-mark"
            size="sm"
            color="gray"
            :label="__('Close')"
            x-on:click.stop="closeRichPanel('image')"
        />
    </div>
</div>
