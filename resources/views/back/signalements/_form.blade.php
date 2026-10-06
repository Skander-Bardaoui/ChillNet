@csrf

<div class="flex flex-col gap-space-md">

{{-- Section habitant & résidence --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">person</span>Habitant & résidence</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1">
<label for="user_id" class="font-label-md text-label-md text-on-surface font-medium">Habitant <span class="text-error">*</span></label>
<select id="user_id" name="user_id" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('user_id') border-error @enderror">
<option value="">Choisir un habitant</option>
@foreach ($habitants as $habitant)
<option value="{{ $habitant->id }}" @selected((string) old('user_id', $signalement?->user_id) === (string) $habitant->id)>{{ $habitant->name }} — {{ $habitant->email }}</option>
@endforeach
</select>
@error('user_id') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
<label for="residence_id" class="font-label-md text-label-md text-on-surface font-medium">Résidence <span class="text-error">*</span></label>
<select id="residence_id" name="residence_id" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('residence_id') border-error @enderror">
<option value="">Choisir une résidence</option>
@foreach ($residences as $residence)
<option value="{{ $residence->id }}" @selected((string) old('residence_id', $signalement?->residence_id) === (string) $residence->id)>{{ $residence->nom }}</option>
@endforeach
</select>
@error('residence_id') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</div>
</fieldset>

{{-- Section qualification --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">report</span>Qualification</legend>
<div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
<div class="flex flex-col gap-1">
<label for="categorie" class="font-label-md text-label-md text-on-surface font-medium">Catégorie <span class="text-error">*</span></label>
<select id="categorie" name="categorie" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('categorie') border-error @enderror">
<option value="fuite" @selected(old('categorie', $signalement?->categorie) === 'fuite')>Fuite</option>
<option value="panne_locale" @selected(old('categorie', $signalement?->categorie) === 'panne_locale')>Panne locale</option>
<option value="personne_vulnerable" @selected(old('categorie', $signalement?->categorie) === 'personne_vulnerable')>Personne vulnérable isolée</option>
<option value="autre" @selected(old('categorie', $signalement?->categorie) === 'autre')>Autre</option>
</select>
@error('categorie') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div id="categorie-autre-wrapper" class="flex flex-col gap-1">
<label for="categorie_autre" class="font-label-md text-label-md text-on-surface font-medium">Précisez si « Autre »</label>
<input id="categorie_autre" name="categorie_autre" maxlength="100" value="{{ old('categorie_autre', $signalement?->categorie_autre) }}" placeholder="Ex. : éclairage du hall" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('categorie_autre') border-error @enderror">
@error('categorie_autre') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
<label for="urgence" class="font-label-md text-label-md text-on-surface font-medium">Urgence <span class="text-error">*</span></label>
<select id="urgence" name="urgence" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('urgence') border-error @enderror">
<option value="normale" @selected(old('urgence', $signalement?->urgence ?? 'normale') === 'normale')>Normale</option>
<option value="prioritaire" @selected(old('urgence', $signalement?->urgence) === 'prioritaire')>Prioritaire</option>
<option value="vitale" @selected(old('urgence', $signalement?->urgence) === 'vitale')>Vitale</option>
</select>
@error('urgence') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
<label for="statut" class="font-label-md text-label-md text-on-surface font-medium">Statut <span class="text-error">*</span></label>
<select id="statut" name="statut" required class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('statut') border-error @enderror">
<option value="nouveau" @selected(old('statut', $signalement?->statut ?? 'nouveau') === 'nouveau')>Nouveau</option>
<option value="en_traitement" @selected(old('statut', $signalement?->statut) === 'en_traitement')>En traitement</option>
<option value="resolu" @selected(old('statut', $signalement?->statut) === 'resolu')>Résolu</option>
</select>
@error('statut') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</div>
</fieldset>

{{-- Section détails --}}
<fieldset class="rounded-xl border border-outline-variant/30 p-4">
<legend class="px-2 font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">description</span>Détails</legend>
<div class="grid grid-cols-1 gap-4">
<div class="flex flex-col gap-1 sm:max-w-xs">
<label for="date_signalement" class="font-label-md text-label-md text-on-surface font-medium">Date du signalement <span class="text-error">*</span></label>
<input id="date_signalement" name="date_signalement" type="date" required value="{{ old('date_signalement', $signalement ? $signalement->date_signalement->format('Y-m-d') : now()->toDateString()) }}" class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('date_signalement') border-error @enderror">
@error('date_signalement') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
<label for="description" class="font-label-md text-label-md text-on-surface font-medium">Description <span class="text-on-surface-variant font-normal">(20 caractères minimum)</span> <span class="text-error">*</span></label>
<textarea id="description" name="description" rows="5" minlength="20" maxlength="5000" required placeholder="Décrivez la situation, le lieu précis et tout élément utile à l'intervention." class="w-full rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface placeholder:text-outline focus:border-primary-container focus:outline-none @error('description') border-error @enderror">{{ old('description', $signalement?->description) }}</textarea>
@error('description') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>

<div class="flex flex-col gap-1">
<label for="photo" class="font-label-md text-label-md text-on-surface font-medium">Photo <span class="text-on-surface-variant font-normal">(facultative, 5 Mo maximum)</span></label>
<input id="photo" name="photo" type="file" accept="image/*" class="w-full rounded-xl border border-dashed border-outline-variant/40 bg-surface-container px-3 py-2.5 text-on-surface file:mr-3 file:rounded-lg file:border-0 file:bg-surface-container-high file:px-3 file:py-1.5 file:text-on-surface file:font-label-md">
@if ($signalement?->photo_path)
<a href="{{ asset('storage/'.$signalement->photo_path) }}" target="_blank" rel="noopener" class="mt-1 inline-flex items-center gap-1 font-label-sm text-label-sm font-semibold text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">image</span>Voir la photo actuelle</a>
@endif
@error('photo') <p class="mt-1 font-body-sm text-body-sm text-error flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">error</span>{{ $message }}</p> @enderror
</div>
</div>
</fieldset>

<div class="flex flex-wrap gap-3">
<button type="submit" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2 shadow"><span class="material-symbols-outlined text-[18px]">save</span>{{ $signalement ? 'Enregistrer les modifications' : 'Créer le signalement' }}</button>
<a href="{{ route('back.signalements.index') }}" class="px-space-md py-2.5 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center justify-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Annuler</a>
</div>

</div>

<script>
const categorieSelect = document.getElementById('categorie');
const autreWrapper = document.getElementById('categorie-autre-wrapper');
const autreInput = document.getElementById('categorie_autre');

function toggleAutreCategory() {
    const isAutre = categorieSelect.value === 'autre';
    autreWrapper.classList.toggle('hidden', !isAutre);
    autreInput.required = isAutre;
}

categorieSelect.addEventListener('change', toggleAutreCategory);
toggleAutreCategory();
</script>
