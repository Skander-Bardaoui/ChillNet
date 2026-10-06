@csrf
<div class="grid grid-cols-1 gap-4 md:grid-cols-2">
    <div class="flex flex-col gap-2">
        <label for="user_id" class="font-label-md text-on-surface">Habitant</label>
        <select id="user_id" name="user_id" required class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="">Choisir un habitant</option>
            @foreach ($habitants as $habitant)
                <option value="{{ $habitant->id }}" @selected((string) old('user_id', $signalement?->user_id) === (string) $habitant->id)>
                    {{ $habitant->name }} — {{ $habitant->email }}
                </option>
            @endforeach
        </select>
        @error('user_id')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-2">
        <label for="residence_id" class="font-label-md text-on-surface">Résidence</label>
        <select id="residence_id" name="residence_id" required class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="">Choisir une résidence</option>
            @foreach ($residences as $residence)
                <option value="{{ $residence->id }}" @selected((string) old('residence_id', $signalement?->residence_id) === (string) $residence->id)>{{ $residence->nom }}</option>
            @endforeach
        </select>
        @error('residence_id')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-2">
        <label for="categorie" class="font-label-md text-on-surface">Catégorie</label>
        <select id="categorie" name="categorie" required class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="fuite" @selected(old('categorie', $signalement?->categorie) === 'fuite')>Fuite</option>
            <option value="panne_locale" @selected(old('categorie', $signalement?->categorie) === 'panne_locale')>Panne locale</option>
            <option value="personne_vulnerable" @selected(old('categorie', $signalement?->categorie) === 'personne_vulnerable')>Personne vulnérable isolée</option>
            <option value="autre" @selected(old('categorie', $signalement?->categorie) === 'autre')>Autre</option>
        </select>
        @error('categorie')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div id="categorie-autre-wrapper" class="flex flex-col gap-2">
        <label for="categorie_autre" class="font-label-md text-on-surface">Précisez si « Autre »</label>
        <input id="categorie_autre" name="categorie_autre" maxlength="100" value="{{ old('categorie_autre', $signalement?->categorie_autre) }}" class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
        @error('categorie_autre')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-2">
        <label for="urgence" class="font-label-md text-on-surface">Urgence</label>
        <select id="urgence" name="urgence" required class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="normale" @selected(old('urgence', $signalement?->urgence ?? 'normale') === 'normale')>Normale</option>
            <option value="prioritaire" @selected(old('urgence', $signalement?->urgence) === 'prioritaire')>Prioritaire</option>
            <option value="vitale" @selected(old('urgence', $signalement?->urgence) === 'vitale')>Vitale</option>
        </select>
        @error('urgence')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-2">
        <label for="statut" class="font-label-md text-on-surface">Statut</label>
        <select id="statut" name="statut" required class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="nouveau" @selected(old('statut', $signalement?->statut ?? 'nouveau') === 'nouveau')>Nouveau</option>
            <option value="en_traitement" @selected(old('statut', $signalement?->statut) === 'en_traitement')>En traitement</option>
            <option value="resolu" @selected(old('statut', $signalement?->statut) === 'resolu')>Résolu</option>
        </select>
        @error('statut')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-2">
        <label for="date_signalement" class="font-label-md text-on-surface">Date du signalement</label>
        <input id="date_signalement" name="date_signalement" type="date" required value="{{ old('date_signalement', $signalement ? $signalement->date_signalement->format('Y-m-d') : now()->toDateString()) }}" class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
        @error('date_signalement')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-2 md:col-span-2">
        <label for="description" class="font-label-md text-on-surface">Description (20 caractères minimum)</label>
        <textarea id="description" name="description" rows="5" minlength="20" maxlength="5000" required class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">{{ old('description', $signalement?->description) }}</textarea>
        @error('description')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>

    <div class="flex flex-col gap-2 md:col-span-2">
        <label for="photo" class="font-label-md text-on-surface">Photo (facultative, 5 Mo maximum)</label>
        <input id="photo" name="photo" type="file" accept="image/*" class="rounded-lg border border-dashed border-outline-variant/40 bg-surface-container px-3 py-2">
        @if ($signalement?->photo_path)
            <a href="{{ asset('storage/'.$signalement->photo_path) }}" target="_blank" rel="noopener" class="text-sm text-primary underline">Voir la photo actuelle</a>
        @endif
        @error('photo')<p class="text-sm text-error">{{ $message }}</p>@enderror
    </div>
</div>

<div class="mt-5 flex flex-wrap gap-3">
    <button type="submit" class="rounded-lg bg-primary-container px-4 py-2 font-semibold text-on-primary-container">{{ $signalement ? 'Enregistrer les modifications' : 'Créer le signalement' }}</button>
    <a href="{{ route('back.signalements.index') }}" class="rounded-lg border border-outline-variant/40 px-4 py-2">Annuler</a>
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
