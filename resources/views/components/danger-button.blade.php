<button {{ $attributes->merge(['type' => 'submit', 'class' => 'solid-button danger-button']) }}>
    {{ $slot }}
</button>
