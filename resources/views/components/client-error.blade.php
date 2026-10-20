@props(['champ'])

<p x-show="erreurs.{{ $champ }}" x-cloak {{ $attributes->merge(['class' => 'rounded-lg bg-error-container/20 border border-error/30 text-error font-body-sm text-body-sm px-3 py-2 flex items-center gap-2']) }}>
    <span class="material-symbols-outlined text-[18px] leading-none shrink-0" aria-hidden="true">error</span>
    <span x-text="erreurs.{{ $champ }}"></span>
</p>
