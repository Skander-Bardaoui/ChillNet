<x-back-layout :title="$point->nom">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<style>[x-cloak] { display: none !important; }</style>

<div class="flex flex-wrap items-center gap-2">
<a href="{{ route('back.points.index') }}" class="px-3 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Liste</a>
<span class="px-2.5 py-1 rounded-full font-label-sm text-label-sm font-semibold inline-flex items-center gap-1 {{ $point->statut->badgeClasses() }}"><span class="material-symbols-outlined text-[14px]">{{ $point->statut->icone() }}</span>{{ $point->statut->label() }}</span>
<div class="ml-auto flex flex-wrap gap-2">
@if ($point->estValide())
<a href="{{ route('points.show', $point) }}" target="_blank" class="px-3 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">open_in_new</span>Voir côté habitant</a>
@endif
<a href="{{ route('back.points.edit', $point) }}" class="px-3 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">edit</span>Modifier</a>
<form method="POST" action="{{ route('back.points.destroy', $point) }}" onsubmit="return confirm('Supprimer ce point et ses {{ $point->avis_count }} avis ? Cette action est définitive.');">@csrf @method('DELETE')<button type="submit" class="px-3 py-2 rounded-lg bg-surface-container-high text-error font-label-md text-label-md font-semibold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">delete</span>Supprimer</button></form>
</div>
</div>

{{-- Modération rapide (admin) --}}
@if (auth()->user()->isAdmin() && ! $point->estValide())
<div class="rounded-2xl bg-amber-50 border border-amber-200 p-space-md flex flex-col md:flex-row md:items-center gap-3" x-data="{ refus: false }">
<p class="flex-1 font-body-md text-body-md text-amber-900"><span class="material-symbols-outlined align-middle">pending_actions</span>
@if ($point->estEnAttente()) Ce point attend votre validation avant d'être visible des habitants.
@else Ce point a été refusé : « {{ $point->motif_refus }} ».
@endif</p>
<div class="flex flex-wrap gap-2" x-show="!refus">
<form method="POST" action="{{ route('back.points.valider', $point) }}">@csrf @method('PATCH')<button class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold">Valider</button></form>
@if ($point->estEnAttente())<button type="button" @click="refus = true" class="px-4 py-2 rounded-lg bg-surface-container-high text-error font-label-md text-label-md font-semibold">Refuser</button>@endif
</div>
<form method="POST" action="{{ route('back.points.refuser', $point) }}" x-show="refus" x-cloak class="flex flex-col sm:flex-row gap-2">@csrf @method('PATCH')
<input type="text" name="motif_refus" value="{{ old('motif_refus') }}" required minlength="10" maxlength="500" placeholder="Motif du refus" class="sm:w-72 rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface" />
<button class="px-4 py-2 rounded-lg bg-error text-on-error font-label-md text-label-md font-semibold">Confirmer</button>
</form>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-sm items-start">
{{-- Fiche --}}
<section class="lg:col-span-2 rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 overflow-hidden">
@if ($point->photo_url)
<img src="{{ $point->photo_url }}" alt="Photo de {{ $point->nom }}" class="w-full h-56 md:h-72 object-cover" />
@endif
<div class="p-space-md flex flex-col gap-3">
<x-point-equipements :point="$point" />
<p class="font-body-md text-body-md text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">place</span>{{ $point->adresse }} · {{ $point->quartier?->libelle() ?? 'Aucun quartier proche' }}</p>
@if ($point->description)<p class="font-body-md text-body-md text-on-surface">{{ $point->description }}</p>@endif
<div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
<div class="rounded-lg bg-surface-container p-3"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Capacité</span><span class="font-title-md text-title-md text-on-surface">{{ $point->capacite ? $point->capacite.' pers.' : '—' }}</span></div>
<div class="rounded-lg bg-surface-container p-3"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Horaires</span><span class="font-title-md text-title-md text-on-surface">{{ $point->horaires() }}</span></div>
<div class="rounded-lg bg-surface-container p-3"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">GPS</span><span class="font-body-sm text-body-sm text-on-surface">{{ number_format($point->latitude, 5, '.', '') }}, {{ number_format($point->longitude, 5, '.', '') }}</span></div>
<div class="rounded-lg bg-surface-container p-3"><span class="font-label-sm text-label-sm uppercase text-on-surface-variant block">Proposé par</span><span class="font-body-sm text-body-sm text-on-surface">{{ $point->auteur?->name ?? '—' }}</span></div>
</div>
@if ($point->validateur)
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $point->statut->label() }} par {{ $point->validateur->name }} le {{ $point->valide_le?->format('d/m/Y à H:i') }}</p>
@endif
<div id="carte-point-detail" class="w-full h-56 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
</div>
</section>

