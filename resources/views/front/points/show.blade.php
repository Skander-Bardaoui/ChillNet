<x-public-layout :title="$point->nom">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('refuges.index') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Points de fraîcheur</a>
<span aria-hidden="true">/</span>
<span class="text-on-surface font-medium truncate">{{ $point->nom }}</span>
</nav>

@unless ($point->estValide())
<div role="status" class="rounded-xl bg-amber-50 border border-amber-200 text-amber-900 px-space-md py-space-sm flex items-center gap-2"><span class="material-symbols-outlined">{{ $point->statut->icone() }}</span>Votre proposition est « {{ mb_strtolower($point->statut->label()) }} » : elle n'est visible que par vous.@if ($point->motif_refus) Motif : {{ $point->motif_refus }}@endif</div>
@endunless

{{-- Fiche --}}
<section class="rounded-2xl bg-surface-container-low shadow-lg overflow-hidden">
@if ($point->photo_url)
<img src="{{ $point->photo_url }}" alt="Photo de {{ $point->nom }}" class="w-full h-60 md:h-80 object-cover" />
@endif
<div class="p-space-md md:p-space-lg flex flex-col gap-space-sm">
<div class="flex items-start justify-between gap-space-sm flex-wrap">
<div class="flex flex-col gap-2">
<x-point-equipements :point="$point" />
<h1 class="font-headline-md text-headline-md text-on-surface font-bold">{{ $point->nom }}</h1>
<p class="font-body-md text-body-md text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">place</span>{{ $point->adresse }}</p>
</div>
<div class="flex flex-col items-end">
<span class="font-display-sm text-display-sm text-on-surface font-bold">{{ $point->noteMoyenne() !== null ? number_format($point->noteMoyenne(), 1, ',', '') : '—' }}</span>
<x-etoiles :note="$point->noteMoyenne()" :taille="18" />
<span class="font-body-sm text-body-sm text-on-surface-variant">{{ $point->avis_count }} avis</span>
</div>
</div>
@if ($point->description)<p class="font-body-md text-body-md text-on-surface">{{ $point->description }}</p>@endif
<div class="grid grid-cols-2 sm:grid-cols-4 gap-space-sm">
<div class="rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Horaires</span><span class="font-title-md text-title-md text-on-surface">{{ $point->horaires() }}</span><span class="block font-label-sm text-label-sm {{ $evaluation['ouvert'] ? 'text-green-700' : 'text-on-surface-variant' }}">{{ $evaluation['ouvert'] ? 'Ouvert maintenant' : 'Fermé maintenant' }}</span></div>
<div class="rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Capacité</span><span class="font-title-md text-title-md text-on-surface">{{ $point->capacite ? $point->capacite.' pers.' : 'Accès libre' }}</span></div>
<div class="rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Distance</span><span class="font-title-md text-title-md text-primary">{{ $evaluation['distance_m'] < 1000 ? $evaluation['distance_m'].' m' : number_format($evaluation['distance_m'] / 1000, 1, ',', '').' km' }} · {{ $evaluation['minutes_marche'] }} min</span></div>
<div class="rounded-lg bg-surface-container p-space-sm"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Affluence estimée (IA)</span><span class="font-title-md text-title-md text-on-surface">{{ $evaluation['affluence']['libelle'] }}</span>
<div class="mt-1 h-1.5 rounded-full bg-surface-container-high overflow-hidden"><div class="h-full rounded-full {{ $evaluation['affluence']['taux'] >= 0.7 ? 'bg-red-500' : ($evaluation['affluence']['taux'] >= 0.4 ? 'bg-amber-500' : 'bg-green-600') }}" style="width: {{ (int) round($evaluation['affluence']['taux'] * 100) }}%"></div></div></div>
</div>
<div class="flex flex-wrap gap-space-sm">
<a href="https://www.openstreetmap.org/directions?engine=fossgis_osrm_foot&route=%3B{{ $point->latitude }}%2C{{ $point->longitude }}" target="_blank" rel="noopener" class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px]">directions_walk</span>Itinéraire à pied</a>
<a href="#avis" class="px-space-md py-2.5 rounded-lg bg-surface-container-highest text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-1.5"><span class="material-symbols-outlined text-[18px]">rate_review</span>Voir les avis</a>
</div>
<div id="carte-point" class="w-full h-56 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
</div>
</section>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md items-start">
{{-- Avis --}}
<section id="avis" class="lg:col-span-2 flex flex-col gap-space-sm scroll-mt-28">
<h2 class="font-headline-sm text-headline-sm text-on-surface">Avis des visiteurs ({{ $avis->total() }})</h2>
@forelse ($avis as $un)
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col gap-2">
<div class="flex items-center gap-2 flex-wrap">
<div class="w-9 h-9 rounded-full bg-primary-container/20 text-primary flex items-center justify-center font-title-md text-title-md">{{ mb_strtoupper(mb_substr($un->user?->name ?? '?', 0, 1)) }}</div>
<div class="flex-1 min-w-0">
<p class="font-title-sm text-title-sm text-on-surface">{{ $un->auteurAbrege() }}@if ((int) $un->user_id === (int) auth()->id()) <span class="font-label-sm text-label-sm text-primary">(vous)</span>@endif</p>
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-2"><x-etoiles :note="$un->note" :taille="14" /> {{ $un->created_at?->format('d/m/Y') }}@if ($un->affluence) · {{ $un->affluence->label() }}@endif</p>
</div>
<x-badge-sentiment :sentiment="$un->sentiment" :score="$un->score_sentiment" />
</div>
@if ($un->commentaire)<p class="font-body-md text-body-md text-on-surface">« {{ $un->commentaire }} »</p>@endif
</article>
@empty
<div class="rounded-xl bg-surface-container-low p-space-md text-on-surface-variant">Pas encore d'avis : soyez le premier à partager votre expérience.</div>
@endforelse
{{ $avis->fragment('avis')->links() }}
</section>

