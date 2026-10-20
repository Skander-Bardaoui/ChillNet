<x-app-layout>
<x-slot name="header">
    <div class="flex items-center gap-space-sm flex-wrap">
        <div class="p-2 rounded-lg bg-primary/10 text-primary">
            <span class="material-symbols-outlined text-[22px]">tips_and_updates</span>
        </div>
        <div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface">Mes conseils personnalisés</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Analyse de risque IA · équipements · météo · coupures en cours.</p>
        </div>
        <div class="ml-auto">
            <a href="{{ route('equipements.index') }}"
               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors">
                <span class="material-symbols-outlined text-[16px]">home_health</span>Mes équipements
            </a>
        </div>
    </div>
</x-slot>

{{-- ═══════════════════════════════════════════════════════════════════════════
     BANDEAU VITAL — priorité absolue
════════════════════════════════════════════════════════════════════════════ --}}
@if($alerteVitale)
<div class="rounded-xl border-2 border-error bg-red-50 p-space-md flex items-start gap-3 shadow-[0_0_24px_rgba(220,38,38,0.15)]">
    <span class="material-symbols-outlined text-error text-[32px] shrink-0 mt-0.5 animate-pulse">emergency</span>
    <div class="flex-1">
        <p class="font-title-md text-title-md text-red-900 font-semibold">⚠ Équipement médical vital — protocole d'urgence actif</p>
        <p class="font-body-sm text-body-sm text-red-800 mt-1">
            Votre foyer dispose d'un équipement de criticité vitale. En cas de coupure, n'attendez pas.
        </p>
        <div class="flex flex-wrap gap-2 mt-2">
            <a href="tel:190" class="px-3 py-1.5 rounded-lg bg-red-600 text-white font-label-md text-label-md font-semibold hover:bg-red-700 inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">call</span>190 — SAMU
            </a>
            <a href="tel:198" class="px-3 py-1.5 rounded-lg bg-orange-600 text-white font-label-md text-label-md font-semibold hover:bg-orange-700 inline-flex items-center gap-1">
                <span class="material-symbols-outlined text-[16px]">fire_truck</span>198 — Protection Civile
            </a>
        </div>
    </div>
</div>
@endif

{{-- ═══════════════════════════════════════════════════════════════════════════
     CARTE NIVEAU DE RISQUE ML
════════════════════════════════════════════════════════════════════════════ --}}
@if(isset($risque))
<section class="rounded-2xl bg-surface-container-low shadow-md overflow-hidden">
    {{-- Header niveau --}}
    <div class="p-space-md flex items-center gap-4 border-b border-outline-variant/20">
        <div class="p-3 rounded-xl {{ $risque['badge_classes'] }} shrink-0">
            <span class="material-symbols-outlined text-[32px]">{{ $risque['icone'] }}</span>
        </div>
        <div class="flex-1">
            <p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant">Niveau de risque — analyse IA</p>
            <p class="font-headline-md text-headline-md text-on-surface font-bold">{{ $risque['label'] }}</p>
            @if($risque['confiance'] > 0)
                <p class="font-body-sm text-body-sm text-on-surface-variant">Confiance du modèle : <strong>{{ round($risque['confiance'] * 100) }} %</strong></p>
            @endif
        </div>
        <div class="text-right shrink-0">
            <span class="px-3 py-1.5 rounded-full text-label-md font-label-md {{ $risque['badge_classes'] }} block">
                {{ $risque['label'] }}
            </span>
            <span class="font-body-sm text-body-sm text-on-surface-variant mt-1 flex items-center gap-1 justify-end">
                @if($risque['source'] === 'modele')
                    <span class="material-symbols-outlined text-[14px] text-primary">smart_toy</span> Modèle IA
                @else
                    <span class="material-symbols-outlined text-[14px]">rule</span> Règles
                @endif
            </span>
        </div>
    </div>

    {{-- Barres de probabilité (uniquement si ML disponible) --}}
    @if(!empty($risque['probabilites']))
    <div class="px-space-md pt-space-sm pb-space-sm border-b border-outline-variant/20">
        <p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant mb-2">Distribution des probabilités</p>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">
            @foreach(['faible' => ['Faible','bg-green-500','text-green-800'], 'modere' => ['Modéré','bg-amber-500','text-amber-800'], 'eleve' => ['Élevé','bg-orange-500','text-orange-800'], 'critique' => ['Critique','bg-red-500','text-red-800']] as $key => $info)
            @php $pct = round(($risque['probabilites'][$key] ?? 0) * 100, 1); @endphp
            <div class="flex flex-col gap-1">
                <div class="flex items-center justify-between">
                    <span class="font-label-sm text-label-sm {{ $info[2] }}">{{ $info[0] }}</span>
                    <span class="font-label-md text-label-md text-on-surface font-bold">{{ $pct }}%</span>
                </div>
                <div class="w-full h-2 rounded-full bg-surface-container overflow-hidden">
                    <div class="{{ $info[1] }} h-full rounded-full transition-all" style="width: {{ $pct }}%"></div>
                </div>
            </div>
            @endforeach
        </div>
    </div>
    @endif

    {{-- Facteurs détectés --}}
    @if(!empty($risque['facteurs']))
    <div class="px-space-md py-space-sm">
        <p class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant mb-2">Facteurs déclencheurs détectés</p>
        <div class="flex flex-wrap gap-2">
            @foreach($risque['facteurs'] as $facteur)
            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-surface-container text-on-surface font-body-sm text-body-sm border border-outline-variant/30">
                <span class="material-symbols-outlined text-[14px] text-primary">arrow_circle_right</span>
                {{ $facteur }}
            </span>
            @endforeach
        </div>
    </div>
    @endif
