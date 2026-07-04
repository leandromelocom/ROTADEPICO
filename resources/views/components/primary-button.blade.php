<button {{ $attributes->merge(['type' => 'submit', 'class' => 'solid-button']) }}>
    {{ $slot }}
</button>
