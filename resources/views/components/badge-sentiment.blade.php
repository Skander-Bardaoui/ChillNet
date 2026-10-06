@props(['sentiment' => null, 'score' => null])
{{-- Badge du sentiment calculé par l'IA sur un avis. Module 3. --}}
@if ($sentiment)
<span {{ $attributes->merge(['class' => 'inline-flex items-center gap-1 px-2 py-0.5 rounded-full font-label-sm text-label-sm font-semibold whitespace-nowrap '.$sentiment->badgeClasses()]) }} title="Analyse de sentiment IA{{ $score !== null ? ' · score '.number_format((float) $score, 2, ',', '') : '' }}">
<span class="material-symbols-outlined text-[14px]">{{ $sentiment->icone() }}</span>{{ $sentiment->label() }}
</span>
@endif
