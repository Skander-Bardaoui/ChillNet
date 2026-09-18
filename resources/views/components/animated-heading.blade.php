@props([
    'text' => '',
    /** Per-character stagger in ms. */
    'charDelay' => 30,
    /** Initial delay in ms before the animation begins. */
    'startDelay' => 200,
    /** Per-character animation duration in ms. */
    'charDuration' => 500,
])

{{--
    Heading that animates in character by character. Each character starts at
    opacity 0 / translateX(-18px) and animates to opacity 1 / translateX(0),
    with the stagger expressed as a CSS animation-delay (see app.css).

    Lines separated by \n are animated line by line.
--}}
<h1 {{ $attributes }}>
    @foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $lineIndex => $line)
        @php
            $characters = mb_str_split($line);
            $lineDelay = $lineIndex * count($characters) * $charDelay;
        @endphp

        <span class="block">
            @foreach ($characters as $charIndex => $char)
                @php
                    $delay = $startDelay + $lineDelay + ($charIndex * $charDelay);
                @endphp

                <span
                    class="char-in inline-block"
                    style="animation-delay: {{ $delay }}ms; animation-duration: {{ $charDuration }}ms;"
                >{{ $char === ' ' ? "\u{00A0}" : $char }}</span>
            @endforeach
        </span>
    @endforeach
</h1>
