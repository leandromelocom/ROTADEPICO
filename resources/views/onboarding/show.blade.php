<x-app-layout>
    <x-slot name="header">
        <div class="app-shell">
            <div class="section-eyebrow">Onboarding self-service</div>
            <h1 class="section-title">Ative sua conta de motorista</h1>
        </div>
    </x-slot>

    <section class="dashboard-shell">
        @if (session('onboarding_status'))
            <div class="status-box">{{ session('onboarding_status') }}</div>
        @endif

        <div class="onboarding-grid">
            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Etapa 1</p>
                        <h2 class="section-title">Perfil operacional</h2>
                        <p class="section-copy">O proprio motorista completa seus dados base para o radar personalizar turnos, veiculo e cidade.</p>
                    </div>
                    <span class="state-badge {{ $checklist['profile'] ? 'up' : 'flat' }}">{{ $checklist['profile'] ? 'ok' : 'pendente' }}</span>
                </div>

                <form method="POST" action="{{ route('onboarding.profile') }}">
                    @csrf
                    <div class="field-grid">
                        <div class="field">
                            <x-input-label for="phone" value="WhatsApp" />
                            <x-text-input id="phone" name="phone" type="text" :value="old('phone', $user->phone)" required />
                        </div>
                        <div class="field">
                            <x-input-label for="city" value="Cidade" />
                            <x-text-input id="city" name="city" type="text" :value="old('city', $user->city)" required />
                        </div>
                    </div>

                    <div class="field-grid">
                        <div class="field">
                            <x-input-label for="vehicle_type" value="Veiculo" />
                            <select id="vehicle_type" name="vehicle_type" class="field-control" required>
                                <option value="Carro" @selected(old('vehicle_type', $user->vehicle_type) === 'Carro')>Carro</option>
                                <option value="Moto" @selected(old('vehicle_type', $user->vehicle_type) === 'Moto')>Moto</option>
                                <option value="SUV" @selected(old('vehicle_type', $user->vehicle_type) === 'SUV')>SUV</option>
                            </select>
                        </div>
                        <div class="field">
                            <x-input-label for="work_shift" value="Turno principal" />
                            <select id="work_shift" name="work_shift" class="field-control" required>
                                <option value="Manha" @selected(old('work_shift', $user->work_shift) === 'Manha')>Manha</option>
                                <option value="Tarde" @selected(old('work_shift', $user->work_shift) === 'Tarde')>Tarde</option>
                                <option value="Noite" @selected(old('work_shift', $user->work_shift) === 'Noite')>Noite</option>
                                <option value="Madrugada" @selected(old('work_shift', $user->work_shift) === 'Madrugada')>Madrugada</option>
                                <option value="Flexivel" @selected(old('work_shift', $user->work_shift) === 'Flexivel')>Flexivel</option>
                            </select>
                        </div>
                    </div>

                    <div class="stack-actions">
                        <x-primary-button>Salvar perfil</x-primary-button>
                    </div>
                </form>
            </section>

            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Etapa 2</p>
                        <h2 class="section-title">Assinatura mensal</h2>
                        <p class="section-copy">Plano unico recorrente para liberar o radar, mapa de regioes e sincronizacao com a Uber.</p>
                    </div>
                    <span class="state-badge {{ $checklist['subscription'] ? 'up' : 'flat' }}">{{ $checklist['subscription'] ? 'ativa' : 'pendente' }}</span>
                </div>

                <article class="pricing-hero">
                    <div>
                        <p class="metric-label">Plano Mensal Pro</p>
                        <h3 class="hero-price">R$ 39,90<span>/mes</span></h3>
                        <p class="profile-copy">Checkout recorrente via Asaas, com pagamento hospedado e liberacao automatica quando a cobranca for confirmada.</p>
                    </div>
                    <div class="chip-group">
                        <span class="chip">Mapa de regioes</span>
                        <span class="chip">Radar preditivo</span>
                        <span class="chip">Conexao Uber</span>
                    </div>
                </article>

                @if ($subscription)
                    <div class="detail-row">
                        <span>Status {{ $subscription->status }}</span>
                        <span class="sky">
                            @if ($subscription->renews_at)
                                Renova em {{ $subscription->renews_at->format('d/m/Y') }}
                            @elseif ($subscription->provider === 'asaas')
                                Aguardando confirmacao da Asaas
                            @endif
                        </span>
                    </div>
                @endif

                @unless ($checklist['subscription'])
                    <form method="POST" action="{{ route('onboarding.subscription') }}" class="stack-actions">
                        @csrf
                        <x-primary-button>{{ $subscription?->status === 'pending' ? 'Gerar novo checkout Asaas' : 'Assinar com Asaas' }}</x-primary-button>
                    </form>
                    <p class="profile-copy">O motorista faz o proprio pagamento no link seguro da Asaas. A assinatura fica pendente ate o webhook confirmar o recebimento.</p>
                @endunless
            </section>

            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Etapa 3</p>
                        <h2 class="section-title">Localizacao e Uber</h2>
                        <p class="section-copy">A localizacao e obrigatoria para o mapa. A conexao Uber e recomendada para enriquecer os dados oficiais de corridas.</p>
                    </div>
                    <span class="state-badge {{ $checklist['location'] ? 'up' : 'flat' }}">{{ $checklist['location'] ? 'localizacao ok' : 'localizacao pendente' }}</span>
                </div>

                <div class="list-stack">
                    <article class="feature-card">
                        <p class="metric-label">Permissao de localizacao</p>
                        <p class="profile-copy">Ao ativar, o dashboard passa a sugerir melhor deslocamento e regioes quentes perto de voce.</p>
                        @if ($user->location_permission_granted_at)
                            <div class="detail-row">
                                <span>Permissao registrada</span>
                                <span class="sky">{{ $user->location_permission_granted_at->format('d/m/Y H:i') }}</span>
                            </div>
                        @else
                            <form method="POST" action="{{ route('onboarding.location') }}" class="stack-actions">
                                @csrf
                                <x-primary-button>Ja autorizei a localizacao</x-primary-button>
                            </form>
                        @endif
                    </article>

                    <article class="feature-card">
                        <p class="metric-label">Conexao Uber</p>
                        <p class="profile-copy">{{ $uberConnection ? 'Conta Uber vinculada com sucesso.' : 'Conecte sua conta Uber para importar corridas oficiais.' }}</p>
                        <div class="detail-row">
                            <span>{{ $uberConnection ? ($uberConnection->email ?: 'Conta conectada') : 'Recomendado' }}</span>
                            <span class="sky">{{ $checklist['uber'] ? 'Uber conectada' : 'Opcional' }}</span>
                        </div>
                        @unless ($checklist['uber'])
                            <div class="stack-actions">
                                <a href="{{ route('integrations.uber.redirect') }}" class="solid-button">Conectar Uber</a>
                            </div>
                        @endunless
                    </article>
                </div>
            </section>

            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Finalizacao</p>
                        <h2 class="section-title">Entrar no radar</h2>
                        <p class="section-copy">Quando perfil, localizacao e assinatura estiverem prontos, o motorista libera o acesso ao painel principal.</p>
                    </div>
                    <span class="small-chip">{{ collect($checklist)->filter()->count() }}/4 itens</span>
                </div>

                <div class="chip-group">
                    <span class="chip {{ $checklist['profile'] ? 'chip-success' : '' }}">Perfil</span>
                    <span class="chip {{ $checklist['subscription'] ? 'chip-success' : '' }}">Assinatura</span>
                    <span class="chip {{ $checklist['location'] ? 'chip-success' : '' }}">Localizacao</span>
                    <span class="chip {{ $checklist['uber'] ? 'chip-success' : '' }}">Uber</span>
                </div>

                <form method="POST" action="{{ route('onboarding.finish') }}" class="stack-actions">
                    @csrf
                    <x-primary-button>Concluir onboarding</x-primary-button>
                </form>
            </section>
        </div>
    </section>
</x-app-layout>