{{-- Déposer / gérer mon avis --}}
<aside class="flex flex-col gap-space-sm lg:sticky lg:top-24">
<div class="rounded-2xl bg-surface-container-low p-space-md shadow-sm">
<h2 class="font-title-md text-title-md text-on-surface font-semibold mb-2">Répartition des notes</h2>
@for ($n = 5; $n >= 1; $n--)
@php $nb = (int) ($notes[$n] ?? 0); $pct = $point->avis_count ? round($nb / $point->avis_count * 100) : 0; @endphp
<div class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant"><span class="w-4">{{ $n }}</span><div class="flex-1 h-2 rounded-full bg-surface-container-high overflow-hidden"><div class="h-full bg-amber-500 rounded-full" style="width: {{ $pct }}%"></div></div><span class="w-6 text-right">{{ $nb }}</span></div>
@endfor
</div>

<div class="rounded-2xl bg-surface-container-low p-space-md shadow-md">
<div class="flex items-center gap-space-sm mb-space-sm">
<div class="p-2 rounded-lg bg-primary/10 text-primary"><span class="material-symbols-outlined text-[20px]">rate_review</span></div>
<h2 class="font-title-lg text-title-lg text-on-surface">{{ $monAvis ? 'Votre avis' : 'Déposer un avis' }}</h2>
</div>
@guest
<p class="font-body-sm text-body-sm text-on-surface-variant">Connectez-vous en tant qu'habitant pour noter ce point.</p>
<a href="{{ route('login') }}" class="mt-3 px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">login</span>Se connecter</a>
@else
@if (! auth()->user()->isHabitant())
<p class="font-body-sm text-body-sm text-on-surface-variant">Les avis sont réservés aux habitants. Modérez-les depuis l'espace de gestion.</p>
@elseif (! $point->estValide())
<p class="font-body-sm text-body-sm text-on-surface-variant">Les avis seront ouverts une fois le point validé.</p>
@elseif ($monAvis)
<div class="flex flex-col gap-2">
<div class="flex items-center gap-2"><x-etoiles :note="$monAvis->note" :taille="18" /><x-badge-sentiment :sentiment="$monAvis->sentiment" /></div>
@if ($monAvis->commentaire)<p class="font-body-sm text-body-sm text-on-surface">« {{ $monAvis->commentaire }} »</p>@endif
<div class="flex gap-2 mt-1">
<a href="{{ route('avis.edit', $monAvis) }}" class="px-4 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">edit</span>Modifier</a>
<form method="POST" action="{{ route('avis.destroy', $monAvis) }}" onsubmit="return confirm('Supprimer votre avis ?');">@csrf @method('DELETE')<button type="submit" class="px-4 py-2 rounded-lg text-error hover:bg-error-container/40 font-label-md text-label-md inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">delete</span>Supprimer</button></form>
</div>
</div>
@else
<form method="POST" action="{{ route('points.avis.store', $point) }}" novalidate>
@include('points-fraicheur._avis-form', ['avis' => null])
</form>
@endif
@endguest
</div>
</aside>
</div>

@include('points-fraicheur._carte-script', [
    'idCarte' => 'carte-point',
    'marqueurs' => [['lat' => $point->latitude, 'lng' => $point->longitude, 'nom' => $point->nom, 'type' => $point->type->label(), 'couleur' => $point->type->couleurHex(), 'lien' => '#']],
])
</x-public-layout>
