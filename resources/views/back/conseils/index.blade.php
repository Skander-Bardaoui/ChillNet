<x-back-layout :title="'Conseils'">
<div class="flex justify-between items-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">3 conseil(s)</p>
<a href="{{ route('back.conseils.create') }}" class="px-space-md py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add</span>Nouveau conseil</a>
</div>
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Titre</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Catégorie</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Équipements liés</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Bien s'hydrater en période de canicule</p></td>
<td class="px-6 py-4 text-on-surface-variant">Hydratation</td>
<td class="px-6 py-4 text-on-surface-variant">Fontaine à eau, Brumisateur</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.conseils.edit', 1) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Réduire sa consommation électrique</p></td>
<td class="px-6 py-4 text-on-surface-variant">Énergie</td>
<td class="px-6 py-4 text-on-surface-variant">Climatiseur, Compteur intelligent</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.conseils.edit', 2) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Protéger ses équipements sensibles</p></td>
<td class="px-6 py-4 text-on-surface-variant">Équipements</td>
<td class="px-6 py-4 text-on-surface-variant">Onduleur, Parasurtenseur</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.conseils.edit', 3) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
</tbody>
</table>
</div>
</x-back-layout>
