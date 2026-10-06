<x-back-layout :title="'Conseils'">

{{-- En-tête --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
    <div class="flex items-start gap-3 flex-1">
        <div class="p-3 rounded-xl bg-primary/10 text-primary shrink-0">
            <span class="material-symbols-outlined text-[28px]">tips_and_updates</span>
        </div>
        <div>
            <p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module 4 · Base de conseils</p>
            <p class="font-body-md text-body-md text-on-surface-variant mt-1">Gérez les conseils de prévention canicule et coupures. Liez chaque conseil aux types d'équipements concernés pour personnaliser les recommandations des habitants.</p>
        </div>
    </div>
    <a href="{{ route('back.conseils.create') }}" class="shrink-0 px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center justify-center gap-2 shadow">
        <span class="material-symbols-outlined text-[18px]">add</span>Nouveau conseil
    </a>
</div>

{{-- Stats --}}
<div class="grid grid-cols-3 gap-3">
    <div class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-4 flex items-center gap-3">
        <span class="material-symbols-outlined text-primary text-[28px]">article</span>
        <div><p class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $stats['total'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">total</p></div>
    </div>
    <div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-center gap-3">
        <span class="material-symbols-outlined text-green-700 text-[28px]">check_circle</span>
        <div><p class="font-headline-sm text-headline-sm text-green-900 font-bold">{{ $stats['actifs'] }}</p><p class="font-body-sm text-body-sm text-green-800">actif(s)</p></div>
    </div>
    <div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-center gap-3">
        <span class="material-symbols-outlined text-amber-700 text-[28px]">visibility_off</span>
        <div><p class="font-headline-sm text-headline-sm text-amber-900 font-bold">{{ $stats['inactifs'] }}</p><p class="font-body-sm text-body-sm text-amber-800">inactif(s)</p></div>
    </div>
</div>

{{-- Filtres --}}
<form method="GET" action="{{ route('back.conseils.index') }}" class="flex flex-wrap gap-3 items-end">
    <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Catégorie</label>
        <select name="categorie" class="rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none text-sm">
            <option value="">Toutes</option>
            @foreach($categories as $cat)
                <option value="{{ $cat->value }}" @selected(($filters['categorie'] ?? '') === $cat->value)>{{ $cat->label() }}</option>
            @endforeach
        </select>
    </div>
    <div class="flex flex-col gap-1">
        <label class="font-label-sm text-label-sm text-on-surface-variant">Recherche</label>
        <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Titre…"
               class="rounded-lg bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none text-sm w-56" />
    </div>
    <button type="submit" class="px-4 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">search</span>Filtrer
    </button>
    @if(array_filter($filters))
        <a href="{{ route('back.conseils.index') }}" class="px-4 py-2 rounded-lg text-on-surface-variant font-label-md text-label-md hover:bg-surface-container inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">close</span>Effacer
        </a>
    @endif
</form>

{{-- Flash --}}
@if(session('success'))
    <div class="rounded-xl bg-green-50 border border-green-200 px-4 py-3 text-green-800 font-body-sm text-body-sm flex items-center gap-2">
        <span class="material-symbols-outlined text-[18px]">check_circle</span>{{ session('success') }}
    </div>
@endif

{{-- Table --}}
<div class="rounded-xl bg-surface-container-low shadow-md overflow-hidden">
    <table class="min-w-full">
        <thead>
            <tr class="border-b border-outline-variant/20">
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Titre</th>
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant hidden md:table-cell">Catégorie</th>
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant hidden lg:table-cell">Équipements liés</th>
                <th class="px-5 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
                <th class="px-5 py-3"><span class="sr-only">Actions</span></th>
            </tr>
        </thead>
        <tbody>
            @forelse($conseils as $conseil)
            <tr class="border-b border-outline-variant/10 hover:bg-surface-container/60 transition-colors">
                <td class="px-5 py-4">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary text-[18px]">{{ $conseil->icone }}</span>
                        <p class="font-title-md text-title-md text-on-surface">{{ $conseil->titre }}</p>
                    </div>
                    <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5 line-clamp-1">{{ Str::limit($conseil->contenu, 80) }}</p>
                </td>
                <td class="px-5 py-4 hidden md:table-cell">
                    <span class="px-2 py-0.5 rounded-full text-label-sm font-label-sm {{ $conseil->categorie->badgeClasses() }}">
                        {{ $conseil->categorie->label() }}
                    </span>
                </td>
                <td class="px-5 py-4 hidden lg:table-cell text-on-surface-variant font-body-sm text-body-sm">
                    @if($conseil->equipementTypes->isEmpty())
                        <span class="text-outline">Général</span>
                    @else
                        {{ $conseil->equipementTypes->pluck('nom')->implode(', ') }}
                    @endif
                </td>
                <td class="px-5 py-4">
                    @if($conseil->actif)
                        <span class="px-2 py-0.5 rounded-full bg-green-100 text-green-800 text-label-sm font-label-sm">Actif</span>
                    @else
                        <span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-label-sm font-label-sm">Inactif</span>
                    @endif
                </td>
                <td class="px-5 py-4 text-right space-x-3 whitespace-nowrap">
                    <a href="{{ route('back.conseils.edit', $conseil) }}" class="text-primary hover:underline font-label-md text-label-md">Modifier</a>
                    <form method="POST" action="{{ route('back.conseils.destroy', $conseil) }}" class="inline"
                          onsubmit="return confirm('Supprimer ce conseil ?')">
                        @csrf @method('DELETE')
                        <button type="submit" class="text-error hover:underline font-label-md text-label-md">Supprimer</button>
                    </form>
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="5" class="px-5 py-10 text-center text-on-surface-variant font-body-md text-body-md">
                    Aucun conseil trouvé.
                    <a href="{{ route('back.conseils.create') }}" class="text-primary hover:underline ml-1">Créer le premier →</a>
                </td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{-- Pagination --}}
<div>{{ $conseils->links() }}</div>

{{-- Lien types d'équipements (admin uniquement) --}}
@if(auth()->user()->isAdmin())
    <div class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-space-md flex items-center justify-between">
        <div class="flex items-center gap-3">
            <span class="material-symbols-outlined text-primary text-[24px]">devices</span>
            <div>
                <p class="font-title-md text-title-md text-on-surface">Catalogue des types d'équipements</p>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Gérez les types auxquels les habitants peuvent rattacher leurs équipements sensibles.</p>
            </div>
        </div>
        <a href="{{ route('back.equipement-types.index') }}" class="px-4 py-2 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-1">
            <span class="material-symbols-outlined text-[16px]">arrow_forward</span>Gérer
        </a>
    </div>
@endif

</x-back-layout>
