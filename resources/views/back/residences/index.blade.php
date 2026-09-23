<x-back-layout :title="'Résidences'">
<div class="flex justify-between items-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ count($residences) }} résidence(s)</p>
<a href="{{ route('back.residences.create') }}" class="px-space-md py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add</span>Nouvelle résidence</a>
</div>
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20">
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Nom</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Quartier</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Logements</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Équipements</th>
<th class="px-6 py-3"></th>
</tr></thead>
<tbody>
@forelse ($residences as $residence)
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">{{ $residence->nom }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">{{ $residence->adresse }}</p></td>
<td class="px-6 py-4 text-on-surface-variant">{{ $residence->quartier?->nom ?? '—' }}</td>
<td class="px-6 py-4 text-on-surface-variant">{{ $residence->nombre_logements }}</td>
<td class="px-6 py-4"><div class="flex gap-2">@if ($residence->salle_climatisee)<span class="px-2 py-0.5 rounded-md bg-surface-variant text-primary font-label-sm text-label-sm">Salle clim.</span>@endif @if ($residence->point_fraicheur)<span class="px-2 py-0.5 rounded-md bg-primary-container/15 text-primary font-label-sm text-label-sm">Pt fraîcheur</span>@endif</div></td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.residences.edit', $residence->id) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<form method="POST" action="{{ route('back.residences.destroy', $residence->id) }}" class="inline" onsubmit="return confirm('Supprimer cette résidence ?');">@csrf @method('DELETE')<button type="submit" class="text-error hover:underline font-label-md text-label-md">Supprimer</button></form>
</td>
</tr>
@empty
<tr><td colspan="5" class="px-6 py-6 text-center text-on-surface-variant">Aucune résidence pour le moment.</td></tr>
@endforelse
</tbody>
</table>
</div>
</x-back-layout>
