<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Rota de Pico</title>
        <link rel="stylesheet" href="{{ asset('app-fallback.css') }}">
        @if (file_exists(public_path('build/manifest.json')) || file_exists(public_path('hot')))
            @vite(['resources/css/app.css', 'resources/js/app.js'])
        @endif
    </head>
    <body>
        <main class="landing-shell">
            <header class="landing-header">
                <a href="/" class="brand">
                    <x-application-logo />
                </a>

                <nav class="landing-actions">
                    @auth
                        <a href="{{ route('dashboard') }}" class="ghost-button">Abrir radar</a>
                    @else
                        <a href="{{ route('login') }}" class="ghost-button">Entrar</a>
                        <a href="{{ route('register') }}" class="solid-button">Criar conta</a>
                    @endauth
                </nav>
            </header>

            <section class="landing-main">
                <div>
                    <div class="eyebrow">Inteligencia de rua para motoristas</div>
                    <h1 class="hero-title">
                        Descubra onde a corrida vira
                        <span>lucro</span>
                        antes do resto.
                    </h1>
                    <p class="hero-copy">
                        Um cockpit mobile que mostra zonas quentes, janelas de maior ticket e sinais de excesso de motoristas.
                        A proposta nao e apenas abrir mapa: e decidir melhor, perder menos tempo parado e buscar corrida no momento certo.
                    </p>

                    <div class="hero-actions">
                        <a href="{{ route('register') }}" class="solid-button">Comecar agora</a>
                        <a href="{{ route('login') }}" class="ghost-button">Ver area do motorista</a>
                    </div>

                    <div class="feature-grid">
                        <article class="feature-card">
                            <p class="metric-label">Radar ao vivo</p>
                            <h3 class="card-title">Top zonas</h3>
                            <p class="profile-copy">Ordena bairros por score de oportunidade e ticket medio.</p>
                        </article>
                        <article class="feature-card">
                            <p class="metric-label">Janela certa</p>
                            <h3 class="card-title">Hora quente</h3>
                            <p class="profile-copy">Mostra quando vale entrar, esperar ou sair da regiao.</p>
                        </article>
                        <article class="feature-card">
                            <p class="metric-label">Anti ociosidade</p>
                            <h3 class="card-title">Fluxo util</h3>
                            <p class="profile-copy">Combina demanda e concentracao de motoristas para filtrar ilusoes.</p>
                        </article>
                    </div>
                </div>

                <section class="panel hero-panel">
                    <span class="hero-glow-a"></span>
                    <span class="hero-glow-b"></span>
                    <div class="hero-stack">
                        <article class="feature-card">
                            <div class="spread-row">
                                <div>
                                    <p class="metric-label">Zona numero 1</p>
                                    <h2 class="panel-title">Itaim + Faria Lima</h2>
                                </div>
                                <span class="score-badge">Score 96</span>
                            </div>

                            <div class="metric-grid">
                                <div class="metric-card">
                                    <div class="metric-label">Media</div>
                                    <div class="metric-value">R$ 42,50</div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-label">Pico</div>
                                    <div class="metric-value">18:20</div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-label">Lotacao</div>
                                    <div class="metric-value">0.64</div>
                                </div>
                                <div class="metric-card">
                                    <div class="metric-label">Modo</div>
                                    <div class="metric-value">Noite</div>
                                </div>
                            </div>

                            <div class="callout">
                                <p class="metric-label orange">Leitura tatica</p>
                                <p class="profile-copy">
                                    Entre por vias laterais, pegue sequencia curta e escape antes de 21h10 quando a oferta de motoristas sobe.
                                </p>
                            </div>
                        </article>

                        <div class="summary-grid">
                            <article class="stat-card">
                                <p class="metric-label">Visao do turno</p>
                                <h3 class="card-title">Noite de sexta</h3>
                                <p class="profile-copy">Aplicativo prioriza zonas com alta saida corporativa e bares.</p>
                            </article>
                            <article class="stat-card">
                                <p class="metric-label">Diferencial</p>
                                <h3 class="card-title">Demanda com contexto</h3>
                                <p class="profile-copy">Nao mostra so demanda. Mostra onde a demanda compensa a concorrencia.</p>
                            </article>
                            <article class="stat-card">
                                <p class="metric-label">Leitura</p>
                                <h3 class="card-title">Decisao rapida</h3>
                                <p class="profile-copy">Foco total em poucos sinais para o motorista agir em segundos.</p>
                            </article>
                            <article class="stat-card">
                                <p class="metric-label">Tela</p>
                                <h3 class="card-title">Mobile first</h3>
                                <p class="profile-copy">Estruturado para uso no telefone durante o turno.</p>
                            </article>
                        </div>
                    </div>
                </section>
            </section>

            <section class="pricing-band">
                <div class="panel pricing-panel">
                    <div>
                        <div class="section-eyebrow">Assinatura unica</div>
                        <h2 class="section-title">Uma plataforma mensal para o proprio motorista se cadastrar e operar</h2>
                        <p class="section-copy">
                            Modelo self-service: cadastro, onboarding, geolocalizacao, conexao Uber e ativacao do radar pelo proprio usuario.
                        </p>
                    </div>

                    <div class="pricing-grid">
                        <article class="pricing-card">
                            <p class="metric-label">Plano Mensal Pro</p>
                            <h3 class="hero-price">R$ 39,90<span>/mes</span></h3>
                            <p class="profile-copy">Um unico plano com mapa operacional, ranking de regioes, radar preditivo e integracao oficial.</p>
                            <div class="chip-group">
                                <span class="chip">Auto cadastro</span>
                                <span class="chip">Onboarding guiado</span>
                                <span class="chip">Radar mobile</span>
                                <span class="chip">Uber API</span>
                            </div>
                            <div class="stack-actions">
                                <a href="{{ route('register') }}" class="solid-button">Assinar e comecar</a>
                            </div>
                        </article>
                    </div>
                </div>
            </section>
        </main>
    </body>
</html>
