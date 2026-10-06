<x-app-layout>
<x-slot name="header">
    <div class="flex items-center gap-space-sm">
        <div class="p-2 rounded-lg bg-primary/10 text-primary">
            <span class="material-symbols-outlined text-[22px]">home_health</span>
        </div>
        <div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface">Mes équipements sensibles</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Déclarez vos appareils critiques pour recevoir des conseils personnalisés en cas de canicule ou coupure.</p>
        </div>
    </div>
</x-slot>

{{-- Flash --}}
@if(session('success'))
    <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-green-800 font-body-sm text-body-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">check_circle</span>{{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="rounded-xl bg-red-50 border border-red-200 px-4 py-3 text-red-800 font-body-sm text-body-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">error</span>{{ session('error') }}
    </div>
@endif

{{-- Formulaire de déclaration --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-md"
         x-data="{
             typeId: '{{ old('equipement_type_id', '') }}',
             criticite: '{{ old('criticite', 'normale') }}',
             types: {{ $types->map(fn($t) => ['id' => $t->id, 'medical' => $t->medical])->values()->toJson() }},
             get contactObligatoire() {
                 if (this.criticite === 'vitale') return true;
                 const t = this.types.find(t => String(t.id) === String(this.typeId));
                 return t ? t.medical : false;
             }
         }">
    <div class="flex items-center gap-space-sm mb-space-sm">
        <span class="material-symbols-outlined text-primary text-[22px]">add_home</span>
        <h2 class="font-title-md text-title-md text-on-surface">Déclarer un équipement</h2>
    </div>

    @if($errors->any())
        <div class="mb-4 rounded-xl bg-red-50 border border-red-200 p-3 text-red-800 font-body-sm text-body-sm">
            <ul class="list-disc list-inside space-y-1">
                @foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach
            </ul>
        </div>
    @endif

    <form action="{{ route('equipements.store') }}" method="POST" class="grid grid-cols-1 md:grid-cols-2 gap-space-sm">
        @csrf

        {{-- Type --}}
        <div class="flex flex-col gap-1">
            <label for="equipement_type_id" class="font-label-md text-label-md text-on-surface">Type d'équipement <span class="text-error">*</span></label>
            <select id="equipement_type_id" name="equipement_type_id" x-model="typeId" required
                    class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container @error('equipement_type_id') border-error @enderror">
                <option value="">— Choisir un type —</option>
                @foreach($types as $type)
                    <option value="{{ $type->id }}" @selected(old('equipement_type_id') == $type->id)>
                        {{ $type->nom }}@if($type->medical) ⚕@endif
                    </option>
                @endforeach
            </select>
            @error('equipement_type_id')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        {{-- Nom --}}
        <div class="flex flex-col gap-1">
            <label for="nom" class="font-label-md text-label-md text-on-surface">Nom de l'équipement <span class="text-error">*</span></label>
            <input id="nom" name="nom" type="text" value="{{ old('nom') }}" required
                   placeholder="Ex. : Respirateur nocturne, Réfrigérateur cuisine"
                   class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container @error('nom') border-error @enderror" />
            @error('nom')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        {{-- Criticité --}}
        <div class="flex flex-col gap-1">
            <label for="criticite" class="font-label-md text-label-md text-on-surface">Criticité <span class="text-error">*</span></label>
            <select id="criticite" name="criticite" x-model="criticite"
                    class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container @error('criticite') border-error @enderror">
                <option value="normale" @selected(old('criticite','normale')==='normale')>Normale</option>
                <option value="elevee" @selected(old('criticite')==='elevee')>Élevée</option>
                <option value="vitale" @selected(old('criticite')==='vitale')>Vitale (médical)</option>
            </select>
            @error('criticite')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        {{-- Notes --}}
        <div class="flex flex-col gap-1">
            <label for="notes" class="font-label-md text-label-md text-on-surface">Notes (optionnel)</label>
            <input id="notes" name="notes" type="text" value="{{ old('notes') }}"
                   placeholder="Emplacement, remarques…"
                   class="rounded-lg bg-surface-container-high text-on-surface px-space-sm py-2 focus:outline-none border border-outline-variant/30 focus:border-primary-container" />
        </div>

        {{-- Contact urgence — champ conditionnel --}}
        <div class="md:col-span-2 flex flex-col gap-1" x-show="contactObligatoire" x-cloak>
            <label for="contact_urgence" class="font-label-md text-label-md text-error flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">emergency</span>
                Contact d'urgence <span x-text="contactObligatoire ? '(requis)' : ''"></span>
            </label>
            <input id="contact_urgence" name="contact_urgence" type="text" value="{{ old('contact_urgence') }}"
                   placeholder="Ex. : Fille — 06 12 34 56 78"
                   class="rounded-lg bg-surface-container-high text-on-surface placeholder:text-outline px-space-sm py-2 focus:outline-none border border-error/40 focus:border-error @error('contact_urgence') border-error @enderror" />
            <p class="font-body-sm text-body-sm text-on-surface-variant">En cas de coupure, ce contact sera mentionné dans vos recommandations d'urgence.</p>
            @error('contact_urgence')<p class="text-error font-body-sm text-body-sm">{{ $message }}</p>@enderror
        </div>

        <div class="md:col-span-2">
            <button type="submit"
                    class="px-space-md py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2">
                <span class="material-symbols-outlined text-[18px]">save</span>Enregistrer l'équipement
            </button>
        </div>
    </form>
</section>

{{-- Liste des équipements déclarés --}}
<section class="flex flex-col gap-space-sm">
    <div class="flex items-center justify-between px-1">
        <p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant">
            Équipements déclarés — {{ $equipements->count() }}
        </p>
        <a href="{{ route('conseils') }}" class="inline-flex items-center gap-1 text-primary font-label-md text-label-md hover:underline">
            <span class="material-symbols-outlined text-[16px]">tips_and_updates</span>Voir mes conseils →
        </a>
    </div>

    @forelse($equipements as $equipement)
    <article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col md:flex-row md:items-center gap-space-sm
                    {{ $equipement->criticite->value === 'vitale' ? 'border border-error/20' : '' }}">
        {{-- Icône type --}}
        <div class="p-2 rounded-lg shrink-0
                    {{ $equipement->criticite->value === 'vitale' ? 'bg-red-100 text-red-800' : 'bg-primary/10 text-primary' }}">
            <span class="material-symbols-outlined text-[20px]">{{ $equipement->type?->icone ?? 'devices' }}</span>
        </div>

        {{-- Infos --}}
        <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
                <h3 class="font-title-md text-title-md text-on-surface">{{ $equipement->nom }}</h3>
                <span class="px-2 py-0.5 rounded-full text-label-sm font-label-sm {{ $equipement->criticite->badgeClasses() }}">
                    <span class="material-symbols-outlined text-[12px] align-middle">{{ $equipement->criticite->icone() }}</span>
                    {{ $equipement->criticite->label() }}
                </span>
                @if($equipement->type?->medical)
                    <span class="px-2 py-0.5 rounded-full bg-red-50 text-red-700 text-label-sm font-label-sm flex items-center gap-1">
                        <span class="material-symbols-outlined text-[12px]">health_and_safety</span>Médical
                    </span>
                @endif
            </div>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">
                {{ $equipement->type?->nom ?? 'Type inconnu' }}
                @if($equipement->notes) · {{ $equipement->notes }}@endif
            </p>
            @if($equipement->contact_urgence)
                <p class="font-body-sm text-body-sm text-error mt-1 flex items-center gap-1">
                    <span class="material-symbols-outlined text-[14px]">emergency</span>
                    Contact d'urgence : <strong>{{ $equipement->contact_urgence }}</strong>
                </p>
            @endif
        </div>

        {{-- Actions --}}
        <div class="flex items-center gap-3 shrink-0 self-end md:self-center">
            <a href="{{ route('equipements.edit', $equipement) }}"
               class="inline-flex items-center gap-1 text-primary font-label-md text-label-md hover:underline">
                <span class="material-symbols-outlined text-[16px]">edit</span>Modifier
            </a>
            <form method="POST" action="{{ route('equipements.destroy', $equipement) }}"
                  onsubmit="return confirm('Supprimer cet équipement ?')">
                @csrf @method('DELETE')
                <button type="submit" class="inline-flex items-center gap-1 text-on-surface-variant font-label-md text-label-md hover:text-error">
                    <span class="material-symbols-outlined text-[16px]">delete</span>
                </button>
            </form>
        </div>
    </article>
    @empty
    <div class="rounded-xl bg-surface-container-low p-space-lg text-center">
        <span class="material-symbols-outlined text-outline text-[40px]">home_health</span>
        <p class="font-title-md text-title-md text-on-surface mt-2">Aucun équipement déclaré</p>
        <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Déclarez vos appareils sensibles pour recevoir des conseils personnalisés.</p>
    </div>
    @endforelse
</section>

</x-app-layout>
