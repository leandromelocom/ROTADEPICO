<section>
    <header>
        <h2 class="profile-title">{{ __('Delete Account') }}</h2>
        <p class="profile-copy">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Before deleting your account, please download any data or information that you wish to retain.') }}
        </p>
    </header>

    <div class="divider"></div>

    <form method="post" action="{{ route('profile.destroy') }}">
        @csrf
        @method('delete')

        <h3 class="profile-title">{{ __('Are you sure you want to delete your account?') }}</h3>
        <p class="profile-copy">
            {{ __('Once your account is deleted, all of its resources and data will be permanently deleted. Please enter your password to confirm you would like to permanently delete your account.') }}
        </p>

        <div class="field">
            <x-input-label for="password" value="{{ __('Password') }}" />
            <x-text-input id="password" name="password" type="password" placeholder="{{ __('Password') }}" />
            <x-input-error :messages="$errors->userDeletion->get('password')" />
        </div>

        <div class="stack-actions">
            <x-danger-button>{{ __('Delete Account') }}</x-danger-button>
        </div>
    </form>
</section>
