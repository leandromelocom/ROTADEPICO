<section>
    <header>
        <h2 class="profile-title">Custo operacional do veiculo</h2>
        <p class="profile-copy">O motor usa esses numeros pra descontar combustivel e desgaste de cada oferta antes de dizer se vale a pena. Preenchido com uma estimativa pelo seu veiculo, mas ajuste pro seu consumo real.</p>
    </header>

    @if (session('cost_settings_status'))
        <div class="status-box">Custo operacional atualizado.</div>
    @endif

    <form method="post" action="{{ route('profile.cost-settings.update') }}">
        @csrf
        @method('patch')

        <div class="field-grid">
            <div class="field">
                <x-input-label for="fuel_consumption_km_per_l" value="Consumo (km por litro)" />
                <x-text-input id="fuel_consumption_km_per_l" name="fuel_consumption_km_per_l" type="number" step="0.1" min="1" :value="old('fuel_consumption_km_per_l', $costSettings['fuel_consumption_km_per_l'])" required />
                <x-input-error :messages="$errors->get('fuel_consumption_km_per_l')" />
            </div>
            <div class="field">
                <x-input-label for="fuel_price_per_liter" value="Preco do combustivel (R$/litro)" />
                <x-text-input id="fuel_price_per_liter" name="fuel_price_per_liter" type="number" step="0.01" min="1" :value="old('fuel_price_per_liter', $costSettings['fuel_price_per_liter'])" required />
                <x-input-error :messages="$errors->get('fuel_price_per_liter')" />
            </div>
        </div>

        <div class="field">
            <x-input-label for="extra_cost_per_km" value="Custo extra por km (manutencao, pneus, depreciacao)" />
            <x-text-input id="extra_cost_per_km" name="extra_cost_per_km" type="number" step="0.01" min="0" :value="old('extra_cost_per_km', $costSettings['extra_cost_per_km'])" required />
            <x-input-error :messages="$errors->get('extra_cost_per_km')" />
        </div>

        <div class="chip-group" style="margin-top: 18px;">
            <span class="small-chip">usado pra calcular o valor liquido de cada oferta</span>
            <span class="small-chip">estimativa inicial pelo tipo de veiculo</span>
        </div>

        <div class="stack-actions" style="margin-top: 18px;">
            <x-primary-button>Salvar custo operacional</x-primary-button>
        </div>
    </form>
</section>
