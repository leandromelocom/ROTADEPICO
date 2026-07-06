<section>
    <header>
        <h2 class="profile-title">Assinatura</h2>
        <p class="profile-copy">
            Controle recorrencia, acompanhe cobrancas e veja quando o acesso pode ser bloqueado por inadimplencia.
        </p>
    </header>

    @if (session('subscription_status'))
        <div class="status-box">{{ session('subscription_status') }}</div>
    @endif

    @if ($subscription)
        <article class="feature-card">
            <div class="spread-row">
                <div>
                    <p class="metric-label">{{ $subscription->plan_name }}</p>
                    <h3 class="card-title">
                        @if ($subscription->status === 'trialing')
                            Trial gratis ativo
                        @else
                            R$ {{ number_format($subscription->price_cents / 100, 2, ',', '.') }}/mes
                        @endif
                    </h3>
                </div>
                <span class="state-badge {{ in_array($subscription->status, ['active', 'trialing'], true) ? 'up' : ($subscription->status === 'overdue' ? 'down' : 'flat') }}">
                    {{ $subscription->status }}
                </span>
            </div>

            <div class="detail-row">
                <span>Gateway {{ strtoupper($subscription->provider ?? 'manual') }}</span>
                <span class="sky">
                    @if ($subscription->status === 'trialing' && $subscription->trial_ends_at)
                        Trial ate {{ $subscription->trial_ends_at->format('d/m/Y') }}
                    @elseif ($subscription->renews_at)
                        Proxima referencia {{ $subscription->renews_at->format('d/m/Y') }}
                    @else
                        Sem proxima referencia definida
                    @endif
                </span>
            </div>

            @if ($subscription->status === 'trialing')
                <p class="profile-copy" style="margin-top: 12px;">
                    Seu acesso esta liberado pelo periodo promocional. Configure a cobranca recorrente antes do fim do trial para nao interromper o radar.
                </p>
            @endif

            @if ($subscription->isBlocked())
                <p class="profile-copy" style="margin-top: 12px;">
                    O acesso ao radar fica bloqueado enquanto a assinatura nao estiver ativa. Estados como `overdue`, `inactive` e `canceled` exigem regularizacao.
                </p>
            @endif

            <div class="stack-actions" style="margin-top: 16px;">
                @if ($subscription->canPause())
                    <form method="POST" action="{{ route('subscription.pause') }}">
                        @csrf
                        <x-danger-button>Pausar recorrencia</x-danger-button>
                    </form>
                @endif

                @if ($subscription->canReactivate())
                    <form method="POST" action="{{ route('subscription.reactivate') }}">
                        @csrf
                        <x-primary-button>Reativar assinatura</x-primary-button>
                    </form>
                @endif

                @if ($subscription->canRegularize() && $subscription->charges->first()?->invoice_url)
                    <a href="{{ $subscription->charges->first()->invoice_url }}" class="solid-button" target="_blank" rel="noreferrer">
                        Regularizar cobranca
                    </a>
                @endif
            </div>
        </article>

        <div class="list-stack" style="margin-top: 18px;">
            <article class="feature-card">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Historico de cobrancas</p>
                        <h3 class="card-title">Ultimos eventos da Asaas</h3>
                    </div>
                    <span class="small-chip">{{ $subscription->charges->count() }} eventos</span>
                </div>

                <div class="charge-history-list" style="margin-top: 16px;">
                    @forelse ($subscription->charges->take(8) as $charge)
                        <article class="charge-history-card">
                            <div class="spread-row">
                                <div>
                                    <p class="metric-label">{{ $charge->event ?? 'evento' }}</p>
                                    <h4 class="card-title">{{ $charge->status ?? 'sem status' }}</h4>
                                </div>
                                <span class="score-badge">
                                    @if ($charge->value_cents)
                                        R$ {{ number_format($charge->value_cents / 100, 2, ',', '.') }}
                                    @else
                                        sem valor
                                    @endif
                                </span>
                            </div>
                            <div class="admin-driver-meta">
                                <span>Vencimento {{ $charge->due_date?->format('d/m/Y') ?? 'nao informado' }}</span>
                                <span>Pagamento {{ $charge->paid_at?->format('d/m/Y H:i') ?? 'pendente' }}</span>
                                <span>{{ $charge->billing_type ?? 'meio nao informado' }}</span>
                            </div>
                            @if ($charge->invoice_url)
                                <div class="stack-actions" style="margin-top: 12px;">
                                    <a href="{{ $charge->invoice_url }}" class="ghost-button" target="_blank" rel="noreferrer">Abrir cobranca</a>
                                </div>
                            @endif
                        </article>
                    @empty
                        <p class="profile-copy">Nenhuma cobranca recebida da Asaas ate agora.</p>
                    @endforelse
                </div>
            </article>
        </div>
    @else
        <article class="feature-card">
            <p class="metric-label">Plano Mensal Pro</p>
            <h3 class="card-title">Assinatura ainda nao iniciada</h3>
            <p class="profile-copy">Ative os 7 dias gratis no onboarding e depois configure o checkout da Asaas para a recorrencia mensal.</p>
        </article>
    @endif
</section>
