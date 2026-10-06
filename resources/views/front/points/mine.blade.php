<x-public-layout title="Mes propositions">
<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('refuges.index') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">arrow_back</span>Points de fraîcheur</a>
<span aria-hidden="true">/</span><span class="text-on-surface font-medium">Mes propositions</span>
</nav>
<header class="flex flex-wrap items-center justify-between gap-3">
<h1 class="font-headline-md text-headline-md text-on-surface font-bold">Mes propositions de points</h1>
<a href="{{ route('points.create') }}" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add_location_alt</span>Proposer un point</a>
</header>

<div class="flex flex-col gap-space-sm">
@forelse ($propositions as $p)
<article class="rounded-2xl bg-surface-container-low shadow-sm p-space-md flex flex-col md:flex-row md:items-center gap-3">
@if ($p->photo_url)
<img src="{{ $p->photo_url }}" alt="" class="w-full md:w-24 h-24 rounded-xl object-cover shrink-0" />
@endif
<div class="flex-1 min-w-0 flex flex-col gap-1">
<div class="flex flex-wrap items-center gap-2">
<a href="{{ route('points.show', $p) }}" class="font-title-md text-title-md text-on-surface font-semibold hover:underline">{{ $p->nom }}</a>
<span class="px-2.5 py-0.5 rounded-full font-label-sm text-label-sm font-semibold inline-flex items-center gap-1 {{ $p->statut->badgeClasses() }}"><span class="material-symbols-outlined text-[14px]">{{ $p->statut->icone() }}</span>{{ $p->statut->label() }}</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $p->type->label() }} · {{ $p->adresse }} · proposé le {{ $p->created_at?->format('d/m/Y') }}</p>
@if ($p->motif_refus)<p class="font-body-sm text-body-sm text-red-800">Motif du refus : {{ $p->motif_refus }}</p>@endif
@if ($p->estValide())<p class="font-body-sm text-body-sm text-green-800">Publié · {{ $p->avis_count }} avis</p>@endif
</div>
@unless ($p->estValide())
<div class="flex gap-2 shrink-0">
<a href="{{ route('points.edit', $p) }}" class="px-4 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">edit</span>Modifier</a>
<form method="POST" action="{{ route('points.destroy', $p) }}" onsubmit="return confirm('Supprimer cette proposition ?');">@csrf @method('DELETE')<button type="submit" class="px-4 py-2 rounded-lg text-error hover:bg-error-container/40 font-label-md text-label-md inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">delete</span>Supprimer</button></form>
</div>
@endunless
</article>
@empty
<div class="rounded-2xl bg-surface-container-low p-space-md text-on-surface-variant">Vous n'avez encore proposé aucun point.</div>
@endforelse
</div>
{{ $propositions->links() }}
</x-public-layout>
