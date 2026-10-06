<x-app-layout>
<x-slot name="header">
    <div class="flex items-center gap-space-sm flex-wrap">
        <div class="p-2 rounded-lg bg-primary/10 text-primary">
            <span class="material-symbols-outlined text-[22px]">tips_and_updates</span>
        </div>
        <div>
            <h1 class="font-headline-sm text-headline-sm text-on-surface">Mes conseils personnalisés</h1>
            <p class="font-body-sm text-body-sm text-on-surface-variant">Recommandations selon votre météo, vos équipements et les coupures en cours.</p>
        </div>
        <div class="ml-auto">
            <a href="{{ route('equipements.index') }}"
               class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors">
                <span class="material-symbols-outlined text-[16px]">home_health</span>Mes équipements
            </a>
        </div>
    </div>
</x-slot>

{{-- Alerte vitale — bandeau prioritaire --}}
@if($alerteVitale)
<div class="rounded-xl border-2 border-error bg-red-50 p-space-md flex items-start gap-3 shadow-[0_0_24px_rgba(220,38,38,0.15)]">
    <span class="material-symbols-outlined text-error text-[28px] shrink-0 mt-0.5">emergency</span>
    <div class="flex-1">
        <p class="font-title-md text-title-md text-red-900 font-semibold">Équipement médical vital détecté</p>
        <p class="font-body-sm text-body-sm text-red-800 mt-1">
            Vous avez un équipement de criticité vitale. En cas de coupure, contactez immédiatement votre relais santé.
            En urgence : <a href="tel:190" class="font-semibold underline">190 (SAMU)</a> · <a href="tel:198" class="font-semibold underline">198 (Protection Civile)</a>
        </p>
    </div>
    <a href="tel:190" class="shrink-0 px-3 py-1.5 rounded-lg bg-red-600 text-white font-label-md text-label-md font-semibold hover:bg-red-700 inline-flex items-center gap-1">
        <span class="material-symbols-outlined text-[16px]">call</span>190
    </a>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">

    {{-- Colonne principale --}}
    <div class="lg:col-span-2 flex flex-col gap-space-md">

        {{-- Message IA --}}
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
                <span class="font-body-sm text-body-sm text-on-surface-variant ml-2">({{ $conseils->count() }} conseil(s))</span>
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
                                    <span class="material-symbols-outlined text-[12px]">{{ $type->icone }}</span>
                                    {{ $type->nom }}
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
        <section class="rounded-xl bg-surface-container-low p-space-lg text-center shadow-sm">
            <span class="material-symbols-outlined text-outline text-[40px]">tips_and_updates</span>
            <p class="font-title-md text-title-md text-on-surface mt-2">Aucun conseil spécifique</p>
            <p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
                Déclarez des équipements pour recevoir des conseils ciblés.
                <a href="{{ route('equipements.index') }}" class="text-primary hover:underline">Déclarer →</a>
            </p>
        </section>
        @endif
    </div>

    {{-- Sidebar — récap équipements + urgences --}}
    <div class="flex flex-col gap-space-md">

        {{-- Mes équipements résumé --}}
        <section class="rounded-xl bg-surface-container-low p-space-md shadow-sm">
            <div class="flex items-center justify-between mb-3">
                <h3 class="font-title-md text-title-md text-on-surface flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary text-[18px]">home_health</span>
                    Mes équipements
                </h3>
                <a href="{{ route('equipements.index') }}" class="text-primary text-label-md font-label-md hover:underline">Gérer</a>
            </div>

            @if($equipements->isEmpty())
                <p class="font-body-sm text-body-sm text-on-surface-variant">Aucun équipement déclaré.</p>
                <a href="{{ route('equipements.index') }}"
                   class="mt-2 inline-flex items-center gap-1 text-primary font-label-md text-label-md hover:underline">
                    <span class="material-symbols-outlined text-[14px]">add</span>Déclarer
                </a>
            @else
                <div class="space-y-2">
                    @foreach($equipements as $eq)
                    <div class="flex items-center gap-2 p-2 rounded-lg bg-surface-container
                                {{ $eq->criticite->value === 'vitale' ? 'border border-error/20' : '' }}">
                        <span class="material-symbols-outlined text-[16px] {{ $eq->criticite->value === 'vitale' ? 'text-error' : 'text-primary' }}">
                            {{ $eq->type?->icone ?? 'devices' }}
                        </span>
                        <div class="flex-1 min-w-0">
                            <p class="font-label-md text-label-md text-on-surface truncate">{{ $eq->nom }}</p>
                            <p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ $eq->type?->nom }}</p>
                        </div>
                        <span class="px-1.5 py-0.5 rounded text-label-sm font-label-sm {{ $eq->criticite->badgeClasses() }} shrink-0">
                            {{ $eq->criticite->label() }}
                        </span>
                    </div>
                    @endforeach
                </div>
            @endif
        </section>

        {{-- Numéros d'urgence --}}
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
                <a href="tel:0800066666" class="flex items-center justify-between p-2.5 rounded-lg bg-blue-50 hover:bg-blue-100 transition-colors">
                    <div class="flex items-center gap-2">
                        <span class="material-symbols-outlined text-blue-700 text-[18px]">support_agent</span>
                        <span class="font-label-md text-label-md text-blue-900">Canicule Info</span>
                    </div>
                    <span class="font-body-sm text-body-sm text-blue-700 font-semibold">0800 06 66 66</span>
                </a>
            </div>
        </section>

        {{-- Lien lieu courant --}}
        @if($lieu)
        <section class="rounded-xl bg-surface-container-low p-space-md shadow-sm">
            <h3 class="font-label-sm text-label-sm uppercase tracking-wider text-on-surface-variant mb-2">Lieu courant</h3>
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
