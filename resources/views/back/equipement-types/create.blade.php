<x-back-layout :title="'Nouveau type d\'équipement'">

<div class="flex items-center gap-3 mb-2">
    <a href="{{ route('back.equipement-types.index') }}" class="p-2 rounded-lg text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-1 font-label-md text-label-md">
        <span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour
    </a>
    <h1 class="font-headline-sm text-headline-sm text-on-surface">Nouveau type d'équipement</h1>
</div>

<div class="rounded-2xl bg-surface-container-low/80 backdrop-blur-xl shadow-xl p-space-lg max-w-2xl">
    <form method="POST" action="{{ route('back.equipement-types.store') }}">
        @csrf

        @if($errors->any())
            <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-3 text-red-800 font-body-sm text-body-sm">
                <ul class="list-disc list-inside space-y-1">
                    @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Nom --}}
        <div class="flex flex-col gap-1.5">
            <label for="nom" class="font-label-md text-label-md text-on-surface">Nom <span class="text-error">*</span></label>
            <input type="text" id="nom" name="nom" value="{{ old('nom') }}"
                   class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('nom') border-error @enderror"
                   placeholder="Ex. : Respirateur médical" required />
            @error('nom')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        {{-- Slug + Icône --}}
        <div class="mt-4 grid grid-cols-1 md:grid-cols-2 gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="slug" class="font-label-md text-label-md text-on-surface">Slug <span class="text-error">*</span></label>
                <input type="text" id="slug" name="slug" value="{{ old('slug') }}"
                       class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none @error('slug') border-error @enderror"
                       placeholder="respirateur" />
                <p class="font-body-sm text-body-sm text-on-surface-variant">Minuscules, chiffres, underscores uniquement.</p>
                @error('slug')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
            </div>
            <div class="flex flex-col gap-1.5">
                <label for="icone" class="font-label-md text-label-md text-on-surface">Icône Material Symbols</label>
                <input type="text" id="icone" name="icone" value="{{ old('icone', 'devices') }}"
                       class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none"
                       placeholder="devices" />
            </div>
        </div>

        {{-- Description --}}
        <div class="mt-4 flex flex-col gap-1.5">
            <label for="description" class="font-label-md text-label-md text-on-surface">Description</label>
            <textarea id="description" name="description" rows="3"
                      class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none"
                      placeholder="Description courte…">{{ old('description') }}</textarea>
        </div>

        {{-- Options --}}
        <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="flex flex-col gap-1.5">
                <label for="ordre" class="font-label-md text-label-md text-on-surface">Ordre</label>
                <input type="number" id="ordre" name="ordre" value="{{ old('ordre', 0) }}" min="0"
                       class="w-full rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2.5 text-on-surface focus:border-primary-container focus:outline-none" />
            </div>
            <div class="flex items-center gap-3 pt-6">
                <input type="hidden" name="medical" value="0" />
                <input type="checkbox" id="medical" name="medical" value="1" @checked(old('medical'))
                       class="w-4 h-4 accent-primary" />
                <label for="medical" class="font-label-md text-label-md text-on-surface cursor-pointer">
                    Équipement médical
                </label>
            </div>
            <div class="flex items-center gap-3 pt-6">
                <input type="hidden" name="actif" value="0" />
                <input type="checkbox" id="actif" name="actif" value="1" @checked(old('actif', true))
                       class="w-4 h-4 accent-primary" />
                <label for="actif" class="font-label-md text-label-md text-on-surface cursor-pointer">Actif</label>
            </div>
        </div>

        {{-- Note médical --}}
        <div class="mt-3 rounded-lg bg-red-50 border border-red-200 px-3 py-2.5 flex items-start gap-2">
            <span class="material-symbols-outlined text-red-600 text-[18px] mt-0.5">info</span>
            <p class="font-body-sm text-body-sm text-red-800">Les types marqués <strong>médical</strong> obligent l'habitant à renseigner un contact d'urgence lors de la déclaration.</p>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <a href="{{ route('back.equipement-types.index') }}" class="px-4 py-2 rounded-lg font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-2">
                Annuler
            </a>
            <button type="submit" class="px-6 py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">save</span>Enregistrer
            </button>
        </div>
    </form>
</div>

</x-back-layout>
