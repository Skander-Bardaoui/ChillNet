<x-app-layout>
<x-slot name="header"><h1 class="font-headline-sm text-headline-sm text-on-surface">Modifier mon signalement</h1></x-slot>
@if ($errors->any())<div class="rounded-lg bg-red-100 px-4 py-3 text-red-900">{{ $errors->first() }}</div>@endif
<section class="rounded-xl bg-surface-container-low p-space-md shadow-md">
<form action="{{ route('front.signalements.update', $signalement) }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
@csrf @method('PATCH')
<div class="flex flex-col gap-1"><label for="categorie">Catégorie</label><select id="categorie" name="categorie" required class="rounded-lg bg-surface-container-high px-space-sm py-2"><option value="fuite" @selected($signalement->categorie === 'fuite')>Fuite</option><option value="panne_locale" @selected($signalement->categorie === 'panne_locale')>Panne locale</option><option value="personne_vulnerable" @selected($signalement->categorie === 'personne_vulnerable')>Personne vulnérable isolée</option><option value="autre" @selected($signalement->categorie === 'autre')>Autre</option></select></div>
<div class="flex flex-col gap-1"><label for="urgence">Niveau d’urgence</label><select id="urgence" name="urgence" required class="rounded-lg bg-surface-container-high px-space-sm py-2"><option value="normale" @selected($signalement->urgence === 'normale')>Normale</option><option value="prioritaire" @selected($signalement->urgence === 'prioritaire')>Prioritaire</option><option value="vitale" @selected($signalement->urgence === 'vitale')>Vitale</option></select></div>
<div id="categorie-autre-wrapper" class="hidden flex-col gap-1"><label for="categorie_autre">Précisez le problème</label><input id="categorie_autre" name="categorie_autre" value="{{ old('categorie_autre', $signalement->categorie_autre) }}" maxlength="100" class="rounded-lg bg-surface-container-high px-space-sm py-2"></div>
<div class="md:col-span-2 flex flex-col gap-1"><label for="description">Description (20 caractères minimum)</label><textarea id="description" name="description" rows="4" minlength="20" maxlength="5000" required class="rounded-lg bg-surface-container-high px-space-sm py-2">{{ old('description', $signalement->description) }}</textarea></div>
<div class="md:col-span-2 flex flex-col gap-1"><label for="photo">Remplacer la photo (facultatif)</label><input id="photo" name="photo" type="file" accept="image/*" class="rounded-lg bg-surface-container-high px-space-sm py-2"></div>
<div class="md:col-span-2 flex gap-3"><button type="submit" class="rounded-lg bg-primary-container px-space-md py-2.5 text-on-primary-container font-semibold">Enregistrer</button><a href="{{ route('signalements.index') }}" class="rounded-lg border border-outline-variant px-space-md py-2.5">Annuler</a></div>
</form>
</section>
<script>
const categorie = document.getElementById('categorie');
const wrapper = document.getElementById('categorie-autre-wrapper');
const autre = document.getElementById('categorie_autre');
function toggleOther() { const active = categorie.value === 'autre'; wrapper.classList.toggle('hidden', !active); wrapper.classList.toggle('flex', active); autre.required = active; }
categorie.addEventListener('change', toggleOther); toggleOther();
</script>
</x-app-layout>