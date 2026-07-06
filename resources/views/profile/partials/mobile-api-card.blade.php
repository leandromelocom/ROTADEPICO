<section>
    <header>
        <h2 class="profile-title">App Android Companion</h2>
        <p class="profile-copy">
            Gere um token mobile para o app Android enviar notificacoes da Uber e receber a decisao instantanea da corrida em segundos.
        </p>
    </header>

    @if (session('mobile_api_status'))
        <div class="status-box">{{ session('mobile_api_status') }}</div>
    @endif

    <article class="feature-card">
        <div class="spread-row">
            <div>
                <p class="metric-label">Endpoint mobile</p>
                <h3 class="card-title">{{ $mobileApiEndpoint }}</h3>
            </div>
            <span class="score-badge">{{ $user->hasMobileApiToken() ? 'Token ativo' : 'Token pendente' }}</span>
        </div>

        <div class="detail-row">
            <span>Token criado</span>
            <span>{{ $user->mobile_api_token_created_at?->format('d/m/Y H:i') ?? 'Nunca' }}</span>
        </div>
        <div class="detail-row">
            <span>Ultimo uso</span>
            <span class="sky">{{ $user->mobile_api_token_last_used_at?->format('d/m/Y H:i') ?? 'Nunca' }}</span>
        </div>
        <div class="detail-row">
            <span>Listener Android</span>
            <span class="sky">{{ $mobileListenerEndpoint }}</span>
        </div>
    </article>

    @if (session('mobile_api_token'))
        <article class="feature-card">
            <p class="metric-label">Token gerado agora</p>
            <h3 class="card-title">{{ session('mobile_api_token') }}</h3>
            <p class="profile-copy">
                Este valor deve ser usado como Bearer token no app Android. Se gerar outro, o anterior deixa de funcionar.
            </p>
        </article>
    @endif

    <article class="feature-card">
        <p class="metric-label">Fluxo automatico</p>
        <h3 class="card-title">Notificacao Uber -> decisao Rotadepico</h3>
        <p class="profile-copy">
            O app Android companion deve escutar a notificacao da Uber, enviar o texto bruto para <strong>{{ $mobileListenerEndpoint }}</strong> e mostrar o overlay com a resposta.
        </p>
        <div class="chip-group" style="margin-top: 18px;">
            <span class="small-chip">listener nativo</span>
            <span class="small-chip">overlay instantaneo</span>
            <span class="small-chip">bearer token</span>
        </div>
    </article>

    <div class="stack-actions">
        <form method="POST" action="{{ route('profile.mobile-token') }}">
            @csrf
            <x-primary-button>{{ $user->hasMobileApiToken() ? 'Gerar novo token mobile' : 'Gerar token mobile' }}</x-primary-button>
        </form>
    </div>
</section>