</section>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">

    {{-- ═══════════════════════════════════════════════════════════════════
         COLONNE PRINCIPALE
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="lg:col-span-2 flex flex-col gap-space-md">

        {{-- Analyse par équipement --}}
        @if(!empty($analyseParEq))
        <section class="flex flex-col gap-space-sm">
            <h2 class="font-title-md text-title-md text-on-surface px-1 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">home_health</span>
                Analyse par équipement
                <span class="font-body-sm text-body-sm text-on-surface-variant">({{ count($analyseParEq) }} équipement(s))</span>
            </h2>
            @foreach($analyseParEq as $eq)
            <article class="rounded-xl bg-surface-container-low p-space-md shadow-sm border-l-4
                {{ $eq['niveau'] === 'critique' ? 'border-error' : ($eq['niveau'] === 'eleve' ? 'border-orange-400' : ($eq['niveau'] === 'modere' ? 'border-amber-400' : 'border-green-400')) }}">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg {{ $eq['badge_classes'] }} shrink-0">
                        <span class="material-symbols-outlined text-[20px]">{{ $eq['type_icone'] }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-title-md text-title-md text-on-surface">{{ $eq['nom'] }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-label-sm font-label-sm {{ $eq['badge_classes'] }}">
                                <span class="material-symbols-outlined text-[12px] align-middle">{{ $eq['icone_risque'] }}</span>
                                {{ $eq['label'] }}
                            </span>
                            @if($eq['vital'])
                                <span class="px-2 py-0.5 rounded-full bg-red-100 text-red-700 text-label-sm font-label-sm">Vital ⚕</span>
                            @elseif($eq['medical'])
                                <span class="px-2 py-0.5 rounded-full bg-orange-100 text-orange-700 text-label-sm font-label-sm">Médical</span>
                            @endif
                        </div>
                        <p class="font-body-sm text-body-sm text-on-surface-variant mt-0.5">{{ $eq['type_nom'] }} · criticité {{ $eq['criticite_label'] }}</p>
                        <div class="mt-2 flex items-start gap-2 p-2 rounded-lg bg-surface-container">
                            <span class="material-symbols-outlined text-[16px] text-primary mt-0.5 shrink-0">lightbulb</span>
                            <p class="font-body-sm text-body-sm text-on-surface">{{ $eq['conseil'] }}</p>
                        </div>
                        @if($eq['contact_urgence'])
                        <p class="font-body-sm text-body-sm text-error mt-1.5 flex items-center gap-1">
                            <span class="material-symbols-outlined text-[14px]">emergency</span>
                            Contact d'urgence : <strong>{{ $eq['contact_urgence'] }}</strong>
                        </p>
                        @endif
                    </div>
                </div>
            </article>
            @endforeach
        </section>
        @endif

        {{-- Simulateur de scénarios --}}
        @if(!empty($scenarios))
        <section class="rounded-xl bg-surface-container-low p-space-md shadow-md">
            <h2 class="font-title-md text-title-md text-on-surface mb-space-sm flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[20px]">science</span>
                Simulation — si la situation change
            </h2>
            <p class="font-body-sm text-body-sm text-on-surface-variant mb-space-sm">
                Le modèle recalcule votre risque selon différents scénarios météo/réseau.
            </p>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                @foreach($scenarios as $scenario)
                <div class="rounded-xl p-3 bg-surface-container flex flex-col gap-2 border border-outline-variant/20">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-[20px]
                            {{ $scenario['niveau'] === 'critique' ? 'text-error' : ($scenario['niveau'] === 'eleve' ? 'text-orange-600' : ($scenario['niveau'] === 'modere' ? 'text-amber-600' : 'text-green-600')) }}">
                            {{ $scenario['icone'] }}
                        </span>
                        <p class="font-label-md text-label-md text-on-surface font-semibold">{{ $scenario['titre'] }}</p>
                    </div>
                    <span class="px-2 py-0.5 rounded-full text-label-sm font-label-sm w-fit {{ $scenario['badge'] }}">
                        {{ $scenario['label'] }}
                    </span>
                    <p class="font-body-sm text-body-sm text-on-surface-variant">{{ $scenario['action'] }}</p>
                </div>
                @endforeach
            </div>
        </section>
        @endif

        {{-- Message IA (Groq ou fallback) --}}
        <section class="rounded-xl bg-surface-container-low p-space-md shadow-md">
            <div class="flex items-center gap-2 mb-space-sm">
                <div class="p-1.5 rounded-lg bg-primary/10 text-primary">
                    <span class="material-symbols-outlined text-[20px]">smart_toy</span>
                </div>
                <h2 class="font-title-md text-title-md text-on-surface">Recommandation personnalisée</h2>
                <span class="ml-auto px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant text-label-sm font-label-sm flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-primary animate-pulse inline-block"></span>IA
                </span>
            </div>
            <p class="font-body-md text-body-md text-on-surface leading-relaxed">{{ $messageIa }}</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-3 flex items-center gap-1">
                <span class="material-symbols-outlined text-[14px]">schedule</span>
                Actualisé toutes les 15 min selon météo et coupures en cours.
            </p>
        </section>

        {{-- Conseils de la base --}}
        @if($conseils->isNotEmpty())
        <section class="flex flex-col gap-space-sm">
            <h2 class="font-title-md text-title-md text-on-surface px-1">
                Conseils pour votre profil
                <span class="font-body-sm text-body-sm text-on-surface-variant ml-2">({{ $conseils->count() }})</span>
            </h2>
            @foreach($conseils as $conseil)
            <article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-col gap-2 hover:shadow-md transition-shadow">
                <div class="flex items-start gap-3">
                    <div class="p-1.5 rounded-lg bg-primary/10 text-primary shrink-0 mt-0.5">
                        <span class="material-symbols-outlined text-[18px]">{{ $conseil->icone }}</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <h3 class="font-title-md text-title-md text-on-surface">{{ $conseil->titre }}</h3>
                            <span class="px-2 py-0.5 rounded-full text-label-sm font-label-sm {{ $conseil->categorie->badgeClasses() }}">
                                {{ $conseil->categorie->label() }}
                            </span>
                        </div>
                        <p class="font-body-md text-body-md text-on-surface-variant mt-1">{{ $conseil->contenu }}</p>
                        @if($conseil->equipementTypes->isNotEmpty())
                        <div class="flex flex-wrap gap-1.5 mt-2">
                            @foreach($conseil->equipementTypes as $type)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-container text-on-surface-variant text-label-sm font-label-sm">
                                <span class="material-symbols-outlined text-[12px]">{{ $type->icone }}</span>{{ $type->nom }}
                            </span>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </div>
            </article>
            @endforeach
        </section>
        @else
        <div class="rounded-xl bg-surface-container-low p-space-lg text-center shadow-sm">
            <span class="material-symbols-outlined text-outline text-[40px]">tips_and_updates</span>
            <p class="font-title-md text-title-md text-on-surface mt-2">Aucun conseil spécifique</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                Déclarez des équipements pour recevoir des conseils ciblés.
                <a href="{{ route('equipements.index') }}" class="text-primary hover:underline">Déclarer →</a>
            </p>
        </div>
        @endif
    </div>

    {{-- ═══════════════════════════════════════════════════════════════════
         SIDEBAR
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex flex-col gap-space-md">

        {{-- Analyse ML détaillée --}}
        @if(isset($risque) && ($risque['niveau_modele'] || $risque['raison']))
        <section class="rounded-xl bg-surface-container-low p-space-md shadow-sm border-l-4
            {{ $risque['niveau_final'] === 'critique' ? 'border-error' : ($risque['niveau_final'] === 'eleve' ? 'border-orange-500' : 'border-primary') }}">
            <h3 class="font-title-md text-title-md text-on-surface mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">insights</span>
                Analyse de risque
            </h3>
            <div class="space-y-2">
                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container">
                    <span class="font-body-sm text-body-sm text-on-surface-variant">Niveau final</span>
                    <span class="px-2 py-0.5 rounded-full text-label-sm font-label-sm {{ $risque['badge_classes'] }}">{{ $risque['label'] }}</span>
                </div>
                @if($risque['niveau_modele'])
                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container">
                    <span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px] text-primary">smart_toy</span>Modèle IA
                    </span>
                    <span class="font-body-sm text-body-sm text-on-surface capitalize">{{ $risque['niveau_modele'] }}</span>
                </div>
                @endif
                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container">
                    <span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
                        <span class="material-symbols-outlined text-[14px]">rule</span>Règles
                    </span>
                    <span class="font-body-sm text-body-sm text-on-surface capitalize">{{ $risque['niveau_regles'] }}</span>
                </div>
                @if($risque['confiance'] > 0)
                <div class="flex items-center justify-between p-2 rounded-lg bg-surface-container">
                    <span class="font-body-sm text-body-sm text-on-surface-variant">Confiance</span>
                    <span class="font-body-sm text-body-sm text-primary font-semibold">{{ round($risque['confiance'] * 100) }} %</span>
                </div>
                @endif
                @if($risque['desaccord'])
                <div class="flex items-center gap-1.5 p-2 rounded-lg bg-amber-50 text-amber-800 font-body-sm text-body-sm">
                    <span class="material-symbols-outlined text-[14px]">sync_problem</span>
                    Modèle et règles divergent — le plus sévère est retenu.
                </div>
                @endif
                @if($risque['raison'])
                <p class="font-body-sm text-body-sm text-on-surface-variant p-2 leading-relaxed">{{ $risque['raison'] }}</p>
                @endif
            </div>
        </section>
        @endif

        {{-- Urgences --}}
        <section class="rounded-xl bg-surface-container-low p-space-md shadow-sm">
            <h3 class="font-title-md text-title-md text-on-surface mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-error text-[18px]">emergency_home</span>
                Urgences
            </h3>
            <div class="space-y-2">
                <a href="tel:190" class="flex items-center justify-between p-2.5 rounded-lg bg-red-50 hover:bg-red-100 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-red-700 text-[18px]">medical_services</span>
                        <span class="font-label-md text-label-md text-red-900">SAMU</span>
                    </div>
                    <span class="font-title-md text-title-md text-red-700 font-bold">190</span>
                </a>
                <a href="tel:198" class="flex items-center justify-between p-2.5 rounded-lg bg-orange-50 hover:bg-orange-100 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-orange-700 text-[18px]">fire_truck</span>
                        <span class="font-label-md text-label-md text-orange-900">Protection Civile</span>
                    </div>
                    <span class="font-title-md text-title-md text-orange-700 font-bold">198</span>
                </a>
                <a href="tel:190" class="flex items-center justify-between p-2.5 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-700 text-[18px]">support_agent</span>
                        <span class="font-label-md text-label-md text-blue-900">Assistance Santé</span>
                    </div>
                    <span class="font-title-md text-title-md text-blue-700 font-bold">190</span>
                </a>
            </div>
        </section>

        {{-- Lieu courant --}}
        @if($lieu)
        <section class="rounded-xl bg-surface-container-low p-space-md shadow-sm">
            <h3 class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant mb-2">Lieu analysé</h3>
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-primary text-[18px]">{{ $lieu->type->icone() }}</span>
                <div>
                    <p class="font-title-md text-title-md text-on-surface">{{ $lieu->nom }}</p>
                    @if($lieu->adresse)<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $lieu->adresse }}</p>@endif
                </div>
            </div>
        </section>
        @endif
    </div>
</div>

</x-app-layout>
