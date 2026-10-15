<x-back-layout :title="'Signalements communautaires'">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <p class="text-on-surface-variant">Consultez et gérez les signalements des habitants.</p>
        <a href="{{ route('back.signalements.create') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-container px-4 py-2 font-semibold text-on-primary-container">
            <span class="material-symbols-outlined">add</span>
            Nouveau signalement
        </a>
    </div>

    <section aria-label="Statistiques des signalements" class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <article class="rounded-xl border border-outline-variant/20 bg-surface-container-low p-4">
            <p class="text-sm text-on-surface-variant">Total</p>
            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $stats->total ?? 0 }}</p>
        </article>
        <article class="rounded-xl border border-outline-variant/20 bg-surface-container-low p-4">
            <p class="text-sm text-on-surface-variant">Nouveaux</p>
            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $stats->nouveaux ?? 0 }}</p>
        </article>
        <article class="rounded-xl border border-outline-variant/20 bg-surface-container-low p-4">
            <p class="text-sm text-on-surface-variant">En traitement</p>
            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $stats->en_traitement ?? 0 }}</p>
        </article>
        <article class="rounded-xl border border-outline-variant/20 bg-surface-container-low p-4">
            <p class="text-sm text-on-surface-variant">Résolus</p>
            <p class="mt-1 text-2xl font-bold text-on-surface">{{ $stats->resolus ?? 0 }}</p>
        </article>
        <article class="rounded-xl border border-error/20 bg-error-container p-4">
            <p class="text-sm text-on-error-container">Urgences vitales</p>
            <p class="mt-1 text-2xl font-bold text-on-error-container">{{ $stats->urgences_vitales ?? 0 }}</p>
        </article>
    </section>

    <form method="GET" action="{{ route('back.signalements.index') }}" class="grid grid-cols-1 gap-3 rounded-xl bg-surface-container-low p-4 md:grid-cols-4">
        <select name="categorie" aria-label="Filtrer par catégorie" class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="">Toutes les catégories</option>
            <option value="fuite" @selected(($filters['categorie'] ?? '') === 'fuite')>Fuite</option>
            <option value="panne_locale" @selected(($filters['categorie'] ?? '') === 'panne_locale')>Panne locale</option>
            <option value="personne_vulnerable" @selected(($filters['categorie'] ?? '') === 'personne_vulnerable')>Personne vulnérable</option>
            <option value="autre" @selected(($filters['categorie'] ?? '') === 'autre')>Autre</option>
        </select>
        <select name="urgence" aria-label="Filtrer par urgence" class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="">Toutes les urgences</option>
            <option value="vitale" @selected(($filters['urgence'] ?? '') === 'vitale')>Vitale</option>
            <option value="prioritaire" @selected(($filters['urgence'] ?? '') === 'prioritaire')>Prioritaire</option>
            <option value="normale" @selected(($filters['urgence'] ?? '') === 'normale')>Normale</option>
        </select>
        <select name="statut" aria-label="Filtrer par statut" class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
            <option value="">Tous les statuts</option>
            <option value="nouveau" @selected(($filters['statut'] ?? '') === 'nouveau')>Nouveau</option>
            <option value="en_traitement" @selected(($filters['statut'] ?? '') === 'en_traitement')>En traitement</option>
            <option value="resolu" @selected(($filters['statut'] ?? '') === 'resolu')>Résolu</option>
        </select>
        <button class="rounded-lg bg-primary-container px-3 py-2 text-on-primary-container">Filtrer</button>
    </form>

    <div class="overflow-x-auto rounded-xl bg-surface-container-low shadow-md">
        <table class="min-w-[1250px] w-full">
            <thead>
                <tr class="border-b border-outline-variant/20 text-left">
                    <th class="px-4 py-3">Catégorie</th>
                    <th class="px-4 py-3">Urgence</th>
                    <th class="px-4 py-3">Habitant / résidence</th>
                    <th class="px-4 py-3">Description</th>
                    <th class="px-4 py-3">Analyse locale</th>
                    <th class="px-4 py-3">Statut</th>
                    <th class="px-4 py-3">Date</th>
                    <th class="px-4 py-3">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($signalements as $signalement)
                    <tr class="border-b border-outline-variant/10 align-top">
                        <td class="px-4 py-4">{{ $signalement->categorie === 'autre' ? $signalement->categorie_autre : str_replace('_', ' ', ucfirst($signalement->categorie)) }}</td>
                        <td class="px-4 py-4">
                            <form method="POST" action="{{ route('back.signalements.treatment', $signalement) }}" class="flex flex-col items-start gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="statut" value="{{ $signalement->statut }}">
                                <select name="urgence" aria-label="Urgence du signalement" class="rounded-lg border border-outline-variant/40 bg-surface-container px-2 py-1">
                                    <option value="vitale" @selected($signalement->urgence === 'vitale')>Vitale</option>
                                    <option value="prioritaire" @selected($signalement->urgence === 'prioritaire')>Prioritaire</option>
                                    <option value="normale" @selected($signalement->urgence === 'normale')>Normale</option>
                                </select>
                                <button class="rounded-lg bg-primary-container px-2 py-1 text-xs text-on-primary-container">Modifier l’urgence</button>
                            </form>
                        </td>
                        <td class="px-4 py-4">
                            {{ $signalement->habitant->name }}<br>
                            <span class="text-on-surface-variant">{{ $signalement->residence->nom }}</span>
                        </td>
                        <td class="max-w-sm px-4 py-4">
                            @if ($signalement->termes_urgents_detectes !== [])
                                <div class="mb-2 rounded-lg border border-error/30 bg-error-container px-3 py-2 text-sm text-on-error-container" role="status">
                                    <span class="font-semibold">À vérifier rapidement :</span>
                                    {{ implode(', ', $signalement->termes_urgents_detectes) }} détecté(s) dans la description.
                                    Le niveau d’urgence reste celui choisi par l’habitant.
                                </div>
                            @endif
                            @if ($signalement->signalements_lies !== [])
                                <div class="mb-2 rounded-lg border border-error/30 bg-error-container px-3 py-2 text-sm text-on-error-container" role="status">
                                    <span class="font-semibold">Signalements possiblement liés :</span>
                                    {{ count($signalement->signalements_lies) }} autre(s) signalement(s) semblable(s) dans cette résidence.
                                    <span class="block mt-1">N° {{ implode(', N° ', array_column($signalement->signalements_lies, 'id')) }}</span>
                                </div>
                            @endif
                            {{ $signalement->description }}
                            @if ($signalement->photo_path)
                                <br><a href="{{ asset('storage/'.$signalement->photo_path) }}" target="_blank" rel="noopener" class="font-semibold text-primary hover:underline">Voir la photo</a>
                            @endif
                        </td>
                        <td class="px-4 py-4">
                            @php
                                $urgenceSuggeree = match ($signalement->urgence_suggeree) {
                                    'vitale' => 'Vitale',
                                    'prioritaire' => 'Prioritaire',
                                    default => 'Normale',
                                };
                            @endphp
                            <div class="rounded-lg border border-outline-variant/30 bg-surface-container-high px-3 py-2 text-sm">
                                <p class="font-semibold text-on-surface">Urgence suggérée : {{ $urgenceSuggeree }}</p>
                                <p class="mt-1 text-on-surface-variant">Indice texte : {{ $signalement->score_urgence_texte }} %</p>
                                @if ($signalement->indicateurs_urgence_texte !== [])
                                    <p class="mt-1 text-xs text-on-surface-variant">Signes : {{ implode(', ', $signalement->indicateurs_urgence_texte) }}</p>
                                @endif
                                <p class="mt-1 text-xs text-on-surface-variant">Suggestion uniquement ; urgence enregistrée : {{ ucfirst($signalement->urgence) }}.</p>
                            </div>
                        </td>
                        <td class="px-4 py-4">
                            <form method="POST" action="{{ route('back.signalements.treatment', $signalement) }}" class="flex flex-col items-start gap-2">
                                @csrf
                                @method('PATCH')
                                <input type="hidden" name="urgence" value="{{ $signalement->urgence }}">
                                <select name="statut" aria-label="Statut du signalement" class="rounded-lg border border-outline-variant/40 bg-surface-container px-3 py-2">
                                    <option value="nouveau" @selected($signalement->statut === 'nouveau')>Nouveau</option>
                                    <option value="en_traitement" @selected($signalement->statut === 'en_traitement')>En traitement</option>
                                    <option value="resolu" @selected($signalement->statut === 'resolu')>Résolu</option>
                                </select>
                                <button class="rounded-lg bg-primary-container px-3 py-2 text-on-primary-container">Enregistrer le statut</button>
                            </form>
                        </td>
                        <td class="whitespace-nowrap px-4 py-4">{{ $signalement->date_signalement->format('d/m/Y') }}</td>
                        <td class="px-4 py-4">
                            <div class="flex flex-col items-start gap-2">
                                <a href="{{ route('back.signalements.edit', $signalement) }}" class="rounded-lg border border-outline-variant/40 px-3 py-2 text-primary hover:bg-surface-container-high">Modifier</a>
                                <form method="POST" action="{{ route('back.signalements.destroy', $signalement) }}" onsubmit="return confirm('Supprimer définitivement ce signalement ?');">
                                    @csrf
                                    @method('DELETE')
                                    <button class="rounded-lg bg-error-container px-3 py-2 text-on-error-container">Supprimer</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-6 py-8 text-center text-on-surface-variant">Aucun signalement à traiter.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</x-back-layout>
