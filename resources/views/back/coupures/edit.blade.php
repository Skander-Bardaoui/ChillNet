<x-back-layout :title="'Modifier la coupure'">
<div class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
<form method="POST" action="#">
@csrf
<div class="flex flex-col gap-2">
<label for="zone" class="font-label-md text-label-md text-on-surface">Zone</label>
<input type="text" id="zone" name="zone" value="Bab Bhar — Centre" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="type" class="font-label-md text-label-md text-on-surface">Type</label>
<select id="type" name="type" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">
<option>Délestage</option>
<option>Surcharge</option>
<option>Panne</option>
<option selected>Maintenance</option>
</select>
</div>
<div class="flex flex-col gap-2">
<label for="statut" class="font-label-md text-label-md text-on-surface">Statut</label>
<select id="statut" name="statut" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">
<option>En cours</option>
<option selected>Prévue</option>
<option>Résolue</option>
</select>
</div>
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="debut" class="font-label-md text-label-md text-on-surface">Début</label>
<input type="datetime-local" id="debut" name="debut" value="2026-08-15T08:00" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
<div class="flex flex-col gap-2">
<label for="fin" class="font-label-md text-label-md text-on-surface">Fin</label>
<input type="datetime-local" id="fin" name="fin" value="2026-08-15T12:00" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
</div>
<div class="mt-4 flex flex-col gap-2">
<label for="message" class="font-label-md text-label-md text-on-surface">Message</label>
<textarea id="message" name="message" rows="4" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">Intervention de maintenance planifiée sur le réseau électrique.</textarea>
</div>
<div class="mt-6 flex justify-end gap-3">
<a href="{{ route('back.coupures.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</div>
</form>
</div>
</x-back-layout>