{{-- Synthèse des avis + IA --}}
<aside class="flex flex-col gap-3">
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold">Note des habitants</h2>
<div class="mt-2 flex items-end gap-3">
<span class="font-display-sm text-display-sm text-on-surface font-bold">{{ $point->noteMoyenne() !== null ? number_format($point->noteMoyenne(), 1, ',', '') : '—' }}</span>
<div class="pb-1"><x-etoiles :note="$point->noteMoyenne()" :taille="18" /><p class="font-body-sm text-body-sm text-on-surface-variant">{{ $point->avis_count }} avis</p></div>
</div>
<div class="mt-3 flex flex-col gap-1">
@for ($n = 5; $n >= 1; $n--)
@php $nb = (int) ($notes[$n] ?? 0); $pct = $point->avis_count ? round($nb / $point->avis_count * 100) : 0; @endphp
<div class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant"><span class="w-4">{{ $n }}</span><div class="flex-1 h-2 rounded-full bg-surface-container-high overflow-hidden"><div class="h-full bg-amber-500 rounded-full" style="width: {{ $pct }}%"></div></div><span class="w-6 text-right">{{ $nb }}</span></div>
@endfor
</div>
</div>

<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">auto_awesome</span>Sentiment (IA)</h2>
<div class="mt-2 flex flex-wrap gap-2">
@foreach (\App\Enums\Sentiment::cases() as $s)
<span class="px-2.5 py-1 rounded-full font-label-sm text-label-sm font-semibold inline-flex items-center gap-1 {{ $s->badgeClasses() }}"><span class="material-symbols-outlined text-[14px]">{{ $s->icone() }}</span>{{ (int) ($repartition[$s->value] ?? 0) }} {{ mb_strtolower($s->label()) }}(s)</span>
@endforeach
</div>
@if ($diagnostic)
<div class="mt-3 rounded-xl bg-red-50 border border-red-200 p-3">
<p class="font-label-md text-label-md text-red-900 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">crisis_alert</span>Point mal noté récurrent · gravité {{ $diagnostic['gravite'] }}/100</p>
<p class="mt-1 font-body-sm text-body-sm text-red-800">{{ $diagnostic['message'] }}</p>
@if ($synthese)<p class="mt-2 font-body-sm text-body-sm text-on-surface border-t border-red-200 pt-2"><strong>Synthèse IA :</strong> {{ $synthese }}</p>@endif
</div>
@else
<p class="mt-3 font-body-sm text-body-sm text-on-surface-variant">Aucun signal négatif récurrent détecté.</p>
@endif
</div>
</aside>
</div>

{{-- Avis --}}
<section id="avis" class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md flex flex-col gap-3">
<h2 class="font-title-md text-title-md text-on-surface font-semibold">Avis ({{ $avis->total() }})</h2>
@forelse ($avis as $un)
<article class="rounded-xl bg-surface-container p-4 flex flex-col sm:flex-row gap-3">
<div class="flex-1 min-w-0">
<div class="flex flex-wrap items-center gap-2">
<span class="font-title-sm text-title-sm text-on-surface font-medium">{{ $un->user?->name ?? 'Compte supprimé' }}</span>
<x-etoiles :note="$un->note" :taille="14" />
<x-badge-sentiment :sentiment="$un->sentiment" :score="$un->score_sentiment" />
@if ($un->affluence)<span class="font-label-sm text-label-sm text-on-surface-variant">· {{ $un->affluence->label() }}</span>@endif
<span class="font-body-sm text-body-sm text-on-surface-variant">· {{ $un->created_at?->format('d/m/Y H:i') }}</span>
</div>
@if ($un->commentaire)<p class="mt-1 font-body-md text-body-md text-on-surface">« {{ $un->commentaire }} »</p>@endif
</div>
<form method="POST" action="{{ route('back.avis.destroy', $un) }}" onsubmit="return confirm('Supprimer cet avis (modération) ?');" class="shrink-0">@csrf @method('DELETE')<button type="submit" class="inline-flex items-center gap-1 px-3 py-2 rounded-lg text-error hover:bg-error-container/40 font-label-md text-label-md"><span class="material-symbols-outlined text-[18px]">delete</span>Supprimer</button></form>
</article>
@empty
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun avis pour le moment.</p>
@endforelse
{{ $avis->fragment('avis')->links() }}
</section>

@include('points-fraicheur._carte-script', [
    'idCarte' => 'carte-point-detail',
    'marqueurs' => [['lat' => $point->latitude, 'lng' => $point->longitude, 'nom' => $point->nom, 'type' => $point->type->label(), 'couleur' => $point->type->couleurHex(), 'statut' => $point->statut->value, 'lien' => '#']],
])
</x-back-layout>
