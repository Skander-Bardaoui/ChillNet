<x-app-layout>
<x-slot name="header">
    <div class="flex items-center gap-space-sm">
        <a href="{{ route('equipements.index') }}" class="p-1.5 rounded-lg text-on-surface-variant hover:bg-surface-container-high">
            <span class="material-symbols-outlined text-[20px]">arrow_back</span>
        </a>
        <div class="p-2 rounded-lg bg-primary/10 text-primary">
            <span class="material-symbols-outlined text-[22px]">edit</span>
        </div>
        <div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface">Modifier l'équipement</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $equipement->nom }}</p>
        </div>
    </div>
</x-slot>

<section class="rounded-xl bg-surface-container-low p-space-md shadow-md max-w-2xl"
         x-data="{
             typeId: '{{ old('equipement_type_id', $equipement->equipement_type_id) }}',
             criticite: '{{ old('criticite', $equipement->criticite->value) }}',
             types: {{ $types->map(fn($t) => ['id' => $t->id, 'medical' => $t->medical])->values()->toJson() }},
             get contactObligatoire() {
                 if (this.criticite === 'vitale') return true;
                 const t = this.types.find(t => String(t.id) === String(this.typeId));
                 return t ? t.medical : false;
             }
         }">

    @if($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-3 text-red-800 font-body-sm text-body-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('equipements.update', $equipement) }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
        @csrf
        @method('PATCH')

        <div class="flex flex-col gap-1">
            <label for="equipement_type_id" class="font-label-md text-label-md text-on-surface">Type d'équipement <span class="text-error">*</span></label>
            <select id="equipement_type_id" name="equipement_type_id" x-model="typeId" required
                    class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container">
                @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected(old('equipement_type_id', $equipement->equipement_type_id) == $type->id)>
                        {{ $type->nom }}@if($type->medical) ⚕@endif
                    </option>
                @endforeach
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="nom" class="font-label-md text-label-md text-on-surface">Nom <span class="text-error">*</span></label>
            <input id="nom" name="nom" type="text" value="{{ old('nom', $equipement->nom) }}" required
                   class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container" />
        </div>

        <div class="flex flex-col gap-1">
            <label for="criticite" class="font-label-md text-label-md text-on-surface">Criticité <span class="text-error">*</span></label>
            <select id="criticite" name="criticite" x-model="criticite"
                    class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container">
                <option value="normale" @selected(old('criticite',$equipement->criticite->value)==='normale')>Normale</option>
                <option value="elevee" @selected(old('criticite',$equipement->criticite->value)==='elevee')>Élevée</option>
                <option value="vitale" @selected(old('criticite',$equipement->criticite->value)==='vitale')>Vitale (médical)</option>
            </select>
        </div>

        <div class="flex flex-col gap-1">
            <label for="notes" class="font-label-md text-label-md text-on-surface">Notes (optionnel)</label>
            <input id="notes" name="notes" type="text" value="{{ old('notes', $equipement->notes) }}"
                   placeholder="Emplacement, remarques…"
                   class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container" />
        </div>

        <div class="md:col-span-2 flex flex-col gap-1" x-show="contactObligatoire" x-cloak>
            <label for="contact_urgence" class="font-label-md text-label-md text-error flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">emergency</span>
                Contact d'urgence (requis)
            </label>
            <input id="contact_urgence" name="contact_urgence" type="text"
                   value="{{ old('contact_urgence', $equipement->contact_urgence) }}"
                   placeholder="Ex. : Fille — 06 12 34 56 78"
                   class="rounded-lg bg-surface-container-high text-on-surface placeholder:text-outline px-space-sm py-2 focus:outline-none border border-error/40" />
            @error('contact_urgence')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2 flex items-center gap-3">
            <button type="submit"
                    class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">save</span>Enregistrer
            </button>
            <a href="{{ route('equipements.index') }}" class="px-4 py-2.5 rounded-lg text-on-surface-variant font-label-md text-label-md hover:bg-surface-container-high">
                Annuler
            </a>
        </div>
    </form>
</section>

</x-app-layout>
