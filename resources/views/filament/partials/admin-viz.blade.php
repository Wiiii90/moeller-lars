@vite('resources/js/admin-viz.js')

@if (request()->is('admin') || request()->is('admin/storage') || request()->is('admin/storage/*'))
    @vite('resources/js/admin-storage-viz.js')
@endif
