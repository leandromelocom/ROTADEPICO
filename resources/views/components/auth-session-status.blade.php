@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'status-box']) }}>
        {{ $status }}
    </div>
@endif
