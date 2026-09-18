@vite('resources/js/admin-viz.js')
@vite('resources/js/admin-password-tools.js')

@if (request()->is('admin') || request()->is('admin/storage') || request()->is('admin/storage/*'))
    @vite('resources/js/admin-storage-viz.js')
@endif
