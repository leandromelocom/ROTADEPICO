<x-guest-layout>
    <x-auth-session-status :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div class="auth-intro">
            <p class="section-eyebrow" style="background: rgba(56, 189, 248, 0.1); border-color: rgba(56, 189, 248, 0.16); color: #7dd3fc;">Acesso do motorista</p>
            <h1>Entrar no radar</h1>
            <p class="section-copy">Abra o painel mobile e veja onde sua proxima hora tem mais chance de render.</p>
        </div>

        <div class="field">
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="field">
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" type="password" name="password" required autocomplete="current-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="inline-check">
            <label for="remember_me" class="inline-check">
                <input id="remember_me" type="checkbox" name="remember">
                <span>Lembrar acesso</span>
            </label>
        </div>

        <div class="form-actions">
            @if (Route::has('password.request'))
                <a class="text-link" href="{{ route('password.request') }}">
                    Esqueceu a senha?
                </a>
            @endif

            <x-primary-button>Entrar</x-primary-button>
        </div>
    </form>
</x-guest-layout>
