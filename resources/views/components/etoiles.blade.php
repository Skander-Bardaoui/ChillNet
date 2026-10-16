@props(['note' => null, 'taille' => 16])
{{-- Note sur 5 en étoiles (pleines / demi / vides). Module 3. --}}
@php
    $valeur = $note !== null ? max(0, min(5, (float) $note)) : 0;
@endphp
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-0.5 text-amber-500']) }} role="img" aria-label="{{ $note !== null ? number_format($valeur, 1, ',', '').' sur 5' : 'Pas encore noté' }}">
@for ($i = 1; $i <= 5; $i++)
<span class="material-symbols-outlined" style="font-size: {{ $taille }}px; font-variation-settings: 'FILL' {{ $valeur >= $i - 0.25 ? 1 : 0 }};">{{ $valeur >= $i - 0.25 ? 'star' : ($valeur >= $i - 0.75 ? 'star_half' : 'star') }}</span>
@endfor
</span>
