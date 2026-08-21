<form class="artist-topbar-sign-out" method="POST" action="{{ filament()->getLogoutUrl() }}">
    @csrf

    <x-filament::icon-button
        color="gray"
        icon="heroicon-m-arrow-left-on-rectangle"
        icon-alias="panels::user-menu.logout-button"
        label="Sign out"
        type="submit"
    />
</form>
