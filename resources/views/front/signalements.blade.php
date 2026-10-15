<x-app-layout>
    <x-slot name="header">
        <div class="flex flex-col gap-3 md:flex-row md:items-end md:justify-between">
            <div>
                <p class="font-label-sm text-label-sm uppercase tracking-[0.18em] text-primary font-semibold">Signalements</p>
                <h1 class="font-headline-lg text-headline-lg text-on-surface">Signalements communautaires</h1>
                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Signalez un problème dans votre résidence et suivez son traitement.</p>
            </div>
            <button type="button" id="notifications-toggle" aria-expanded="false" aria-controls="notifications-panel" class="inline-flex items-center gap-2 self-start rounded-full border border-outline-variant/40 bg-surface-container-low px-3 py-2 text-on-surface shadow-sm transition hover:bg-surface-container-high">
                <span class="material-symbols-outlined text-[18px] text-primary">notifications</span>
                <span class="font-label-md text-label-md">Notifications</span>
                @if ($unreadNotificationsCount > 0)
                    <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-primary-container px-1.5 py-0.5 text-[11px] font-semibold text-on-primary-container">{{ $unreadNotificationsCount }}</span>
                @endif
            </button>
        </div>
    </x-slot>

    @if (session('success'))
        <div class="mb-space-md rounded-2xl border border-primary-container/30 bg-primary-container/10 px-4 py-3 text-sm font-medium text-on-surface shadow-sm">
            {{ session('success') }}
        </div>
    @endif
    @if ($errors->any())
        <div class="mb-space-md rounded-2xl border border-error/20 bg-error-container px-4 py-3 text-sm font-medium text-on-error-container shadow-sm">
            {{ $errors->first() }}
        </div>
    @endif

    <div class="space-y-space-lg">
        <div id="notifications-panel" class="hidden rounded-[28px] border border-outline-variant/30 bg-surface-container-low/90 p-space-md shadow-[0_12px_32px_rgba(15,23,42,0.08)] backdrop-blur-xl md:p-space-lg">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="font-title-md text-title-md text-on-surface">Notifications</h2>
                <span class="inline-flex min-w-[1.5rem] items-center justify-center rounded-full bg-primary-container px-1.5 py-0.5 text-[11px] font-semibold text-on-primary-container">
                    {{ $notifications->count() }}
                </span>
            </div>

            <div class="space-y-3">
                @forelse ($notifications->take(3) as $notification)
                    <div class="rounded-2xl border border-outline-variant/25 bg-surface-container px-4 py-3 shadow-sm">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="font-label-md text-label-md text-on-surface">{{ $notification->data['title'] ?? 'Notification' }}</p>
                                <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">{{ $notification->data['message'] ?? 'Nouveau message' }}</p>
                            </div>
                            <time class="shrink-0 font-body-sm text-body-sm text-on-surface-variant">
                                {{ $notification->created_at->format('d/m/Y H:i') }}
                            </time>
                        </div>
                    </div>
                @empty
                    <div class="flex items-center gap-2 rounded-2xl border border-dashed border-outline-variant/50 bg-surface-container px-4 py-3 text-on-surface-variant">
                        <span class="material-symbols-outlined text-[18px]">inbox</span>
                        <span class="font-body-sm text-body-sm">Aucune notification pour le moment.</span>
                    </div>
                @endforelse
            </div>
        </div>

        <section class="rounded-[28px] border border-outline-variant/30 bg-surface-container-low/90 p-space-md shadow-[0_12px_32px_rgba(15,23,42,0.08)] backdrop-blur-xl md:p-space-lg">
            <div class="mb-space-md flex items-center gap-3">
                <span class="flex h-11 w-11 items-center justify-center rounded-2xl bg-primary-container text-on-primary-container shadow-sm">
                    <span class="material-symbols-outlined text-[24px]">add_circle</span>
                </span>
                <div>
                    <h2 class="font-title-md text-title-md text-on-surface">Nouveau signalement</h2>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">Décrivez précisément le problème rencontré.</p>
                </div>
            </div>

            <form action="{{ route('front.signalements.store') }}" method="POST" enctype="multipart/form-data" class="grid grid-cols-1 gap-space-md md:grid-cols-2">
                @csrf

                <div class="flex flex-col gap-2">
                    <label for="residence_id" class="font-label-md text-label-md text-on-surface">Résidence concernée</label>
                    <select id="residence_id" name="residence_id" required @disabled($residences->isEmpty()) class="rounded-2xl border border-outline-variant/50 bg-surface-container px-space-sm py-3 text-on-surface shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20 disabled:opacity-60">
                        <option value="">Sélectionnez une résidence</option>
                        @foreach ($residences as $residenceOption)
                            <option value="{{ $residenceOption->id }}" @selected((string) old('residence_id', auth()->user()->residence_id) === (string) $residenceOption->id)>
                                {{ $residenceOption->nom }}{{ $residenceOption->quartier ? ' — '.$residenceOption->quartier->nom : '' }}
                            </option>
                        @endforeach
                    </select>
                    @if ($residences->isEmpty())
                        <p class="font-body-sm text-body-sm text-on-surface-variant">Aucune résidence n’est encore disponible. Contactez un gestionnaire pour qu’il en ajoute une.</p>
                    @endif
                </div>

                <div class="flex flex-col gap-2">
                    <label for="categorie" class="font-label-md text-label-md text-on-surface">Catégorie</label>
                    <select id="categorie" name="categorie" required class="rounded-2xl border border-outline-variant/50 bg-surface-container px-space-sm py-3 text-on-surface shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="fuite" @selected(old('categorie', 'fuite') === 'fuite')>Fuite</option>
                        <option value="panne_locale" @selected(old('categorie') === 'panne_locale')>Panne locale</option>
                        <option value="personne_vulnerable" @selected(old('categorie') === 'personne_vulnerable')>Personne vulnérable isolée</option>
                        <option value="autre" @selected(old('categorie') === 'autre')>Autre</option>
                    </select>
                </div>

                <div id="categorie-autre-wrapper" class="hidden flex-col gap-2">
                    <label for="categorie_autre" class="font-label-md text-label-md text-on-surface">Précisez le problème</label>
                    <input id="categorie_autre" name="categorie_autre" type="text" value="{{ old('categorie_autre') }}" maxlength="100" placeholder="Écrivez la catégorie du problème" class="rounded-2xl border border-outline-variant/50 bg-surface-container px-space-sm py-3 text-on-surface placeholder:text-on-surface-variant/80 shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20" />
                </div>

                <div class="flex flex-col gap-2">
                    <label for="urgence" class="font-label-md text-label-md text-on-surface">Niveau d’urgence</label>
                    <select id="urgence" name="urgence" required class="rounded-2xl border border-outline-variant/50 bg-surface-container px-space-sm py-3 text-on-surface shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">
                        <option value="normale" @selected(old('urgence', 'normale') === 'normale')>Normale</option>
                        <option value="prioritaire" @selected(old('urgence') === 'prioritaire')>Prioritaire</option>
                        <option value="vitale" @selected(old('urgence') === 'vitale')>Vitale</option>
                    </select>
                </div>

                <div class="md:col-span-2 flex flex-col gap-2">
                    <label for="description" class="font-label-md text-label-md text-on-surface">Description (20 caractères minimum)</label>
                    <textarea id="description" name="description" rows="5" minlength="20" maxlength="5000" required class="rounded-2xl border border-outline-variant/50 bg-surface-container px-space-sm py-3 text-on-surface placeholder:text-on-surface-variant/80 shadow-sm transition focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20">{{ old('description') }}</textarea>
                </div>

                <div class="md:col-span-2 flex flex-col gap-2">
                    <label for="photo" class="font-label-md text-label-md text-on-surface">Photo (facultative, 5 Mo maximum)</label>
                    <input id="photo" name="photo" type="file" accept="image/*" class="block w-full rounded-2xl border border-dashed border-outline-variant/60 bg-surface-container px-space-sm py-3 text-sm text-on-surface-variant shadow-sm file:mr-4 file:rounded-xl file:border-0 file:bg-primary-container file:px-4 file:py-2 file:text-sm file:font-semibold file:text-on-primary-container" />
                </div>

                <div class="md:col-span-2 flex items-center justify-end">
                    <button type="submit" class="inline-flex items-center justify-center gap-2 rounded-2xl bg-primary-container px-5 py-3 font-label-md text-label-md font-semibold text-on-primary-container shadow-[0_8px_22px_rgba(27,119,186,0.22)] transition hover:-translate-y-0.5 hover:opacity-95">
                        <span class="material-symbols-outlined text-[18px]">send</span>
                        Envoyer le signalement
                    </button>
                </div>
            </form>
        </section>

        <script>
            const categorie = document.getElementById('categorie');
            const categorieAutreWrapper = document.getElementById('categorie-autre-wrapper');
            const categorieAutre = document.getElementById('categorie_autre');
            const notificationsToggle = document.getElementById('notifications-toggle');
            const notificationsPanel = document.getElementById('notifications-panel');

            function toggleCategorieAutre() {
                const isOther = categorie.value === 'autre';
                categorieAutreWrapper.classList.toggle('hidden', !isOther);
                categorieAutreWrapper.classList.toggle('flex', isOther);
                categorieAutre.required = isOther;
            }

            if (notificationsToggle && notificationsPanel) {
                notificationsToggle.addEventListener('click', () => {
                    const isExpanded = notificationsToggle.getAttribute('aria-expanded') === 'true';
                    notificationsToggle.setAttribute('aria-expanded', String(!isExpanded));
                    notificationsPanel.classList.toggle('hidden', isExpanded);
                });
            }

            categorie.addEventListener('change', toggleCategorieAutre);
            toggleCategorieAutre();
        </script>

        <section class="rounded-[28px] border border-outline-variant/30 bg-surface-container-low/90 p-space-md shadow-[0_12px_32px_rgba(15,23,42,0.08)] backdrop-blur-xl md:p-space-lg">
            <div class="mb-space-md flex flex-col gap-2 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <h2 class="font-title-md text-title-md text-on-surface">Mes signalements</h2>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Chaque signalement est associé à la résidence que vous sélectionnez.</p>
                </div>
                <span class="inline-flex items-center gap-2 rounded-full border border-outline-variant/40 bg-surface-container px-3 py-1.5 text-xs font-medium uppercase tracking-[0.12em] text-on-surface-variant">
                    <span class="material-symbols-outlined text-[16px] text-primary">location_home</span>
                    {{ $signalements->count() }} signalement(s)
                </span>
            </div>

            <div class="overflow-hidden rounded-2xl border border-outline-variant/30 bg-surface-container">
                <div class="overflow-x-auto">
                    <table class="min-w-[960px] w-full border-collapse text-left">
                        <thead class="bg-surface-container-high/80">
                            <tr class="border-b border-outline-variant/25 text-on-surface-variant">
                                <th class="px-4 py-3 font-label-sm text-label-sm uppercase tracking-[0.12em]">Catégorie</th>
                                <th class="px-4 py-3 font-label-sm text-label-sm uppercase tracking-[0.12em]">Résidence</th>
                                <th class="px-4 py-3 font-label-sm text-label-sm uppercase tracking-[0.12em]">Urgence</th>
                                <th class="px-4 py-3 font-label-sm text-label-sm uppercase tracking-[0.12em]">Description</th>
                                <th class="px-4 py-3 font-label-sm text-label-sm uppercase tracking-[0.12em]">Statut</th>
                                <th class="px-4 py-3 font-label-sm text-label-sm uppercase tracking-[0.12em]">Date</th>
                                <th class="px-4 py-3 font-label-sm text-label-sm uppercase tracking-[0.12em]">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($signalements as $signalement)
                                @php
                                    $badgeUrgence = match ($signalement->urgence) {
                                        'vitale' => 'bg-error-container text-on-error-container',
                                        'prioritaire' => 'bg-tertiary-container/25 text-tertiary-fixed',
                                        default => 'bg-primary-container/15 text-primary',
                                    };
                                    $badgeStatut = match ($signalement->statut) {
                                        'nouveau' => 'bg-primary-container/15 text-primary',
                                        'en_traitement' => 'bg-tertiary-container/25 text-tertiary-fixed',
                                        default => 'bg-primary-container/20 text-on-surface',
                                    };
                                @endphp
                                <tr class="border-b border-outline-variant/20 transition hover:bg-surface-container-high/40">
                                    <td class="px-4 py-3 font-body-sm text-body-sm text-on-surface">
                                        {{ $signalement->categorie === 'autre' ? $signalement->categorie_autre : str_replace('_', ' ', ucfirst($signalement->categorie)) }}
                                    </td>
                                    <td class="px-4 py-3 font-body-sm text-body-sm text-on-surface">
                                        {{ $signalement->residence?->nom ?? 'Résidence supprimée' }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeUrgence }}">
                                            {{ ucfirst($signalement->urgence) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-body-sm text-body-sm text-on-surface">
                                        {{ \Illuminate\Support\Str::limit($signalement->description, 80) }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold {{ $badgeStatut }}">
                                            {{ str_replace('_', ' ', ucfirst($signalement->statut)) }}
                                        </span>
                                    </td>
                                    <td class="px-4 py-3 font-body-sm text-body-sm text-on-surface-variant">
                                        {{ $signalement->date_signalement->format('d/m/Y') }}
                                    </td>
                                    <td class="px-4 py-3">
                                        <div class="flex flex-wrap items-center gap-2">
                                            <a href="{{ route('front.signalements.pdf', $signalement) }}" class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/40 bg-surface-container px-2.5 py-1.5 text-xs font-semibold text-primary transition hover:bg-surface-container-high">PDF</a>
                                            <a href="{{ route('front.signalements.edit', $signalement) }}" class="inline-flex items-center gap-1 rounded-xl border border-outline-variant/40 bg-surface-container px-2.5 py-1.5 text-xs font-semibold text-on-surface transition hover:bg-surface-container-high">Modifier</a>
                                            <form method="POST" action="{{ route('front.signalements.destroy', $signalement) }}" onsubmit="return confirm('Supprimer ce signalement ?');">
                                                @csrf
                                                @method('DELETE')
                                                <button type="submit" class="inline-flex items-center gap-1 rounded-xl border border-error/20 bg-error-container px-2.5 py-1.5 text-xs font-semibold text-on-error-container transition hover:opacity-90">Supprimer</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" class="px-4 py-8 text-center font-body-sm text-body-sm text-on-surface-variant">
                                        Aucun signalement envoyé.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-app-layout>
