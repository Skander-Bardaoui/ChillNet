@csrf

<div>
    <label for="nom" class="block text-sm font-medium text-gray-700">Nom du quartier</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $quartier?->getNom() ?? '') }}"
           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
    @error('nom') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="mt-4 grid grid-cols-2 gap-4">
    <div>
        <label for="ville" class="block text-sm font-medium text-gray-700">Ville</label>
        <input type="text" id="ville" name="ville" value="{{ old('ville', $quartier?->getVille() ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
        @error('ville') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="code_postal" class="block text-sm font-medium text-gray-700">Code postal</label>
        <input type="text" id="code_postal" name="code_postal" value="{{ old('code_postal', $quartier?->getCodePostal() ?? '') }}"
               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
        @error('code_postal') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-4">
    <label for="description" class="block text-sm font-medium text-gray-700">Description (optionnel)</label>
    <textarea id="description" name="description" rows="4"
              class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500">{{ old('description', $quartier?->getDescription() ?? '') }}</textarea>
    @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('back.quartiers.index') }}" class="px-4 py-2 rounded-md text-sm text-gray-600 hover:bg-cream">Annuler</a>
    <button type="submit" class="bg-cherry text-white px-4 py-2 rounded-md text-sm hover:bg-cherry-700">Enregistrer</button>
</div>
