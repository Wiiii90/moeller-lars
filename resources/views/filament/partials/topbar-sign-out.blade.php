<form class="admin-topbar-sign-out" method="POST" action="{{ filament()->getLogoutUrl() }}">
    @csrf

    <x-filament::button
        type="submit"
        color="gray"
        size="sm"
        icon="heroicon-m-arrow-left-on-rectangle"
    >
        Sign out
    </x-filament::button>
</form>
