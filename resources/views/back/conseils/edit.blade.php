<x-back-layout :title="'Modifier le conseil'">
<div class="rounded-xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
<form method="POST" action="#">
@csrf
<div class="flex flex-col gap-2">
<label for="titre" class="font-label-md text-label-md text-on-surface">Titre</label>
<input type="text" id="titre" name="titre" value="Réduire sa consommation électrique" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
</div>
<div class="mt-4 flex flex-col gap-2">
<label for="categorie" class="font-label-md text-label-md text-on-surface">Catégorie</label>
<select id="categorie" name="categorie" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">
<option>Hydratation</option>
<option selected>Énergie</option>
<option>Équipements</option>
</select>
</div>
<div class="mt-4 flex flex-col gap-2">
<label for="contenu" class="font-label-md text-label-md text-on-surface">Contenu</label>
<textarea id="contenu" name="contenu" rows="5" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">Limitez l'usage du climatiseur à 26°C et débranchez les appareils en veille.</textarea>
</div>
<div class="mt-6 flex justify-end gap-3">
<a href="{{ route('back.conseils.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</div>
</form>
</div>
</x-back-layout>
