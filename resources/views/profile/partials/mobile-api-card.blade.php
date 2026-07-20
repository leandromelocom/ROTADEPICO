<section>
    <header>
        <h2 class="profile-title">App Android Companion</h2>
        <p class="profile-copy">
            Baixe o app Rotadepico Companion no celular e entre com o mesmo e-mail e senha desta conta. Nao precisa copiar nada aqui — o app busca o token sozinho ao fazer login.
        </p>
    </header>

    @if (session('mobile_api_status'))
        <div class="status-box">{{ session('mobile_api_status') }}</div>
    @endif

    <article class="feature-card">
        <div class="spread-row">
            <div>
                <p class="metric-label">Conexao do app</p>
                <h3 class="card-title">{{ $user->hasMobileApiToken() ? 'App conectado' : 'App ainda nao conectado' }}</h3>
            </div>
            <span class="score-badge">{{ $user->hasMobileApiToken() ? 'Token ativo' : 'Token pendente' }}</span>
        </div>

        <div class="detail-row">
            <span>Login feito em</span>
            <span>{{ $user->mobile_api_token_created_at?->format('d/m/Y H:i') ?? 'Nunca' }}</span>
        </div>
        <div class="detail-row">
            <span>Ultimo uso</span>
            <span class="sky">{{ $user->mobile_api_token_last_used_at?->format('d/m/Y H:i') ?? 'Nunca' }}</span>
        </div>
    </article>

    <article class="feature-card">
        <div class="spread-row">
            <div>
                <p class="metric-label">Dispositivos conectados</p>
                <h3 class="card-title">{{ $mobileDevices->count() }} ativo(s) recente(s)</h3>
            </div>
            <span class="score-badge">{{ $mobileDevices->isEmpty() ? 'Sem listener' : 'Mapeados' }}</span>
        </div>

        @forelse ($mobileDevices as $device)
            <div class="detail-row">
                <span>{{ $device->device_label ?: $device->device_id }}</span>
                <span class="sky">{{ $device->last_seen_at?->format('d/m/Y H:i') ?? 'Nunca' }}</span>
            </div>
        @empty
            <p class="profile-copy" style="margin-top: 16px;">
                Assim que o app Android enviar a primeira notificacao da Uber, o aparelho aparece aqui automaticamente.
            </p>
        @endforelse
    </article>

    <details style="margin-top: 18px;">
        <summary class="metric-label" style="cursor: pointer;">Avancado: gerar token manualmente</summary>

        <p class="profile-copy" style="margin-top: 12px;">
            So use isto se nao quiser digitar a senha no celular. Cole o valor abaixo como Bearer token no app, no campo "Opcoes avancadas".
        </p>

        <div class="detail-row">
            <span>Endpoint mobile</span>
            <span class="sky">{{ $mobileApiEndpoint }}</span>
        </div>
        <div class="detail-row">
            <span>Listener Android</span>
            <span class="sky">{{ $mobileListenerEndpoint }}</span>
        </div>

        @if (session('mobile_api_token'))
            <article class="feature-card" style="margin-top: 12px;">
                <p class="metric-label">Token gerado agora</p>
                <h3 class="card-title">{{ session('mobile_api_token') }}</h3>
                <p class="profile-copy">
                    Se gerar outro, o anterior deixa de funcionar.
                </p>
            </article>
        @endif

        <div class="stack-actions" style="margin-top: 12px;">
            <form method="POST" action="{{ route('profile.mobile-token') }}">
                @csrf
                <x-primary-button>{{ $user->hasMobileApiToken() ? 'Gerar novo token mobile' : 'Gerar token mobile' }}</x-primary-button>
            </form>
        </div>
    </details>
</section>
