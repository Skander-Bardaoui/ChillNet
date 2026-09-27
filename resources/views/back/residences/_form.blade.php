@csrf
<div class="flex flex-col gap-2">
<label for="nom" class="font-label-md text-label-md text-on-surface">Nom de la résidence</label>
<input type="text" id="nom" name="nom" value="{{ old('nom', $residence?->nom ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('nom') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="mt-4 flex flex-col gap-2">
<label for="adresse" class="font-label-md text-label-md text-on-surface">Adresse</label>
<input type="text" id="adresse" name="adresse" value="{{ old('adresse', $residence?->adresse ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('adresse') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="quartier_id" class="font-label-md text-label-md text-on-surface">Quartier</label>
<select id="quartier_id" name="quartier_id" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">— Sélectionner —</option>
@foreach ($quartiers as $q)
<option value="{{ $q->id }}" @selected(old('quartier_id', $residence?->quartier_id) == $q->id)>{{ $q->nom }} ({{ $q->ville }})</option>
@endforeach
</select>
@error('quartier_id') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-2">
<label for="nombre_logements" class="font-label-md text-label-md text-on-surface">Nombre de logements</label>
<input type="number" min="0" id="nombre_logements" name="nombre_logements" value="{{ old('nombre_logements', $residence?->nombre_logements ?? 0) }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('nombre_logements') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
</div>
<div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-3">
<label class="flex items-start gap-3 p-4 rounded-xl bg-surface-container-high cursor-pointer hover:bg-surface-container-highest transition-all"><input type="checkbox" name="salle_climatisee" value="1" @checked(old('salle_climatisee', $residence?->salle_climatisee ?? false)) class="mt-1 w-5 h-5 accent-primary-container rounded" /><span class="font-title-md text-title-md text-on-surface">Salle climatisée disponible</span></label>
<label class="flex items-start gap-3 p-4 rounded-xl bg-surface-container-high cursor-pointer hover:bg-surface-container-highest transition-all"><input type="checkbox" name="point_fraicheur" value="1" @checked(old('point_fraicheur', $residence?->point_fraicheur ?? false)) class="mt-1 w-5 h-5 accent-primary-container rounded" /><span class="font-title-md text-title-md text-on-surface">Point de fraîcheur</span></label>
</div>
<div class="mt-6 flex justify-end gap-3">
<a href="{{ route('back.residences.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high">Annuler</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</div>
