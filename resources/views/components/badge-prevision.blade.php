{{-- Badge « Prévision IA » d'un point de fraîcheur, affiché aux habitants
     uniquement si le modèle prévoit une dégradation (niveau >= à surveiller). --}}
@props(['sante' => null])
@if ($sante && $sante['niveau']->estARisque())
<span {{ $attributes->merge(['class' => 'self-start px-2 py-0.5 rounded-full font-label-sm text-label-sm font-semibold inline-flex items-center gap-1 '.$sante['niveau']->badgeClasses()]) }} title="{{ $sante['niveau']->messagePublic() }}"><span class="material-symbols-outlined text-[14px]">{{ $sante['niveau']->icone() }}</span>Prévision IA : {{ mb_strtolower($sante['niveau']->libellePublic()) }}</span>
@endif
