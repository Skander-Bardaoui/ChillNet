<x-back-layout :title="'Signalements communautaires'">
<div class="flex justify-between items-center">
<p class="font-body-sm text-body-sm text-on-surface-variant">3 signalement(s) à traiter</p>
</div>
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20">
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Catégorie</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Urgence</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Résidence</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Description</th>
<th class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
<th class="px-6 py-3"></th>
</tr></thead>
<tbody>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4 text-on-surface">Climatisation en panne</td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-red-100 text-red-800 font-label-sm text-label-sm">Haute</span></td>
<td class="px-6 py-4 text-on-surface-variant">Résidence Les Jasmins</td>
<td class="px-6 py-4 text-on-surface-variant">Clim de la salle commune hors service.</td>
<td class="px-6 py-4">
<form method="POST" action="#">
@csrf
<select name="statut" class="rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option selected>Nouveau</option>
<option>En traitement</option>
<option>Résolu</option>
</select>
</form>
</td>
<td class="px-6 py-4 text-right whitespace-nowrap">
<button type="button" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4 text-on-surface">Coupure d'eau</td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-orange-100 text-orange-800 font-label-sm text-label-sm">Moyenne</span></td>
<td class="px-6 py-4 text-on-surface-variant">Résidence El Yasmine</td>
<td class="px-6 py-4 text-on-surface-variant">Pression faible au 3e étage.</td>
<td class="px-6 py-4">
<form method="POST" action="#">
@csrf
<select name="statut" class="rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option>Nouveau</option>
<option selected>En traitement</option>
<option>Résolu</option>
</select>
</form>
</td>
<td class="px-6 py-4 text-right whitespace-nowrap">
<button type="button" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</td>
</tr>
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60">
<td class="px-6 py-4 text-on-surface">Surchauffe ascenseur</td>
<td class="px-6 py-4"><span class="px-2 py-0.5 rounded-md bg-yellow-100 text-yellow-800 font-label-sm text-label-sm">Basse</span></td>
<td class="px-6 py-4 text-on-surface-variant">Résidence Les Oliviers</td>
<td class="px-6 py-4 text-on-surface-variant">Ascenseur bloqué aux heures chaudes.</td>
<td class="px-6 py-4">
<form method="POST" action="#">
@csrf
<select name="statut" class="rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option>Nouveau</option>
<option>En traitement</option>
<option selected>Résolu</option>
</select>
</form>
</td>
<td class="px-6 py-4 text-right whitespace-nowrap">
<button type="button" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</td>
</tr>
</tbody>
</table>
</div>
</x-back-layout>
