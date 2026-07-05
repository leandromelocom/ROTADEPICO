<x-app-layout>
    <section class="dashboard-shell">
        <div class="dashboard-grid">
            <section class="panel">
                <div class="spread-row">
                    <div>
                        <div class="section-eyebrow">Painel administrativo</div>
                        <h1 class="section-title">Operacao do Rotadepico</h1>
                        <p class="section-copy">
                            Visao consolidada da base de motoristas, assinatura, onboarding e conexao com a Uber.
                        </p>
                    </div>
                    <span class="small-chip">Admin</span>
                </div>

                <div class="metric-grid" style="margin-top: 18px;">
                    <article class="metric-card">
                        <div class="metric-label">Motoristas cadastrados</div>
                        <div class="metric-value">{{ $stats['drivers_total'] }}</div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-label">Assinaturas ativas</div>
                        <div class="metric-value">{{ $stats['active_subscriptions'] }}</div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-label">Inadimplentes</div>
                        <div class="metric-value">{{ $stats['overdue_subscriptions'] }}</div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-label">Receita mensal</div>
                        <div class="metric-value">R$ {{ number_format($stats['monthly_revenue_cents'] / 100, 2, ',', '.') }}</div>
                    </article>
                </div>
            </section>

            <aside class="summary-grid">
                <article class="stat-card">
                    <p class="metric-label">Uber conectada</p>
                    <div class="metric-value">{{ $stats['uber_connected'] }}</div>
                    <p class="profile-copy">Motoristas com integracao Uber ativa.</p>
                </article>
                <article class="stat-card">
                    <p class="metric-label">Onboarding concluido</p>
                    <div class="metric-value">{{ $stats['onboarding_completed'] }}</div>
                    <p class="profile-copy">Base pronta para usar o radar principal.</p>
                </article>
                <article class="stat-card">
                    <p class="metric-label">Taxa Uber</p>
                    <div class="metric-value">
                        {{ $stats['drivers_total'] > 0 ? round(($stats['uber_connected'] / $stats['drivers_total']) * 100) : 0 }}%
                    </div>
                    <p class="profile-copy">Percentual da base com conta Uber vinculada.</p>
                </article>
                <article class="stat-card">
                    <p class="metric-label">Taxa onboarding</p>
                    <div class="metric-value">
                        {{ $stats['drivers_total'] > 0 ? round(($stats['onboarding_completed'] / $stats['drivers_total']) * 100) : 0 }}%
                    </div>
                    <p class="profile-copy">Motoristas que chegaram prontos ao dashboard.</p>
                </article>
            </aside>
        </div>

        <section class="panel" style="margin-top: 18px;">
            <div class="spread-row">
                <div>
                    <p class="metric-label">Prontidao de producao</p>
                    <h2 class="section-title">Ambiente e integracoes</h2>
                    <p class="section-copy">Leitura rapida do que ainda falta fechar antes de operar em producao com mais seguranca.</p>
                </div>
                <span class="state-badge {{ $readiness['ready'] ? 'up' : 'flat' }}">{{ $readiness['ready'] ? 'pronto' : 'ajustes pendentes' }}</span>
            </div>

            <div class="metric-grid" style="margin-top: 18px;">
                <article class="metric-card">
                    <div class="metric-label">Checks OK</div>
                    <div class="metric-value">{{ $readiness['summary']['ok'] }}</div>
                </article>
                <article class="metric-card">
                    <div class="metric-label">Warnings</div>
                    <div class="metric-value">{{ $readiness['summary']['warnings'] }}</div>
                </article>
                <article class="metric-card">
                    <div class="metric-label">Errors</div>
                    <div class="metric-value">{{ $readiness['summary']['errors'] }}</div>
                </article>
                <article class="metric-card">
                    <div class="metric-label">Comando</div>
                    <div class="metric-value">artisan</div>
                    <p class="profile-copy">`app:production-check`</p>
                </article>
            </div>

            <div class="admin-driver-list" style="margin-top: 18px;">
                @foreach ($readiness['checks'] as $check)
                    <article class="admin-driver-card">
                        <div class="spread-row">
                            <div>
                                <p class="metric-label">{{ $check['label'] }}</p>
                                <h3 class="card-title">{{ strtoupper($check['status']) }}</h3>
                            </div>
                            <span class="state-badge {{ $check['status'] === 'ok' ? 'up' : ($check['status'] === 'error' ? 'down' : 'flat') }}">
                                {{ $check['status'] }}
                            </span>
                        </div>
                        <p class="profile-copy">{{ $check['message'] }}</p>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="panel" style="margin-top: 18px;">
            <div class="spread-row">
                <div>
                    <p class="metric-label">Base operacional</p>
                    <h2 class="section-title">Motoristas da plataforma</h2>
                    <p class="section-copy">Leitura direta de status comercial e operacional para acompanhar adesao, ativacao e risco de churn.</p>
                </div>
                <span class="small-chip">{{ $drivers->count() }} motoristas</span>
            </div>

            <div class="admin-driver-list" style="margin-top: 18px;">
                @foreach ($drivers as $driver)
                    <article class="admin-driver-card">
                        <div class="spread-row">
                            <div>
                                <h3 class="card-title">{{ $driver->name }}</h3>
                                <p class="profile-copy">{{ $driver->email }}</p>
                            </div>
                            <span class="state-badge {{ $driver->subscription?->status === 'active' ? 'up' : ($driver->subscription?->status === 'overdue' ? 'down' : 'flat') }}">
                                {{ $driver->subscription?->status ?? 'sem assinatura' }}
                            </span>
                        </div>

                        <div class="admin-driver-meta">
                            <span>{{ $driver->city ?: 'Cidade pendente' }}</span>
                            <span>{{ $driver->vehicle_type ?: 'Veiculo pendente' }}</span>
                            <span>{{ $driver->work_shift ?: 'Turno pendente' }}</span>
                        </div>

                        <div class="chip-group">
                            <span class="chip {{ $driver->onboarding_completed_at ? 'chip-success' : '' }}">
                                {{ $driver->onboarding_completed_at ? 'Onboarding concluido' : 'Onboarding pendente' }}
                            </span>
                            <span class="chip {{ $driver->uberConnection ? 'chip-success' : '' }}">
                                {{ $driver->uberConnection ? 'Uber conectada' : 'Uber nao conectada' }}
                            </span>
                            <span class="chip {{ $driver->location_permission_granted_at ? 'chip-success' : '' }}">
                                {{ $driver->location_permission_granted_at ? 'Localizacao autorizada' : 'Localizacao pendente' }}
                            </span>
                        </div>

                        <div class="admin-driver-footer">
                            <span>Plano {{ $driver->subscription?->plan_name ?? 'nao ativado' }}</span>
                            <span>
                                @if ($driver->subscription?->renews_at)
                                    Renova em {{ $driver->subscription->renews_at->format('d/m/Y') }}
                                @elseif ($driver->subscription?->status === 'overdue')
                                    Regularizacao pendente
                                @else
                                    Sem renovacao ativa
                                @endif
                            </span>
                            <span>
                                @if ($driver->subscription)
                                    R$ {{ number_format($driver->subscription->price_cents / 100, 2, ',', '.') }}/mes
                                @else
                                    Sem receita
                                @endif
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </section>
</x-app-layout>
