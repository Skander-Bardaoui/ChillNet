<x-back-layout :title="'Nouvelle alerte'">
<div class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
<form method="POST" action="#">
@csrf
<div class="flex flex-col gap-2">
<label for="titre" class="font-label-md text-label-md text-on-surface">Titre de l'alerte</label>
<input type="text" id="titre" name="titre" value="Vigilance forte chaleur" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="niveau" class="font-label-md text-label-md text-on-surface">Niveau</label>
<select id="niveau" name="niveau" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">
<option value="jaune" selected>Jaune</option>
<option value="orange">Orange</option>
<option value="rouge">Rouge</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label for="quartier" class="font-label-md text-label-md text-on-surface">Quartier</label>
<select id="quartier" name="quartier" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">
<option selected>Cité El Khadra</option>
<option>Menzah 1</option>
<option>Bab Bhar</option>
</select>
</div>
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="debut" class="font-label-md text-label-md text-on-surface">Début</label>
<input type="datetime-local" id="debut" name="debut" value="2026-08-12T10:00" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
<div class="flex flex-col gap-2">
<label for="fin" class="font-label-md text-label-md text-on-surface">Fin</label>
<input type="datetime-local" id="fin" name="fin" value="2026-08-14T18:00" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
</div>
<div class="mt-4 flex flex-col gap-2">
<label for="seuil" class="font-label-md text-label-md text-on-surface">Seuil de température (°C)</label>
<input type="number" id="seuil" name="seuil" value="40" min="30" max="55" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
<div class="mt-4 flex flex-col gap-2">
<label for="message" class="font-label-md text-label-md text-on-surface">Message</label>
<textarea id="message" name="message" rows="4" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">Restez hydratés, évitez les sorties entre 11h et 17h.</textarea>
</div>
<div class="mt-6 flex justify-end gap-3">
<a href="{{ route('back.alertes.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</div>
</form>
</div>
</x-back-layout>
