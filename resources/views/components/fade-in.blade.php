@props([
    /** Delay in ms before the fade-in starts. */
    'delay' => 0,
    /** Animation duration in ms. */
    'duration' => 1000,
])

{{-- Wraps content that fades in (opacity 0 -> 1) after a configurable delay. --}}
<div {{ $attributes->class('fade-in') }} style="animation-delay: {{ $delay }}ms; animation-duration: {{ $duration }}ms;">
    {{ $slot }}
</div>
