{{-- General supplies its Working page; all preview behavior is shared. --}}
<x-admin.live-preview
    class="general-appearance-stage__preview admin-visual-stage__pane"
    wire:key="general-live-preview"
    :url="route('preview.home')"
    frame-title="Interactive live preview of the public homepage"
    :refresh-on-appearance="true"
/>
