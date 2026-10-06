<x-back-layout :title="'Points de fraîcheur'">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>[x-cloak] { display: none !important; }</style>

{{-- En-tête du module --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
<div class="flex items-start gap-3 flex-1">
<div class="p-3 rounded-xl bg-sky-100 text-sky-800 shrink-0"><span class="material-symbols-outlined text-[28px]">ac_unit</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module 3 · @if(auth()->user()->isGestionnaire()) Votre zone uniquement @else Référentiel global @endif</p>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">Référencez les <strong>parcs, salles climatisées et fontaines</strong>, modérez les points proposés par les habitants et surveillez les avis grâce à l'analyse de sentiment IA.</p>
</div>
</div>
<div class="flex flex-wrap gap-2 shrink-0">
<a href="{{ route('back.avis.index') }}" class="px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">reviews</span>Avis ({{ $stats['avis'] }})</a>
<a href="{{ route('back.points.create') }}" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2 shadow"><span class="material-symbols-outlined text-[18px]">add</span>Nouveau point</a>
</div>
</div>

{{-- Chiffres clés --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
<div class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-primary text-[28px]">location_on</span>
<div><p class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $stats['total'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">points au total</p></div>
</div>
<div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-green-700 text-[28px]">verified</span>
<div><p class="font-headline-sm text-headline-sm text-green-900 font-bold">{{ $stats['valides'] }}</p><p class="font-body-sm text-body-sm text-green-800">validés (publics)</p></div>
</div>
<div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-amber-700 text-[28px]">hourglass_top</span>
<div><p class="font-headline-sm text-headline-sm text-amber-900 font-bold">{{ $stats['en_attente'] }}</p><p class="font-body-sm text-body-sm text-amber-800">à modérer</p></div>
</div>
<div class="rounded-xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-red-700 text-[28px]">block</span>
<div><p class="font-headline-sm text-headline-sm text-red-900 font-bold">{{ $stats['refuses'] }}</p><p class="font-body-sm text-body-sm text-red-800">refusés</p></div>
</div>
</div>

{{-- File de modération : propositions des habitants --}}
@if ($enAttente->isNotEmpty())
<section class="rounded-2xl bg-amber-50/60 border border-amber-200 p-space-md flex flex-col gap-3">
<h2 class="font-title-md text-title-md text-amber-900 font-semibold flex items-center gap-2"><span class="material-symbols-outlined">pending_actions</span>Propositions d'habitants à modérer ({{ $enAttente->count() }})</h2>
<div class="flex flex-col gap-3">
@foreach ($enAttente as $proposition)
<article class="rounded-xl bg-surface-container-lowest border border-outline-variant/20 p-4 flex flex-col lg:flex-row lg:items-center gap-3" x-data="{ refus: false }">
<div class="flex items-start gap-3 flex-1 min-w-0">
@if ($proposition->photo_url)
<img src="{{ $proposition->photo_url }}" alt="" class="w-16 h-16 rounded-lg object-cover shrink-0" />
@else
<div class="w-16 h-16 rounded-lg bg-surface-container flex items-center justify-center shrink-0"><span class="material-symbols-outlined text-primary">{{ $proposition->type->icone() }}</span></div>
@endif
<div class="min-w-0">
<a href="{{ route('back.points.show', $proposition) }}" class="font-title-md text-title-md text-on-surface font-medium hover:underline">{{ $proposition->nom }}</a>
<p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ $proposition->type->label() }} · {{ $proposition->adresse }} · {{ $proposition->quartier?->nom ?? 'Hors quartier' }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Proposé par {{ $proposition->auteur?->name ?? 'compte supprimé' }} · {{ $proposition->created_at?->diffForHumans() }}</p>
</div>
</div>
@if (auth()->user()->isAdmin())
<div class="flex flex-wrap gap-2 shrink-0" x-show="!refus">
<form method="POST" action="{{ route('back.points.valider', $proposition) }}">@csrf @method('PATCH')<button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">check</span>Valider</button></form>
<button type="button" @click="refus = true" class="px-4 py-2 rounded-lg bg-surface-container-high text-error font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">close</span>Refuser</button>
</div>
<form method="POST" action="{{ route('back.points.refuser', $proposition) }}" x-show="refus" x-cloak class="flex flex-col sm:flex-row gap-2 w-full lg:w-auto">@csrf @method('PATCH')
<input type="text" name="motif_refus" required minlength="10" maxlength="500" placeholder="Motif du refus (visible par l'habitant)" class="flex-1 lg:w-72 rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none" />
<button type="submit" class="px-4 py-2 rounded-lg bg-error text-on-error font-label-md text-label-md font-semibold">Confirmer le refus</button>
<button type="button" @click="refus = false" class="px-3 py-2 rounded-lg text-on-surface-variant font-label-md text-label-md">Annuler</button>
</form>
@else
<span class="font-body-sm text-body-sm text-amber-800">En attente de validation par un administrateur</span>
@endif
</article>
@endforeach
</div>
</section>
@endif

{{-- IA : points mal notés de manière récurrente --}}
<section class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
<div class="flex items-center gap-2 mb-3">
<span class="material-symbols-outlined text-primary text-[22px]">auto_awesome</span>
<h2 class="font-title-md text-title-md text-on-surface font-semibold">Points mal notés récurrents <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(IA · analyse de sentiment des avis sur {{ \App\Services\SentimentAvisService::FENETRE_JOURS }} jours)</span></h2>
</div>
@forelse ($malNotes as $diag)
<div class="rounded-xl bg-red-50 border border-red-200 p-3 mb-2 flex flex-col sm:flex-row sm:items-center gap-3">
<span class="material-symbols-outlined text-red-700 text-[26px] shrink-0">sentiment_dissatisfied</span>
<div class="flex-1 min-w-0">
<p class="font-title-sm text-title-sm text-red-900 font-semibold">{{ $diag['point']->nom }}</p>
<p class="font-body-sm text-body-sm text-red-800">{{ $diag['message'] }}</p>
</div>
<div class="flex items-center gap-3 shrink-0">
<div class="w-28"><div class="h-2 rounded-full bg-red-100 overflow-hidden"><div class="h-full bg-red-600 rounded-full" style="width: {{ $diag['gravite'] }}%"></div></div><p class="font-label-sm text-label-sm text-red-800 mt-0.5">Gravité {{ $diag['gravite'] }}/100</p></div>
<a href="{{ route('back.points.show', $diag['point']) }}" class="px-3 py-2 rounded-lg bg-surface-container-lowest text-red-800 font-label-md text-label-md hover:bg-white inline-flex items-center gap-1">Analyser<span class="material-symbols-outlined text-[16px]">arrow_forward</span></a>
</div>
</div>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-2"><span class="material-symbols-outlined text-green-700">check_circle</span>Aucun point ne cumule d'avis négatifs récurrents.</p>
@endforelse
</section>

{{-- Carte --}}
<div class="rounded-2xl overflow-hidden bg-surface-container-low shadow-md border border-outline-variant/20">
<div class="flex items-center gap-2 px-space-md py-2.5 border-b border-outline-variant/20">
<span class="font-label-md text-label-md text-on-surface font-semibold">Carte des points</span>
<span class="ml-auto hidden md:inline font-body-sm text-body-sm text-on-surface-variant">🟢 parc · 🔵 salle climatisée · 🩵 fontaine — contour pointillé = non validé</span>
</div>
<div id="carte-back-points" class="w-full h-72 md:h-80 z-0"></div>
</div>

{{-- Filtres --}}
<form method="GET" action="{{ route('back.points.index') }}" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3 items-end">
<div class="flex flex-col gap-1 lg:col-span-2">
<label for="q" class="font-label-md text-label-md text-on-surface-variant">Recherche</label>
<input type="search" id="q" name="q" value="{{ $filtres['q'] ?? '' }}" placeholder="Nom ou adresse…" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
<div class="flex flex-col gap-1">
<label for="f-type" class="font-label-md text-label-md text-on-surface-variant">Type</label>
<select id="f-type" name="type" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">Tous</option>
@foreach (\App\Enums\TypePointFraicheur::cases() as $type)
<option value="{{ $type->value }}" @selected(($filtres['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
@endforeach
</select>
</div>
<div class="flex flex-col gap-1">
<label for="f-statut" class="font-label-md text-label-md text-on-surface-variant">Statut</label>
<select id="f-statut" name="statut" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">Tous</option>
@foreach (\App\Enums\StatutPointFraicheur::cases() as $statut)
<option value="{{ $statut->value }}" @selected(($filtres['statut'] ?? '') === $statut->value)>{{ $statut->label() }}</option>
@endforeach
</select>
</div>
<div class="flex gap-2">
<select name="tri" aria-label="Trier" class="flex-1 rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="recent" @selected(($filtres['tri'] ?? 'recent') === 'recent')>Plus récents</option>
<option value="nom" @selected(($filtres['tri'] ?? '') === 'nom')>Nom A → Z</option>
<option value="note" @selected(($filtres['tri'] ?? '') === 'note')>Mieux notés</option>
<option value="avis" @selected(($filtres['tri'] ?? '') === 'avis')>Plus d'avis</option>
</select>
<button type="submit" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant" aria-label="Filtrer"><span class="material-symbols-outlined text-[18px] align-middle">filter_list</span></button>
</div>
</form>

{{-- Tableau --}}
<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 overflow-hidden">
<div class="overflow-x-auto">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20 bg-surface-container-lowest/60">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Point</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Type</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Quartier</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Note</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
@forelse ($points as $point)
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60 last:border-0">
<td class="px-6 py-4">
<p class="font-title-md text-title-md text-on-surface font-medium">{{ $point->nom }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $point->adresse }} · {{ $point->horaires() }}@if ($point->capacite) · {{ $point->capacite }} pl.@endif</p>
</td>
<td class="px-6 py-4 whitespace-nowrap"><span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full font-label-sm text-label-sm font-semibold {{ $point->type->badgeClasses() }}"><span class="material-symbols-outlined text-[14px]">{{ $point->type->icone() }}</span>{{ $point->type->label() }}</span></td>
<td class="px-6 py-4 text-on-surface-variant whitespace-nowrap">{{ $point->quartier?->nom ?? '—' }}</td>
<td class="px-6 py-4 whitespace-nowrap">
@if ($point->avis_count)
<x-etoiles :note="$point->noteMoyenne()" :taille="14" /><span class="block font-body-sm text-body-sm text-on-surface-variant">{{ number_format($point->noteMoyenne(), 1, ',', '') }} · {{ $point->avis_count }} avis</span>
@else
<span class="font-body-sm text-body-sm text-on-surface-variant">Aucun avis</span>
@endif
</td>
<td class="px-6 py-4"><span class="px-2.5 py-0.5 rounded-full font-label-sm text-label-sm font-semibold whitespace-nowrap {{ $point->statut->badgeClasses() }}">{{ $point->statut->label() }}</span></td>
<td class="px-6 py-4 text-right space-x-1 whitespace-nowrap">
<a href="{{ route('back.points.show', $point) }}" title="Voir" aria-label="Voir" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-on-surface-variant hover:bg-surface-container-high"><span class="material-symbols-outlined text-[18px]">visibility</span></a>
<a href="{{ route('back.points.edit', $point) }}" title="Modifier" aria-label="Modifier" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-primary hover:bg-primary-container/20"><span class="material-symbols-outlined text-[18px]">edit</span></a>
<form method="POST" action="{{ route('back.points.destroy', $point) }}" class="inline" onsubmit="return confirm('Supprimer « {{ addslashes($point->nom) }} » et ses {{ $point->avis_count }} avis ? Cette action est définitive.');">@csrf @method('DELETE')<button type="submit" title="Supprimer" aria-label="Supprimer" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-error hover:bg-error-container/40"><span class="material-symbols-outlined text-[18px]">delete</span></button></form>
</td>
</tr>
@empty
<tr><td colspan="6" class="px-6 py-10 text-center">
<p class="font-title-md text-title-md text-on-surface font-medium">Aucun point de fraîcheur</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Ajoutez un parc, une salle climatisée ou une fontaine avec le bouton « Nouveau point ».</p>
</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div class="flex flex-col md:flex-row md:items-center gap-2 md:justify-between">
<p class="font-body-sm text-body-sm text-on-surface-variant">Affichage {{ $points->firstItem() ?? 0 }}–{{ $points->lastItem() ?? 0 }} sur {{ $points->total() }}</p>
{{ $points->links() }}
</div>

@include('points-fraicheur._carte-script', ['idCarte' => 'carte-back-points', 'marqueurs' => $marqueurs, 'centre' => null, 'origine' => null])
</x-back-layout>
