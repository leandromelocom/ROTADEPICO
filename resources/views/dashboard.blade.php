@push('styles')
    <link
        rel="stylesheet"
        href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
        integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY="
        crossorigin=""
    >
@endpush

@push('scripts')
    <script
        src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo="
        crossorigin=""
        defer
    ></script>
@endpush

<x-app-layout>
    <section class="dashboard-shell">
        <div class="dashboard-grid">
            <section class="panel">
                <div class="spread-row">
                    <div>
                        <div class="section-eyebrow">Radar preditivo</div>
                        <h1 class="section-title">Melhor jogada para voce agora</h1>
                        <p class="section-copy">
                            {{ $bestNow['zone_name'] }} foi promovida porque combina com seu veiculo, seu turno e a janela operacional atual.
                        </p>
                    </div>
                    <span class="score-badge" data-live-best-score>Score {{ $bestNow['predicted_score'] }}</span>
                </div>

                <div class="metric-grid">
                    <article class="metric-card">
                        <div class="metric-label">Ticket</div>
                        <div class="metric-value" data-live-ticket>R$ {{ number_format($bestNow['avg_fare'], 2, ',', '.') }}</div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-label">Janela</div>
                        <div class="metric-value" data-live-window>{{ $bestNow['best_window'] }}</div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-label">Fit perfil</div>
                        <div class="metric-value" data-live-fit>{{ $bestNow['fit_score'] }}%</div>
                    </article>
                    <article class="metric-card">
                        <div class="metric-label">Hora util</div>
                        <div class="metric-value" data-live-hourly>R$ {{ number_format($bestNow['expected_hourly'], 2, ',', '.') }}</div>
                    </article>
                </div>

                <div class="panel-split">
                    <article class="callout">
                        <p class="metric-label orange">Leitura tatica</p>
                        <p class="profile-copy">{{ $bestNow['tip'] }}</p>
                        <p class="profile-copy orange" data-live-recommendation>{{ $bestNow['recommendation'] }}</p>
                    </article>
                    <article class="feature-card">
                        <p class="metric-label">Sinais do algoritmo</p>
                        <div class="chip-group" data-live-signals>
                            @foreach ($bestNow['signals'] as $signal)
                                <span class="chip">{{ $signal }}</span>
                            @endforeach
                        </div>
                    </article>
                </div>
            </section>

            <aside class="summary-grid">
                <article class="stat-card">
                    <p class="metric-label">Media das zonas</p>
                    <div class="metric-value">R$ {{ number_format($stats['avg_ticket'], 2, ',', '.') }}</div>
                    <p class="profile-copy">Ticket medio combinado do radar ativo.</p>
                </article>
                <article class="stat-card">
                    <p class="metric-label">Melhor score pessoal</p>
                    <div class="metric-value">{{ $stats['best_score'] }}</div>
                    <p class="profile-copy">Pontuacao final ja ajustada ao seu perfil.</p>
                </article>
                <article class="stat-card">
                    <p class="metric-label">Melhor distancia</p>
                    <div class="metric-value" data-live-distance>{{ $stats['closest_best_distance_km'] !== null ? number_format($stats['closest_best_distance_km'], 1, ',', '.') . ' km' : 'GPS livre' }}</div>
                    <p class="profile-copy">Distancia ate a melhor zona calculada com sua localizacao atual.</p>
                </article>
                <article class="stat-card">
                    <p class="metric-label">Zonas monitoradas</p>
                    <div class="metric-value">{{ $stats['zones_online'] }}</div>
                    <p class="profile-copy">Radar inicial com foco em Sao Paulo.</p>
                </article>
            </aside>
        </div>

        <div class="ranking-grid" style="margin-top: 18px;">
            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Va agora</p>
                        <h2 class="section-title">Recomendacao imediata por proximidade</h2>
                        <p class="section-copy">Quando o GPS responde, o motor recalcula score, horario e deslocamento para decidir a melhor zona agora.</p>
                    </div>
                    <span class="small-chip">Local + timing</span>
                </div>

                <article class="feature-card live-recommendation-card" style="margin-top: 18px;">
                    <div class="spread-row">
                        <div>
                            <p class="metric-label" data-live-now-label>Va agora para</p>
                            <h3 class="card-title" data-live-now-zone>{{ $bestNow['zone_name'] }}</h3>
                        </div>
                        <span class="score-badge" data-live-now-priority>{{ $bestNow['distance_km'] !== null ? number_format($bestNow['distance_km'], 1, ',', '.') . ' km' : 'aguardando GPS' }}</span>
                    </div>
                    <p class="profile-copy" data-live-now-copy>{{ $bestNow['recommendation'] }}</p>
                </article>
            </section>

            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Ranking por horario</p>
                        <h2 class="section-title">Como o mapa aquece nas proximas horas</h2>
                    </div>
                    <span class="small-chip">Agora ate +3h</span>
                </div>

                <div class="timeline-grid" style="margin-top: 18px;" data-hourly-rankings>
                    @foreach ($hourlyRankings as $slot)
                        <article class="timeline-card">
                            <p class="metric-label">{{ $slot['label'] }}</p>
                            <h3 class="card-title">{{ $slot['zone_name'] }}</h3>
                            <p class="profile-copy">{{ $slot['best_window'] }}</p>
                            <div class="detail-row">
                                <span>Score {{ $slot['predicted_score'] }}</span>
                                <span class="sky">R$ {{ number_format($slot['expected_hourly'], 2, ',', '.') }}/h</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="panel" style="margin-top: 18px;">
            <div class="spread-row">
                <div>
                    <p class="metric-label">Mapa operacional</p>
                    <h2 class="section-title">Onde voce esta e para onde mover</h2>
                    <p class="section-copy">
                        O mapa usa sua geolocalizacao para destacar a melhor zona perto de voce. Se a permissao for negada, o radar abre centralizado em Sao Paulo.
                    </p>
                </div>
                <span class="small-chip">Geolocalizacao ao vivo</span>
            </div>

            <div class="map-grid" style="margin-top: 18px;">
                <div
                    id="driver-opportunity-map"
                    class="map-canvas"
                    data-opportunity-map='@json($mapZones)'
                    data-map-default-lat="-23.550520"
                    data-map-default-lng="-46.633308"
                    data-map-default-zoom="11"
                    data-location-endpoint="{{ route('radar.location') }}"
                ></div>

                <aside class="map-panel">
                    <div class="map-status" data-map-status>
                        Solicitando sua localizacao para mostrar a melhor zona perto de voce.
                    </div>

                    <div class="map-driver-card" data-driver-card hidden>
                        <p class="metric-label">Sua posicao</p>
                        <h3 class="card-title" data-driver-title>Motorista localizado</h3>
                        <p class="profile-copy" data-driver-copy></p>
                    </div>

                    <div class="map-legend">
                        <p class="metric-label">Leitura de pagamento</p>
                        <div class="legend-list">
                            <span><i class="legend-dot legend-dot-premium"></i>Premium</span>
                            <span><i class="legend-dot legend-dot-strong"></i>Forte</span>
                            <span><i class="legend-dot legend-dot-good"></i>Boa</span>
                            <span><i class="legend-dot legend-dot-volume"></i>Volume</span>
                        </div>
                    </div>

                    <div class="list-stack" data-map-zone-list>
                        @foreach ($mapZones->take(3) as $zone)
                            <article class="map-zone-card">
                                <div class="spread-row">
                                    <h3 class="card-title">{{ $zone['zone_name'] }}</h3>
                                    <span class="score-badge">Score {{ $zone['predicted_score'] }}</span>
                                </div>
                                <p class="profile-copy">{{ $zone['recommendation'] }}</p>
                                <div class="detail-row">
                                    <span>{{ $zone['best_window'] }}</span>
                                    <span class="sky">{{ $zone['distance_km'] !== null ? number_format($zone['distance_km'], 1, ',', '.') . ' km' : 'R$ ' . number_format($zone['avg_fare'], 2, ',', '.') }}</span>
                                </div>
                                <div class="detail-row">
                                    <span>{{ $zone['pay_label'] }}</span>
                                    <span>{{ $zone['route_profile'] }}</span>
                                </div>
                            </article>
                        @endforeach
                    </div>
                </aside>
            </div>
        </section>

        <div class="ranking-grid" style="margin-top: 18px;">
            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Comparacao entre regioes proximas</p>
                        <h2 class="section-title">O que vale mais perto de voce</h2>
                    </div>
                    <span class="small-chip">Distancia x retorno</span>
                </div>

                <div class="list-stack" style="margin-top: 18px;" data-nearby-comparisons>
                    @foreach ($nearbyComparisons as $zone)
                        <article class="feature-card">
                            <div class="spread-row">
                                <div>
                                    <p class="metric-label">{{ $zone['distance_km'] !== null ? number_format($zone['distance_km'], 1, ',', '.') . ' km' : 'sem gps' }}</p>
                                    <h3 class="card-title">{{ $zone['zone_name'] }}</h3>
                                </div>
                                <span class="score-badge">R$ {{ number_format($zone['avg_fare'], 2, ',', '.') }}</span>
                            </div>
                            <p class="profile-copy">{{ $zone['reason'] }}</p>
                            <div class="detail-row">
                                <span>{{ $zone['best_window'] }}</span>
                                <span class="sky">Score local {{ $zone['localized_priority'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Previsao por turno</p>
                        <h2 class="section-title">Quando cada zona deve aquecer mais</h2>
                    </div>
                    <span class="small-chip">Motor de aquecimento</span>
                </div>

                <div class="forecast-grid" style="margin-top: 18px;" data-shift-forecasts>
                    @foreach ($shiftForecasts as $forecast)
                        <article class="forecast-card">
                            <p class="metric-label">{{ $forecast['shift'] }}</p>
                            <h3 class="card-title">{{ $forecast['zone_name'] }}</h3>
                            <p class="profile-copy">{{ $forecast['best_window'] }}</p>
                            <div class="detail-row">
                                <span>R$ {{ number_format($forecast['expected_hourly'], 2, ',', '.') }}/h</span>
                                <span class="sky">Turno {{ $forecast['shift'] }}</span>
                            </div>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="panel" style="margin-top: 18px;">
            <div class="spread-row">
                <div>
                    <p class="metric-label">Regioes pagando melhor</p>
                    <h2 class="section-title">Onde o ticket esta mais alto agora</h2>
                    <p class="section-copy">
                        Esta leitura prioriza as regioes com maior media de corrida e desenha zonas quentes no mapa para facilitar o deslocamento.
                    </p>
                </div>
                <span class="small-chip">Radar de ticket</span>
            </div>

            <div class="paying-grid" style="margin-top: 18px;">
                @foreach ($topPayingRegions as $region)
                    <article class="paying-card">
                        <div class="spread-row">
                            <div>
                                <p class="metric-label">{{ $region['pay_label'] }}</p>
                                <h3 class="card-title">{{ $region['zone_name'] }}</h3>
                            </div>
                            <span class="score-badge">R$ {{ number_format($region['avg_fare'], 2, ',', '.') }}</span>
                        </div>
                        <p class="profile-copy">Janela {{ $region['best_window'] }} • {{ $region['route_profile'] }}</p>
                        <div class="detail-row">
                            <span>Score {{ $region['predicted_score'] }}</span>
                            <span class="sky">{{ $region['distance_km'] !== null ? number_format($region['distance_km'], 1, ',', '.') . ' km' : 'Foco em ticket' }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <div class="ranking-grid" style="margin-top: 18px;">
            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Proximos 90 minutos</p>
                        <h2 class="section-title">Plano de ataque</h2>
                    </div>
                    <span class="small-chip">{{ auth()->user()->vehicle_type }} • {{ auth()->user()->work_shift }}</span>
                </div>

                <div class="move-list list-stack" style="margin-top: 18px;">
                    @foreach ($nextMoves as $move)
                        <article class="move-card">
                            <p class="metric-label orange">{{ $move['title'] }}</p>
                            <h3 class="card-title">{{ $move['zone_name'] }}</h3>
                            <p class="sky">{{ $move['window'] }}</p>
                            <p class="profile-copy">{{ $move['reason'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>

            <section class="panel">
                <div class="spread-row">
                    <div>
                        <p class="metric-label">Ranking personalizado</p>
                        <h2 class="section-title">Onde vale insistir</h2>
                    </div>
                    <span class="small-chip">Cidade {{ auth()->user()->city ?? 'Nao definida' }}</span>
                </div>

                <div class="list-stack" style="margin-top: 18px;">
                    @foreach ($heatZones as $zone)
                        <article class="ranking-card">
                            <div class="spread-row">
                                <h3 class="card-title">{{ $zone['zone_name'] }}</h3>
                                <span class="state-badge {{ $zone['trend'] === 'subindo' ? 'up' : ($zone['trend'] === 'descendo' ? 'down' : 'flat') }}">{{ $zone['trend'] }}</span>
                            </div>
                            <div class="detail-row">
                                <span>{{ $zone['route_profile'] }}</span>
                                <span>Fila {{ $zone['queue_pressure'] }}/100</span>
                            </div>
                            <div class="bar">
                                <span style="width: {{ $zone['predicted_score'] }}%"></span>
                            </div>
                            <div class="spread-row">
                                <span>Score {{ $zone['predicted_score'] }}</span>
                                <span class="sky">Fit {{ $zone['fit_score'] }}%</span>
                            </div>
                            <p class="profile-copy">{{ $zone['recommendation'] }}</p>
                        </article>
                    @endforeach
                </div>
            </section>
        </div>

        <section class="panel" style="margin-top: 18px;">
            <div class="spread-row">
                <div>
                    <p class="metric-label">Janelas de maior retorno</p>
                    <h2 class="section-title">Sequencia ideal do turno</h2>
                </div>
                <span class="small-chip">Perfil {{ auth()->user()->vehicle_type }} / {{ auth()->user()->work_shift }}</span>
            </div>

            <div class="window-list list-stack" style="margin-top: 18px;">
                @foreach ($peakWindows as $window)
                    <article class="window-card">
                        <div class="spread-row">
                            <div>
                                <h3 class="card-title">{{ $window['zone_name'] }}</h3>
                                <p class="profile-copy">{{ $window['pickup_hotspot'] }}</p>
                            </div>
                            <div style="text-align: right;">
                                <span class="score-badge">R$ {{ number_format($window['expected_hourly'], 2, ',', '.') }}/h</span>
                                <p class="orange">{{ $window['best_window'] }}</p>
                            </div>
                        </div>
                        <div class="chip-group">
                            @foreach ($window['signals'] as $signal)
                                <span class="small-chip">{{ $signal }}</span>
                            @endforeach
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    </section>

    <script id="opportunity-map-template" type="application/json">
        {
            "userPopupTitle": "Voce esta aqui",
            "userPopupBody": "Posicao atual usada para calcular a melhor zona por proximidade.",
            "distanceSuffix": "km",
            "fallbackStatus": "Nao foi possivel obter sua localizacao. O mapa foi aberto em Sao Paulo com as melhores zonas do radar.",
            "readyStatus": "Sua localizacao foi encontrada. As zonas abaixo foram ordenadas pela melhor combinacao de score, horario e proximidade.",
            "loadingStatus": "Solicitando sua localizacao para recalcular o radar em tempo real."
        }
    </script>
</x-app-layout>
