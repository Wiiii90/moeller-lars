<form class="artist-topbar-sign-out" method="POST" action="{{ filament()->getLogoutUrl() }}">
    @csrf

    <button class="artist-topbar-sign-out__button" type="submit" aria-label="Sign out">
        <x-filament::icon icon="heroicon-m-arrow-left-on-rectangle" class="artist-topbar-sign-out__icon" />
        <span>Sign out</span>
    </button>
</form>
