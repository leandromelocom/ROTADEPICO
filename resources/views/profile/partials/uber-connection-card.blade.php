<section>
    <header>
        <h2 class="profile-title">Uber API</h2>
        <p class="profile-copy">
            Vincule sua conta Uber para sincronizar automaticamente o historico oficial de corridas do motorista.
            Esta integracao usa a Drivers API da Uber e depende de credenciais aprovadas.
        </p>
    </header>

    @if (session('uber_status'))
        <div class="status-box">{{ session('uber_status') }}</div>
    @endif

    @if ($uberConnection)
        <div class="list-stack">
            <article class="feature-card">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Conta vinculada</p>
                        <h3 class="card-title">{{ $uberConnection->first_name }} {{ $uberConnection->last_name }}</h3>
                    </div>
                    <span class="score-badge">Uber conectada</span>
                </div>
                <p class="profile-copy">{{ $uberConnection->email ?: 'E-mail nao retornado pela Uber.' }}</p>
                <div class="detail-row">
                    <span>{{ $tripSummary->trips_count ?? 0 }} corridas importadas</span>
                    <span class="sky">R$ {{ number_format((float) ($tripSummary->gross_fare ?? 0), 2, ',', '.') }}</span>
                </div>
                <div class="detail-row">
                    <span>Ultima sincronizacao</span>
                    <span>{{ $uberConnection->last_synced_at?->format('d/m/Y H:i') ?? 'Nunca' }}</span>
                </div>
            </article>
        </div>

        <div class="stack-actions">
            <form method="POST" action="{{ route('integrations.uber.sync') }}">
                @csrf
                <x-primary-button>Sincronizar corridas</x-primary-button>
            </form>

            <form method="POST" action="{{ route('integrations.uber.destroy') }}">
                @csrf
                @method('DELETE')
                <x-danger-button>Desvincular Uber</x-danger-button>
            </form>
        </div>
    @else
        <article class="feature-card">
            <p class="metric-label">Integracao oficial</p>
            <h3 class="card-title">Pronto para conectar</h3>
            <p class="profile-copy">
                Ao conectar, o sistema passara a importar viagens, valores e horarios diretamente da conta Uber autorizada pelo motorista.
            </p>
            <div class="chip-group">
                <span class="chip">partner.profile</span>
                <span class="chip">partner.trips</span>
            </div>
        </article>

        <div class="stack-actions">
            <a href="{{ route('integrations.uber.redirect') }}" class="solid-button">Conectar Uber</a>
        </div>
    @endif
</section>
