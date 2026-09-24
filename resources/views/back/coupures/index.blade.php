<x-back-layout :title="'Coupures de courant'">
<div class="flex justify-between items-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">3 coupure(s)</p>
<a href="{{ route('back.coupures.create') }}" class="px-space-md py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add</span>Nouvelle coupure</a>
</div>
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Zone</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Type</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Horaires</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Menzah 1 — Secteur Nord</p></td>
<td class="px-6 py-4 text-on-surface-variant">Délestage</td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-orange-100 text-orange-800 font-label-sm text-label-sm">En cours</span></td>
<td class="px-6 py-4 text-on-surface-variant">12/08/2026 14:00 — 12/08/2026 17:00</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.coupures.edit', 1) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Bab Bhar — Centre</p></td>
<td class="px-6 py-4 text-on-surface-variant">Maintenance</td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-yellow-100 text-yellow-800 font-label-sm text-label-sm">Prévue</span></td>
<td class="px-6 py-4 text-on-surface-variant">15/08/2026 08:00 — 15/08/2026 12:00</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.coupures.edit', 2) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Cité El Khadra — Sud</p></td>
<td class="px-6 py-4 text-on-surface-variant">Panne</td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-green-100 text-green-800 font-label-sm text-label-sm">Résolue</span></td>
<td class="px-6 py-4 text-on-surface-variant">10/08/2026 09:00 — 10/08/2026 11:30</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.coupures.edit', 3) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
<button type="button" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
</td>
</tr>
</tbody>
</table>
</div>
</x-back-layout>
