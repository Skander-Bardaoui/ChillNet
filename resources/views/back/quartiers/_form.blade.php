@csrf
<div class="flex flex-col gap-2">
<label for="nom" class="font-label-md text-label-md text-on-surface">Nom du quartier</label>
<input type="text" id="nom" name="nom" value="{{ old('nom', $quartier?->nom ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('nom') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="ville" class="font-label-md text-label-md text-on-surface">Ville</label>
<input type="text" id="ville" name="ville" value="{{ old('ville', $quartier?->ville ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('ville') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-2">
<label for="code_postal" class="font-label-md text-label-md text-on-surface">Code postal</label>
<input type="text" id="code_postal" name="code_postal" value="{{ old('code_postal', $quartier?->code_postal ?? '') }}" required class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('code_postal') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
</div>
<div class="mt-4 grid grid-cols-2 gap-4">
<div class="flex flex-col gap-2">
<label for="latitude" class="font-label-md text-label-md text-on-surface">Latitude (carte)</label>
<input type="number" step="0.0000001" min="-90" max="90" id="latitude" name="latitude" value="{{ old('latitude', $quartier?->latitude ?? '') }}" placeholder="36.8008" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('latitude') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="flex flex-col gap-2">
<label for="longitude" class="font-label-md text-label-md text-on-surface">Longitude (carte)</label>
<input type="number" step="0.0000001" min="-180" max="180" id="longitude" name="longitude" value="{{ old('longitude', $quartier?->longitude ?? '') }}" placeholder="10.1800" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
@error('longitude') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
</div>
<div class="mt-4 flex flex-col gap-2">
<label for="description" class="font-label-md text-label-md text-on-surface">Description (optionnel)</label>
<textarea id="description" name="description" rows="4" class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none">{{ old('description', $quartier?->description ?? '') }}</textarea>
@error('description') <p class="font-body-sm text-body-sm text-error">{{ $message }}</p> @enderror
</div>
<div class="mt-6 flex justify-end gap-3">
<a href="{{ route('back.quartiers.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high">Annuler</a>
<button type="submit" class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Enregistrer</button>
</div>
