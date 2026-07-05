<x-app-layout>
    <x-slot name="header">
        <div class="app-shell">
            <div class="section-eyebrow">Conta do motorista</div>
            <h1 class="section-title">Perfil</h1>
        </div>
    </x-slot>

    <section class="dashboard-shell">
        <div class="profile-layout">
            <div class="profile-card">
                <div class="max-w-xl">
                    @include('profile.partials.subscription-card')
                </div>
            </div>

            <div class="profile-card">
                <div class="max-w-xl">
                    @include('profile.partials.uber-connection-card')
                </div>
            </div>

            <div class="profile-card">
                <div class="max-w-xl">
                    @include('profile.partials.update-profile-information-form')
                </div>
            </div>

            <div class="profile-card">
                <div class="max-w-xl">
                    @include('profile.partials.update-password-form')
                </div>
            </div>

            <div class="profile-card">
                <div class="max-w-xl">
                    @include('profile.partials.delete-user-form')
                </div>
            </div>
        </div>
    </section>
</x-app-layout>
