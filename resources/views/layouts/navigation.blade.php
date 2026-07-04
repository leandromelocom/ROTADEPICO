<nav class="site-nav">
    <div class="nav-bar">
        <a href="{{ route('dashboard') }}" class="brand">
            <x-application-logo />
        </a>

        <div class="nav-links desktop-only">
            <a href="{{ route('dashboard') }}" class="nav-link {{ request()->routeIs('dashboard') ? 'is-active' : '' }}">Radar</a>
            <a href="{{ route('profile.edit') }}" class="nav-link {{ request()->routeIs('profile.*') ? 'is-active' : '' }}">Perfil</a>
        </div>

        <div class="nav-actions desktop-only">
            <div class="account">
                <button type="button" class="account-button" data-toggle-target="account-menu">
                    {{ Auth::user()->name }}
                </button>
                <div id="account-menu" class="account-menu" data-toggle-panel hidden>
                    <a href="{{ route('profile.edit') }}">Perfil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit">Sair</button>
                    </form>
                </div>
            </div>
        </div>

        <div class="mobile-only">
            <button type="button" class="menu-toggle" data-toggle-target="mobile-menu">Menu</button>
        </div>
    </div>

    <div id="mobile-menu" class="mobile-menu mobile-only" data-toggle-panel hidden>
        <a href="{{ route('dashboard') }}" class="mobile-link">Radar</a>
        <a href="{{ route('profile.edit') }}" class="mobile-link">Perfil</a>
        <div class="mobile-user">
            <strong>{{ Auth::user()->name }}</strong>
            <span>{{ Auth::user()->email }}</span>
        </div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="mobile-link">Sair</button>
        </form>
    </div>
</nav>
