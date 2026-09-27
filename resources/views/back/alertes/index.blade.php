<x-back-layout :title="'Alertes canicule'">
<div class="flex justify-between items-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">3 alerte(s)</p>
<a href="{{ route('back.alertes.create') }}" class="px-space-md py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add</span>Nouvelle alerte</a>
</div>
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Titre</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Niveau</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Quartier</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Début / Fin</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Vigilance forte chaleur</p><p class="font-body-sm text-body-sm text-on-surface-variant">Seuil 40°C dépassé</p></td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-yellow-100 text-yellow-800 font-label-sm text-label-sm">Jaune</span></td>
<td class="px-6 py-4 text-on-surface-variant">Cité El Khadra</td>
<td class="px-6 py-4 text-on-surface-variant">12/08/2026 10:00<br>14/08/2026 18:00</td>
<td class="px-6 py-4 text-on-surface-variant">Active</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.alertes.edit', 1) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Pic de chaleur attendu</p><p class="font-body-sm text-body-sm text-on-surface-variant">Seuil 43°C prévu</p></td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-orange-100 text-orange-800 font-label-sm text-label-sm">Orange</span></td>
<td class="px-6 py-4 text-on-surface-variant">Menzah 1</td>
<td class="px-6 py-4 text-on-surface-variant">15/08/2026 12:00<br>17/08/2026 20:00</td>
<td class="px-6 py-4 text-on-surface-variant">Programmée</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.alertes.edit', 2) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Canicule extrême</p><p class="font-body-sm text-body-sm text-on-surface-variant">Seuil 46°C critique</p></td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-red-100 text-red-800 font-label-sm text-label-sm">Rouge</span></td>
<td class="px-6 py-4 text-on-surface-variant">Bab Bhar</td>
<td class="px-6 py-4 text-on-surface-variant">18/08/2026 09:00<br>20/08/2026 22:00</td>
<td class="px-6 py-4 text-on-surface-variant">Terminée</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.alertes.edit', 3) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
</tbody>
</table>
</div>
</x-back-layout>
