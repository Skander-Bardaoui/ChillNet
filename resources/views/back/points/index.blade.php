<x-back-layout :title="'Points de fraîcheur — modération'">
<div class="flex justify-between items-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">3 point(s) proposé(s) par les habitants</p>
<a href="{{ route('back.points.index') }}" class="px-space-md py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">refresh</span>Actualiser</a>
</div>
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20">
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Nom</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Type</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Quartier</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Proposé par</th>
<th class="px-6 py-3"></th>
</tr></thead>
<tbody>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Fontaine Place Centrale</p></td>
<td class="px-6 py-4 text-on-surface-variant">Fontaine</td>
<td class="px-6 py-4 text-on-surface-variant">Bab Bhar</td>
<td class="px-6 py-4 text-on-surface-variant">Habitant : Ahmed B.</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<button type="button" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Valider</button>
<button type="button" class="px-4 py-2 rounded-lg bg-surface-container-high text-error font-label-md text-label-md font-semibold hover:opacity-95">Refuser</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Salle climatisée Menzah</p></td>
<td class="px-6 py-4 text-on-surface-variant">Salle climatisée</td>
<td class="px-6 py-4 text-on-surface-variant">Menzah 1</td>
<td class="px-6 py-4 text-on-surface-variant">Habitante : Sara M.</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<button type="button" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Valider</button>
<button type="button" class="px-4 py-2 rounded-lg bg-surface-container-high text-error font-label-md text-label-md font-semibold hover:opacity-95">Refuser</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4"><p class="font-title-md text-title-md text-on-surface">Parc ombragé El Khadra</p></td>
<td class="px-6 py-4 text-on-surface-variant">Espace vert</td>
<td class="px-6 py-4 text-on-surface-variant">Cité El Khadra</td>
<td class="px-6 py-4 text-on-surface-variant">Habitant : Yassine T.</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<button type="button" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Valider</button>
<button type="button" class="px-4 py-2 rounded-lg bg-surface-container-high text-error font-label-md text-label-md font-semibold hover:opacity-95">Refuser</button>
</td>
</tr>
</tbody>
</table>
</div>
</x-back-layout>
