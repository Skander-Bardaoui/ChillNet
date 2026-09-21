@csrf

<div>
    <label for="nom" class="block text-sm font-medium text-gray-700">Nom de la résidence</label>
    <input type="text" id="nom" name="nom" value="{{ old('nom', $residence?->getNom() ?? '') }}"
           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
    @error('nom') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="mt-4">
    <label for="adresse" class="block text-sm font-medium text-gray-700">Adresse</label>
    <input type="text" id="adresse" name="adresse" value="{{ old('adresse', $residence?->getAdresse() ?? '') }}"
           class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
    @error('adresse') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
</div>

<div class="mt-4 grid grid-cols-2 gap-4">
    <div>
        <label for="quartier_id" class="block text-sm font-medium text-gray-700">Quartier</label>
        <select id="quartier_id" name="quartier_id"
                class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
            <option value="">— Sélectionner —</option>
            @foreach ($quartiers as $q)
                <option value="{{ $q->getId() }}" @selected(old('quartier_id', $residence?->getQuartier()->getId() ?? null) == $q->getId())>
                    {{ $q->getNom() }} ({{ $q->getVille() }})
                </option>
            @endforeach
        </select>
        @error('quartier_id') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
    <div>
        <label for="nombre_logements" class="block text-sm font-medium text-gray-700">Nombre de logements</label>
        <input type="number" min="0" id="nombre_logements" name="nombre_logements"
               value="{{ old('nombre_logements', $residence?->getNombreLogements() ?? 0) }}"
               class="mt-1 block w-full border-gray-300 rounded-md shadow-sm focus:border-orange-500 focus:ring-orange-500" required>
        @error('nombre_logements') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
    </div>
</div>

<div class="mt-4 flex gap-6">
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="salle_climatisee" value="1"
               @checked(old('salle_climatisee', $residence?->isSalleClimatisee() ?? false))
               class="rounded border-gray-300 text-cherry focus:ring-orange-500">
        Salle climatisée disponible
    </label>
    <label class="flex items-center gap-2 text-sm text-gray-700">
        <input type="checkbox" name="point_fraicheur" value="1"
               @checked(old('point_fraicheur', $residence?->isPointFraicheur() ?? false))
               class="rounded border-gray-300 text-cherry focus:ring-orange-500">
        Accessible comme point de fraîcheur
    </label>
</div>

<div class="mt-6 flex justify-end gap-3">
    <a href="{{ route('back.residences.index') }}" class="px-4 py-2 rounded-md text-sm text-gray-600 hover:bg-cream">Annuler</a>
    <button type="submit" class="bg-cherry text-white px-4 py-2 rounded-md text-sm hover:bg-cherry-700">Enregistrer</button>
</div>
