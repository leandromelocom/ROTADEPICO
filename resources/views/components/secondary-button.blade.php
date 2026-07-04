<button {{ $attributes->merge(['type' => 'button', 'class' => 'ghost-button secondary-button']) }}>
    {{ $slot }}
</button>
