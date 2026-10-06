<x-back-layout :title="'Types d\'équipements'">

{{-- En-tête --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
    <div class="flex items-start gap-3 flex-1">
        <div class="p-3 rounded-xl bg-purple-100 text-purple-800 shrink-0">
            <span class="material-symbols-outlined text-[28px]">devices</span>
        </div>
        <div>
            <p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module 4 · Catalogue des équipements</p>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Définissez les types d'équipements sensibles que les habitants peuvent déclarer. Les types <strong>médicaux</strong> imposent un contact d'urgence.</p>
        </div>
    </div>
    <a href="{{ route('back.equipement-types.create') }}" class="shrink-0 px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center justify-center gap-2 shadow">
        <span class="material-symbols-outlined text-[18px]">add</span>Nouveau type
    </a>
</div>

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

{{-- Table --}}
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
    <table class="min-w-full">
        <thead>
            <tr class="border-b border-outline-variant/20">
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Type</th>
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant hidden md:table-cell">Slug</th>
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Médical</th>
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant hidden lg:table-cell">Équipements déclarés</th>
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
                <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($types as $type)
            <tr class="border-b border-outline-variant/10 hover:bg-surface-container/60 transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-2">
                        <div class="p-1.5 rounded-lg bg-surface-container text-primary">
                            <span class="material-symbols-outlined text-[18px]">{{ $type->icone }}</span>
                        </div>
                        <p class="font-title-md text-title-md text-on-surface">{{ $type->nom }}</p>
                    </div>
                    @if($type->description)
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5 line-clamp-1 ml-9">{{ $type->description }}</p>
                    @endif
                </td>
                <td class="px-5 py-4 hidden md:table-cell">
                    <code class="font-body-sm text-body-sm bg-surface-container px-2 py-0.5 rounded text-on-surface-variant">{{ $type->slug }}</code>
                </td>
                <td class="px-5 py-4">
                    @if($type->medical)
                        <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-label-sm font-label-sm flex items-center gap-1 w-fit">
                            <span class="material-symbols-outlined text-[14px]">health_and_safety</span>Oui
                        </span>
                    @else
                        <span class="text-on-surface-variant font-body-sm text-body-sm">—</span>
                    @endif
                </td>
                <td class="px-5 py-4 hidden lg:table-cell">
                    <span class="font-title-md text-title-md text-on-surface">{{ $type->equipements_count }}</span>
                    <span class="font-body-sm text-body-sm text-on-surface-variant ml-1">déclaré(s)</span>
                </td>
                <td class="px-5 py-4">
                    @if($type->actif)
                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-label-sm font-label-sm">Actif</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-label-sm font-label-sm">Inactif</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-right space-x-3 whitespace-nowrap">
                    <a href="{{ route('back.equipement-types.edit', $type) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
                    <form method="POST" action="{{ route('back.equipement-types.destroy', $type) }}" class="inline"
                          onsubmit="return confirm('Supprimer ce type ? Impossible s\'il est utilisé par des habitants.')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="6" class="px-5 py-10 text-center text-on-surface-variant font-body-md text-body-md">
                    Aucun type d'équipement défini.
                    <a href="{{ route('back.equipement-types.create') }}" class="text-primary hover:underline ml-1">Créer le premier →</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

<div>{{ $types->links() }}</div>

<div class="flex justify-start">
    <a href="{{ route('back.conseils.index') }}" class="inline-flex items-center gap-2 text-on-surface-variant hover:text-primary font-label-md text-label-md">
        <span class="material-symbols-outlined text-[16px]">arrow_back</span>Retour aux conseils
    </a>
</div>

</x-back-layout>
