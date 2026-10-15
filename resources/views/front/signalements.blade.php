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
                    <div class="flex flex-wrap items-center gap-2">
                        <button
                            type="button"
                            id="analyze-signalement"
                            data-url="{{ route('front.signalements.analyze') }}"
                            class="inline-flex items-center gap-2 rounded-xl border border-primary/30 bg-primary-container/10 px-3 py-2 text-sm font-semibold text-primary transition hover:bg-primary-container/20 disabled:cursor-wait disabled:opacity-60"
                        >
                            <span class="material-symbols-outlined text-[18px]" aria-hidden="true">psychology</span>
                            <span id="analyze-signalement-label">Analyser mon signalement</span>
                        </button>
                        <p class="text-xs text-on-surface-variant">Analyse locale, sans clé API. La catégorie est une suggestion ; l’urgence reste votre choix.</p>
                    </div>
                    <div id="signalement-analysis" class="hidden rounded-xl border border-outline-variant/40 bg-surface-container-high p-3 text-sm text-on-surface" role="status" aria-live="polite">
                        <p id="signalement-analysis-category"></p>
                        <p id="signalement-analysis-confidence" class="mt-1 text-xs text-on-surface-variant"></p>
                        <button type="button" id="use-analysis-category" class="mt-2 hidden rounded-lg bg-primary-container px-3 py-1.5 font-semibold text-on-primary-container">Utiliser cette catégorie</button>
                    </div>
                    <p id="signalement-analysis-status" class="text-sm text-on-surface-variant" role="status" aria-live="polite"></p>
                    <p id="urgent-description-alert" class="hidden rounded-xl border border-error/30 bg-error-container px-3 py-2 text-sm text-on-error-container" role="status" aria-live="polite"></p>
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

        <section class="rounded-[28px] border border-primary/20 bg-surface-container-low/90 p-space-md shadow-[0_12px_32px_rgba(15,23,42,0.08)] md:p-space-lg" aria-labelledby="support-assistant-title">
            <div class="flex items-start gap-3">
                <span class="material-symbols-outlined rounded-xl bg-primary-container/15 p-2 text-primary" aria-hidden="true">support_agent</span>
                <div>
                    <h2 id="support-assistant-title" class="font-title-md text-title-md text-on-surface">Assistant support — estimation d’urgence</h2>
                    <p class="mt-1 font-body-sm text-body-sm text-on-surface-variant">Décris ta situation séparément de ton signalement pour obtenir un indice d’urgence. Le message n’est ni envoyé à un service externe ni enregistré.</p>
                </div>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-[1fr_auto]">
                <label class="sr-only" for="support-message">Décris ta situation pour l’assistant support</label>
                <textarea id="support-message" rows="3" maxlength="2000" placeholder="Ex. : une odeur de gaz dans le couloir…" class="w-full rounded-xl border border-outline-variant/50 bg-surface-container px-4 py-3 text-on-surface placeholder:text-on-surface-variant/80 focus:border-primary focus:outline-none focus:ring-2 focus:ring-primary/20"></textarea>
                <button type="button" id="support-estimate-button" data-url="{{ route('front.support.estimate') }}" class="inline-flex items-center justify-center gap-2 self-start rounded-xl bg-primary-container px-4 py-3 font-semibold text-on-primary-container transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60">
                    <span class="material-symbols-outlined text-[18px]" aria-hidden="true">analytics</span>
                    <span id="support-estimate-label">Évaluer l’urgence</span>
                </button>
            </div>
            <p class="mt-2 text-xs text-on-surface-variant">Le pourcentage est un indice indicatif, pas une probabilité médicale ni un diagnostic. En cas de danger immédiat, appelle les secours.</p>
            <div id="support-estimate-result" class="mt-4 hidden rounded-xl border border-outline-variant/40 bg-surface-container-high p-4" role="status" aria-live="polite">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <p id="support-estimate-level" class="font-semibold text-on-surface"></p>
                    <p class="text-sm font-bold text-on-surface"><span id="support-estimate-percent"></span> %</p>
                </div>
                <div class="mt-2 h-3 overflow-hidden rounded-full bg-surface-variant" role="progressbar" aria-label="Indice indicatif d’urgence" aria-valuemin="0" aria-valuemax="100" id="support-estimate-progress">
                    <div id="support-estimate-bar" class="h-full rounded-full bg-primary-container transition-all duration-300" style="width: 0%"></div>
                </div>
                <p id="support-estimate-reasons" class="mt-2 text-sm text-on-surface-variant"></p>
                <p id="support-estimate-immediate" class="mt-3 hidden rounded-lg border border-error/30 bg-error-container px-3 py-2 text-sm font-medium text-on-error-container">
                    Cette description contient des signes de danger immédiat. N’attends pas la réponse du site : appelle le SAMU au 190 ou la Protection civile au 198.
                </p>
            </div>
            <p id="support-estimate-error" class="mt-3 hidden text-sm text-error" role="alert"></p>
        </section>

        <script>
            const categorie = document.getElementById('categorie');
            const categorieAutreWrapper = document.getElementById('categorie-autre-wrapper');
            const categorieAutre = document.getElementById('categorie_autre');
            const notificationsToggle = document.getElementById('notifications-toggle');
            const notificationsPanel = document.getElementById('notifications-panel');
            const description = document.getElementById('description');
            const analyzeButton = document.getElementById('analyze-signalement');
            const analyzeLabel = document.getElementById('analyze-signalement-label');
            const analysisPanel = document.getElementById('signalement-analysis');
            const analysisCategory = document.getElementById('signalement-analysis-category');
            const analysisConfidence = document.getElementById('signalement-analysis-confidence');
            const analysisStatus = document.getElementById('signalement-analysis-status');
            const useAnalysisCategory = document.getElementById('use-analysis-category');
            const urgentDescriptionAlert = document.getElementById('urgent-description-alert');
            let suggestedCategory = null;

            function toggleCategorieAutre() {
                const isOther = categorie.value === 'autre';
                categorieAutreWrapper.classList.toggle('hidden', !isOther);
                categorieAutreWrapper.classList.toggle('flex', isOther);
                categorieAutre.required = isOther;
            }

            function detectUrgentTerms() {
                const texte = description.value
                    .toLocaleLowerCase('fr')
                    .normalize('NFD')
                    .replace(/[\u0300-\u036f]/g, '');
                const termes = [];

                if (/\bdanger\b|\bdangereux\b|\bdangereuse\b/.test(texte)) {
                    termes.push('danger');
                }
                if (/\bpersonne\s+(?:est\s+)?bloque(?:e|es|s)?\b/.test(texte)) {
                    termes.push('personne bloquée');
                }
                if (/\bpersonne\s+(?:est\s+)?coince(?:e|es|s)?\b/.test(texte)) {
                    termes.push('personne coincée');
                }

                urgentDescriptionAlert.textContent = termes.length
                    ? `Attention : ${termes.join(', ')}. Le gestionnaire sera alerté à la lecture de votre signalement. Vous gardez le choix du niveau d’urgence.`
                    : '';
                urgentDescriptionAlert.classList.toggle('hidden', termes.length === 0);
            }

            function categoryLabel(value) {
                return {
                    fuite: 'Fuite',
                    panne_locale: 'Panne locale',
                    personne_vulnerable: 'Personne vulnérable isolée',
                    autre: 'Autre',
                }[value] || 'Autre';
            }

            function resetAnalysisOnEdit() {
                suggestedCategory = null;
                analysisPanel.classList.add('hidden');
                analysisStatus.textContent = '';
                useAnalysisCategory.classList.add('hidden');
                detectUrgentTerms();
            }

            if (notificationsToggle && notificationsPanel) {
                notificationsToggle.addEventListener('click', () => {
                    const isExpanded = notificationsToggle.getAttribute('aria-expanded') === 'true';
                    notificationsToggle.setAttribute('aria-expanded', String(!isExpanded));
                    notificationsPanel.classList.toggle('hidden', isExpanded);
                });
            }

            const supportButton = document.getElementById('support-estimate-button');
            const supportMessage = document.getElementById('support-message');
            const supportLabel = document.getElementById('support-estimate-label');
            const supportResult = document.getElementById('support-estimate-result');
            const supportLevel = document.getElementById('support-estimate-level');
            const supportPercent = document.getElementById('support-estimate-percent');
            const supportProgress = document.getElementById('support-estimate-progress');
            const supportBar = document.getElementById('support-estimate-bar');
            const supportReasons = document.getElementById('support-estimate-reasons');
            const supportImmediate = document.getElementById('support-estimate-immediate');
            const supportError = document.getElementById('support-estimate-error');

            supportButton.addEventListener('click', async () => {
                const texte = supportMessage.value.trim();
                supportError.classList.add('hidden');
                supportResult.classList.add('hidden');
                if (texte.length < 2) {
                    supportError.textContent = 'Décrivez votre situation en quelques mots pour obtenir une estimation.';
                    supportError.classList.remove('hidden');
                    supportMessage.focus();
                    return;
                }

                supportButton.disabled = true;
                supportLabel.textContent = 'Évaluation…';
                try {
                    const response = await fetch(supportButton.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({ message: texte }),
                    });
                    const data = await response.json();
                    if (!response.ok) {
                        throw new Error(data.message || 'L’évaluation n’a pas abouti. Réessayez.');
                    }

                    supportLevel.textContent = `Niveau estimé : ${data.niveau}`;
                    supportPercent.textContent = data.pourcentage;
                    supportProgress.setAttribute('aria-valuenow', data.pourcentage);
                    supportBar.style.width = `${data.pourcentage}%`;
                    supportBar.className = `h-full rounded-full transition-all duration-300 ${data.urgence_immediate ? 'bg-error' : (data.pourcentage >= 50 ? 'bg-tertiary-container' : 'bg-primary-container')}`;
                    supportReasons.textContent = data.indicateurs.length
                        ? `Signes repérés : ${data.indicateurs.join(', ')}.`
                        : 'Aucun mot-clé d’urgence particulier détecté.';
                    supportImmediate.classList.toggle('hidden', !data.urgence_immediate);
                    supportResult.classList.remove('hidden');
                } catch (error) {
                    supportError.textContent = error.message || 'Impossible d’évaluer la situation pour le moment.';
                    supportError.classList.remove('hidden');
                } finally {
                    supportButton.disabled = false;
                    supportLabel.textContent = 'Évaluer l’urgence';
                }
            });

            analyzeButton.addEventListener('click', async () => {
                const texte = description.value.trim();
                if (!texte) {
                    analysisStatus.textContent = 'Écrivez une description avant de lancer l’analyse.';
                    description.focus();
                    return;
                }

                analyzeButton.disabled = true;
                analyzeLabel.textContent = 'Analyse en cours…';
                analysisStatus.textContent = '';
                analysisPanel.classList.add('hidden');

                try {
                    const response = await fetch(analyzeButton.dataset.url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('input[name="_token"]').value,
                        },
                        body: JSON.stringify({ description: texte }),
                    });
                    const resultat = await response.json();

                    if (!response.ok) {
                        throw new Error(resultat.message || 'L’analyse n’a pas pu être effectuée.');
                    }

                    suggestedCategory = resultat.categorie;
                    analysisCategory.textContent = `Catégorie suggérée : ${categoryLabel(resultat.categorie)}`;
                    analysisConfidence.textContent = `Score indicatif du modèle : ${resultat.confiance} %. Ce score n’est pas une probabilité garantie.`;
                    useAnalysisCategory.classList.remove('hidden');
                    analysisPanel.classList.remove('hidden');

                    if (resultat.termes_urgents.length > 0) {
                        urgentDescriptionAlert.textContent = `Attention : ${resultat.termes_urgents.join(', ')}. Le gestionnaire sera alerté à la lecture de votre signalement. Vous gardez le choix du niveau d’urgence.`;
                        urgentDescriptionAlert.classList.remove('hidden');
                    }
                } catch (error) {
                    analysisStatus.textContent = error.message || 'L’analyse locale a échoué.';
                } finally {
                    analyzeButton.disabled = false;
                    analyzeLabel.textContent = 'Analyser mon signalement';
                }
            });

            useAnalysisCategory.addEventListener('click', () => {
                if (!suggestedCategory) {
                    return;
                }
                categorie.value = suggestedCategory;
                categorie.dispatchEvent(new Event('change'));
                analysisStatus.textContent = 'Catégorie appliquée. Vérifiez-la et choisissez vous-même le niveau d’urgence.';
            });

            categorie.addEventListener('change', toggleCategorieAutre);
            description.addEventListener('input', resetAnalysisOnEdit);
            toggleCategorieAutre();
            detectUrgentTerms();
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
