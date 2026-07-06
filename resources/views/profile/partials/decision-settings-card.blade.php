<section>
    <header>
        <h2 class="profile-title">Preferencias operacionais</h2>
        <p class="profile-copy">Cada motorista define o seu corte. Em dias de giro, pode aceitar mais volume; em dias premium, sobe a regua.</p>
    </header>

    @if (session('decision_settings_status'))
        <div class="status-box">Preferencias operacionais atualizadas.</div>
    @endif

    <form method="post" action="{{ route('profile.decision-settings.update') }}">
        @csrf
        @method('patch')

        <div class="field">
            <x-input-label for="decision_profile" value="Perfil de decisao" />
            <select id="decision_profile" name="decision_profile" class="field-control" required>
                <option value="giro" @selected(old('decision_profile', $decisionSettings['decision_profile']) === 'giro')>Giro rapido</option>
                <option value="equilibrado" @selected(old('decision_profile', $decisionSettings['decision_profile']) === 'equilibrado')>Equilibrado</option>
                <option value="premium" @selected(old('decision_profile', $decisionSettings['decision_profile']) === 'premium')>Premium</option>
            </select>
            <x-input-error :messages="$errors->get('decision_profile')" />
        </div>

        <div class="field-grid">
            <div class="field">
                <x-input-label for="min_offer_fare" value="Valor minimo da corrida" />
                <x-text-input id="min_offer_fare" name="min_offer_fare" type="number" step="0.01" min="5" :value="old('min_offer_fare', $decisionSettings['min_offer_fare'])" required />
                <x-input-error :messages="$errors->get('min_offer_fare')" />
            </div>
            <div class="field">
                <x-input-label for="min_fare_per_km" value="Meta minima por km" />
                <x-text-input id="min_fare_per_km" name="min_fare_per_km" type="number" step="0.01" min="0.5" :value="old('min_fare_per_km', $decisionSettings['min_fare_per_km'])" required />
                <x-input-error :messages="$errors->get('min_fare_per_km')" />
            </div>
        </div>

        <div class="field-grid">
            <div class="field">
                <x-input-label for="min_hourly_rate" value="Meta minima por hora" />
                <x-text-input id="min_hourly_rate" name="min_hourly_rate" type="number" step="0.01" min="10" :value="old('min_hourly_rate', $decisionSettings['min_hourly_rate'])" required />
                <x-input-error :messages="$errors->get('min_hourly_rate')" />
            </div>
            <div class="field">
                <x-input-label for="max_pickup_distance_km" value="Maximo ate embarque (km)" />
                <x-text-input id="max_pickup_distance_km" name="max_pickup_distance_km" type="number" step="0.1" min="0.5" :value="old('max_pickup_distance_km', $decisionSettings['max_pickup_distance_km'])" required />
                <x-input-error :messages="$errors->get('max_pickup_distance_km')" />
            </div>
        </div>

        <div class="field">
            <x-input-label for="max_pickup_eta_minutes" value="Maximo ate embarque (min)" />
            <x-text-input id="max_pickup_eta_minutes" name="max_pickup_eta_minutes" type="number" min="1" :value="old('max_pickup_eta_minutes', $decisionSettings['max_pickup_eta_minutes'])" required />
            <x-input-error :messages="$errors->get('max_pickup_eta_minutes')" />
        </div>

        <div class="chip-group" style="margin-top: 18px;">
            <span class="small-chip">ele decide a propria regua</span>
            <span class="small-chip">pode subir ou baixar por dia</span>
            <span class="small-chip">impacta o motor instantaneamente</span>
        </div>

        <div class="stack-actions" style="margin-top: 18px;">
            <x-primary-button>Salvar preferencias</x-primary-button>
        </div>
    </form>
</section>
