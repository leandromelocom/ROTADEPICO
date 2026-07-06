@php
    $inputIdPrefix = $inputIdPrefix ?? 'location';
    $selectedState = old($stateFieldName ?? 'state', $selectedState ?? null);
    $selectedCity = old($cityFieldName ?? 'city', $selectedCity ?? null);
    $stateFieldName = $stateFieldName ?? 'state';
    $cityFieldName = $cityFieldName ?? 'city';
@endphp

<div class="field-grid">
    <div class="field">
        <x-input-label :for="$inputIdPrefix.'_state'" value="Estado" />
        <select
            id="{{ $inputIdPrefix }}_state"
            name="{{ $stateFieldName }}"
            class="field-control"
            data-brazil-state
            data-city-target="{{ $inputIdPrefix }}_city"
            data-selected-city='@json($selectedCity)'
        >
            <option value="">Selecione o estado</option>
            @foreach ($brazilStates as $uf => $state)
                <option value="{{ $uf }}" @selected($selectedState === $uf)>{{ $state['name'] }} ({{ $uf }})</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get($stateFieldName)" />
    </div>

    <div class="field">
        <x-input-label :for="$inputIdPrefix.'_city'" value="Cidade" />
        <select
            id="{{ $inputIdPrefix }}_city"
            name="{{ $cityFieldName }}"
            class="field-control"
            data-brazil-city
            required
        >
            <option value="">{{ $selectedState ? 'Selecione a cidade' : 'Escolha o estado antes' }}</option>
            @foreach (($selectedState && isset($brazilStates[$selectedState])) ? $brazilStates[$selectedState]['cities'] : [] as $city)
                <option value="{{ $city }}" @selected($selectedCity === $city)>{{ $city }}</option>
            @endforeach
        </select>
        <x-input-error :messages="$errors->get($cityFieldName)" />
    </div>
</div>

@once
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const catalog = @json($brazilStates);

            document.querySelectorAll('[data-brazil-state]').forEach((stateSelect) => {
                const targetId = stateSelect.dataset.cityTarget;
                const citySelect = document.getElementById(targetId);

                if (! citySelect) {
                    return;
                }

                const selectedCity = stateSelect.dataset.selectedCity ? JSON.parse(stateSelect.dataset.selectedCity) : '';

                const fillCities = (state, preservedCity = '') => {
                    const cities = catalog[state]?.cities ?? [];

                    citySelect.innerHTML = '';

                    const placeholder = document.createElement('option');
                    placeholder.value = '';
                    placeholder.textContent = state ? 'Selecione a cidade' : 'Escolha o estado antes';
                    citySelect.appendChild(placeholder);

                    cities.forEach((city) => {
                        const option = document.createElement('option');
                        option.value = city;
                        option.textContent = city;

                        if (city === preservedCity) {
                            option.selected = true;
                        }

                        citySelect.appendChild(option);
                    });
                };

                fillCities(stateSelect.value, citySelect.value || selectedCity);

                stateSelect.addEventListener('change', () => {
                    fillCities(stateSelect.value);
                });
            });
        });
    </script>
@endonce
