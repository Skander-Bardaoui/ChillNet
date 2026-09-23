<x-back-layout :title="'Quartiers'">
<div class="flex justify-between items-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ count($quartiers) }} quartier(s)</p>
<a href="{{ route('back.quartiers.create') }}" class="px-space-md py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add</span>Nouveau quartier</a>
</div>
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20">
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Nom</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Ville</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Code postal</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Résidences</th>
<th class="px-6 py-3"></th>
</tr></thead>
<tbody>
@forelse ($quartiers as $quartier)
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4 font-title-md text-title-md text-on-surface">{{ $quartier->nom }}</td>
<td class="px-6 py-4 text-on-surface-variant">{{ $quartier->ville }}</td>
<td class="px-6 py-4 text-on-surface-variant">{{ $quartier->code_postal }}</td>
<td class="px-6 py-4 text-on-surface-variant">{{ $quartier->residences_count }}</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.quartiers.edit', $quartier->id) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<form method="POST" action="{{ route('back.quartiers.destroy', $quartier->id) }}" class="inline" onsubmit="return confirm('Supprimer ce quartier ?');">@csrf @method('DELETE')<button type="submit" class="text-error hover:underline font-label-md text-label-md">Supprimer</button></form>
</td>
</tr>
@empty
<tr><td colspan="5" class="px-6 py-6 text-center text-on-surface-variant">Aucun quartier pour le moment.</td></tr>
@endforelse
</tbody>
</table>
</div>
</x-back-layout>
