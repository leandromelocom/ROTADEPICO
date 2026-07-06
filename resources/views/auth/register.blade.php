<x-guest-layout>
    <form method="POST" action="{{ route('register') }}">
        @csrf

        <div class="auth-intro">
            <p class="section-eyebrow">Novo motorista</p>
            <h1>Crie seu radar</h1>
            <p class="section-copy">Cadastre seu perfil de trabalho para receber uma leitura inicial das melhores zonas e horarios.</p>
        </div>

        <div class="field">
            <x-input-label for="name" value="Nome" />
            <x-text-input id="name" type="text" name="name" :value="old('name')" required autofocus autocomplete="name" />
            <x-input-error :messages="$errors->get('name')" />
        </div>

        <div class="field">
            <x-input-label for="email" value="E-mail" />
            <x-text-input id="email" type="email" name="email" :value="old('email')" required autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" />
        </div>

        <div class="field">
            <x-input-label for="phone" value="WhatsApp" />
            <x-text-input id="phone" type="text" name="phone" :value="old('phone')" required autocomplete="tel" />
            <x-input-error :messages="$errors->get('phone')" />
        </div>

        @include('partials.brazil-city-select', [
            'brazilStates' => $brazilStates,
            'inputIdPrefix' => 'register',
            'selectedState' => old('state', 'SP'),
            'selectedCity' => old('city', 'São Paulo'),
        ])

        <div class="field">
            <x-input-label for="vehicle_type" value="Veiculo" />
            <select id="vehicle_type" name="vehicle_type" class="field-control" required>
                <option value="Carro" @selected(old('vehicle_type') === 'Carro')>Carro</option>
                <option value="Moto" @selected(old('vehicle_type') === 'Moto')>Moto</option>
                <option value="SUV" @selected(old('vehicle_type') === 'SUV')>SUV</option>
            </select>
            <x-input-error :messages="$errors->get('vehicle_type')" />
        </div>

        <div class="field">
            <x-input-label for="work_shift" value="Turno principal" />
            <select id="work_shift" name="work_shift" class="field-control" required>
                <option value="Manha" @selected(old('work_shift') === 'Manha')>Manha</option>
                <option value="Tarde" @selected(old('work_shift') === 'Tarde')>Tarde</option>
                <option value="Noite" @selected(old('work_shift', 'Noite') === 'Noite')>Noite</option>
                <option value="Madrugada" @selected(old('work_shift') === 'Madrugada')>Madrugada</option>
                <option value="Flexivel" @selected(old('work_shift') === 'Flexivel')>Flexivel</option>
            </select>
            <x-input-error :messages="$errors->get('work_shift')" />
        </div>

        <div class="field">
            <x-input-label for="password" value="Senha" />
            <x-text-input id="password" type="password" name="password" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password')" />
        </div>

        <div class="field">
            <x-input-label for="password_confirmation" value="Confirmar senha" />
            <x-text-input id="password_confirmation" type="password" name="password_confirmation" required autocomplete="new-password" />
            <x-input-error :messages="$errors->get('password_confirmation')" />
        </div>

        <div class="form-actions">
            <a class="text-link" href="{{ route('login') }}">
                Ja tem conta?
            </a>

            <x-primary-button>Criar conta</x-primary-button>
        </div>
    </form>
</x-guest-layout>
