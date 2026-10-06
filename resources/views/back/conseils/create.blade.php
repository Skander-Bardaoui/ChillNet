<x-back-layout :title="'Nouveau conseil'">

<div class="flex items-center gap-3 mb-2">
    <a href="{{ route('back.conseils.index') }}" class="p-2 rounded-lg text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-1 font-label-md text-label-md">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour
    </a>
    <h1 class="font-headline-sm text-headline-sm text-on-surface">Nouveau conseil</h1>
</div>

<div class="rounded-2xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
    <form method="POST" action="{{ route('back.conseils.store') }}">
        @csrf

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-3 text-red-800 font-body-sm text-body-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Titre --}}
        <div class="flex flex-col gap-1.5">
            <label for="titre" class="font-label-md text-label-md text-on-surface">Titre <span class="text-error">*</span></label>
            <input type="text" id="titre" name="titre" value="{{ old('titre') }}"
                   class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('titre') border-error @enderror"
                   placeholder="Ex. : Protéger son réfrigérateur lors d'une coupure" required />
            @error('titre')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        {{-- Catégorie + Icône --}}
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="categorie" class="font-label-md text-label-md text-on-surface">Catégorie <span class="text-error">*</span></label>
                <select id="categorie" name="categorie"
                        class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('categorie') border-error @enderror">
                    @foreach($categories as $cat)
                        <option value="{{ $cat->value }}" @selected(old('categorie') === $cat->value)>{{ $cat->label() }}</option>
                    @endforeach
                </select>
                @error('categorie')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="icone" class="font-label-md text-label-md text-on-surface">Icône Material Symbols</label>
                <input type="text" id="icone" name="icone" value="{{ old('icone', 'tips_and_updates') }}"
                       class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none"
                       placeholder="tips_and_updates" />
                <p class="font-body-sm text-body-sm text-on-surface-variant">Nom d'icône sur <a href="https://fonts.google.com/icons" target="_blank" class="text-primary hover:underline">fonts.google.com/icons</a></p>
            </div>
        </div>

        {{-- Contenu --}}
        <div class="mt-4 flex flex-col gap-1.5">
            <label for="contenu" class="font-label-md text-label-md text-on-surface">Contenu <span class="text-error">*</span></label>
            <textarea id="contenu" name="contenu" rows="5"
                      class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('contenu') border-error @enderror"
                      placeholder="Description du conseil…" required>{{ old('contenu') }}</textarea>
            @error('contenu')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        {{-- Types d'équipements --}}
        @if($equipementTypes->isNotEmpty())
        <div class="mt-4 flex flex-col gap-1.5">
            <label class="font-label-md text-label-md text-on-surface">Équipements concernés</label>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Laissez vide pour un conseil général (tous les habitants).</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 mt-1">
                @foreach($equipementTypes as $type)
                <label class="flex items-center gap-2 p-2.5 rounded-lg bg-surface-container cursor-pointer hover:bg-surface-container-high transition-colors">
                    <input type="checkbox" name="equipement_type_ids[]" value="{{ $type->id }}"
                           @checked(in_array($type->id, old('equipement_type_ids', [])))
                           class="w-4 h-4 accent-primary" />
                    <span class="material-symbols-outlined text-primary text-[16px]">{{ $type->icone }}</span>
                    <span class="font-body-md text-body-md text-on-surface">{{ $type->nom }}</span>
                    @if($type->medical)
                        <span class="ml-auto px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-label-sm font-label-sm">Médical</span>
                    @endif
                </label>
                @endforeach
            </div>
        </div>
        @endif

        {{-- Options --}}
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="ordre" class="font-label-md text-label-md text-on-surface">Ordre d'affichage</label>
                <input type="number" id="ordre" name="ordre" value="{{ old('ordre', 0) }}" min="0"
                       class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
            </div>
            <div class="flex items-center gap-3 pt-6">
                <input type="hidden" name="actif" value="0" />
                <input type="checkbox" id="actif" name="actif" value="1" @checked(old('actif', true))
                       class="w-4 h-4 accent-primary" />
                <label for="actif" class="font-label-md text-label-md text-on-surface cursor-pointer">Publier immédiatement</label>
            </div>
        </div>

        {{-- Actions --}}
        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('back.conseils.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">close</span>Annuler
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">save</span>Enregistrer
            </button>
        </div>
    </form>
</div>

</x-back-layout>
