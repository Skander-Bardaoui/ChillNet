<x-back-layout :title="'Tableau de bord'">
@if(auth()->user()->isAdmin())
{{-- ADMIN : vision globale du réseau --}}
<section class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Température</span><span class="material-symbols-outlined text-tertiary-container">thermostat</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">38.2°C</p><p class="font-body-sm text-body-sm text-on-surface-variant">Pic 16h30 · 41°C</p></div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Réseau</span><span class="material-symbols-outlined text-primary">bolt</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">88%</p><p class="font-body-sm text-body-sm text-tertiary-fixed">Éco-vigilance 14h–18h</p></div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Quartiers</span><span class="material-symbols-outlined text-primary">location_city</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ $stats['quartiers'] }}</p><a href="{{ route('back.quartiers.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Gérer →</a></div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Résidences</span><span class="material-symbols-outlined text-primary">home</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ $stats['residences'] }}</p><a href="{{ route('back.residences.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Gérer →</a></div>
</section>
<p class="font-body-sm text-body-sm text-on-surface-variant">Espace <strong class="text-on-surface">administrateur</strong> : référentiel global — quartiers + toutes les résidences (création, modification, suppression).</p>
@else
{{-- GESTIONNAIRE : périmètre = sa résidence uniquement --}}
@php
    $maResidence = auth()->user()->residence;
@endphp
<section class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">
<div class="lg:col-span-2 rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md">
<div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Ma résidence</span><span class="material-symbols-outlined text-primary">apartment</span></div>
@if($maResidence)
<p class="font-headline-sm text-headline-sm text-on-surface mt-1">{{ $maResidence->nom }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $maResidence->adresse ?? 'Adresse non renseignée' }}@if($maResidence->quartier) — Quartier {{ $maResidence->quartier->nom }} ({{ $maResidence->quartier->ville }})@endif</p>
<div class="flex flex-wrap gap-2 mt-3">
<span class="px-2 py-0.5 rounded-md bg-surface-variant text-on-surface font-label-sm text-label-sm">{{ $maResidence->nombre_logements ?? '—' }} logements</span>
@if($maResidence->salle_climatisee)<span class="px-2 py-0.5 rounded-md bg-surface-variant text-primary font-label-sm text-label-sm">Salle climatisée</span>@endif
@if($maResidence->point_fraicheur)<span class="px-2 py-0.5 rounded-md bg-primary-container/15 text-primary font-label-sm text-label-sm">Point de fraîcheur</span>@endif
</div>
<a href="{{ route('back.residences.edit', $maResidence->id) }}" class="inline-flex items-center gap-2 mt-4 px-4 py-2.5 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95"><span class="material-symbols-outlined text-[18px]">edit</span>Modifier ma résidence</a>
@else
<p class="font-title-md text-title-md text-on-surface mt-1">Aucune résidence rattachée</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Contactez un administrateur pour être rattaché à votre résidence.</p>
@endif
</div>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Mon périmètre</span><span class="material-symbols-outlined text-primary">shield</span></div><p class="font-body-md text-body-md text-on-surface mt-2">{{ $stats['residences'] }} résidence · {{ $maResidence?->quartier ? '1' : '0' }} quartier</p><p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Vous gérez uniquement votre résidence (fiche + équipements). Les quartiers et les autres résidences sont administrés par un admin.</p><a href="{{ route('back.residences.index') }}" class="font-body-sm text-body-sm text-primary hover:underline">Voir ma fiche →</a></div>
</section>
@endif

@php
    // Libellés & couleurs des modules pour l'activité récente (le statut
    // « coupure » et l'urgence « signalement » sont des chaînes, pas des enums).
    $coupureClasses = ['en_cours' => 'bg-red-100 text-red-800', 'prevue' => 'bg-orange-100 text-orange-800', 'resolue' => 'bg-green-100 text-green-800'];
    $catLabels = ['fuite' => 'Fuite', 'panne_locale' => 'Panne locale', 'personne_vulnerable' => 'Personne vulnérable', 'autre' => 'Autre'];
    $urgLabels = ['vitale' => 'Vitale', 'prioritaire' => 'Prioritaire', 'normale' => 'Normale'];
    $urgClasses = ['vitale' => 'bg-red-100 text-red-800', 'prioritaire' => 'bg-orange-100 text-orange-800', 'normale' => 'bg-surface-variant text-on-surface-variant'];
    $statutSignalement = ['nouveau' => 'Nouveau', 'en_traitement' => 'En traitement', 'resolu' => 'Résolu'];
@endphp

{{-- Chiffres clés par module (périmètre : global admin / zone gestionnaire) --}}
<section>
<p class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant mb-2">Chiffres clés par module</p>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-space-md">
<a href="{{ route('back.alertes.index') }}" class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md border border-outline-variant/20 hover:border-primary-container/50 transition-colors"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Alertes canicule</span><span class="material-symbols-outlined text-red-600">warning</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ $stats['alertes_actives'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">active(s) · <strong class="text-amber-700">{{ $stats['alertes_a_valider'] }}</strong> à valider</p></a>
<a href="{{ route('back.coupures.index') }}" class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md border border-outline-variant/20 hover:border-primary-container/50 transition-colors"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Coupures</span><span class="material-symbols-outlined text-orange-600">bolt</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ $stats['coupures_en_cours'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">en cours · {{ $stats['coupures_prevues'] }} prévue(s)</p></a>
<a href="{{ route('back.signalements.index') }}" class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md border border-outline-variant/20 hover:border-primary-container/50 transition-colors"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Signalements</span><span class="material-symbols-outlined text-primary">report</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ $stats['signalements_nouveaux'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">à traiter (nouveau)</p></a>
<div class="rounded-xl bg-surface-container/70 backdrop-blur-md p-space-md shadow-md border border-outline-variant/20"><div class="flex items-center justify-between text-on-surface-variant"><span class="font-label-md text-label-md uppercase tracking-wider">Communauté</span><span class="material-symbols-outlined text-primary">groups</span></div><p class="font-display-lg text-display-lg text-on-surface mt-1">{{ $stats['habitants'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">habitant(s) · {{ $stats['lieux'] }} lieu(x) suivi(s)</p></div>
</div>
</section>

{{-- Activité récente : 5 derniers éléments par module, dans le périmètre. --}}
<section>
<p class="font-label-md text-label-md uppercase tracking-wider text-on-surface-variant mb-2">Activité récente</p>
<div class="grid grid-cols-1 lg:grid-cols-3 gap-space-md">

<div class="rounded-xl bg-surface-container/70 backdrop-blur-md shadow-md border border-outline-variant/20 overflow-hidden">
<div class="flex items-center justify-between gap-2 px-space-md py-3 border-b border-outline-variant/20">
<span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-red-600 text-[20px]">warning</span>Dernières alertes</span>
<a href="{{ route('back.alertes.index') }}" class="font-label-sm text-label-sm text-primary hover:underline whitespace-nowrap">Tout voir →</a>
</div>
<div class="divide-y divide-outline-variant/10">
@forelse ($dernieresAlertes as $alerte)
<a href="{{ route('back.alertes.edit', $alerte->id) }}" class="flex items-center gap-3 px-space-md py-3 hover:bg-surface-container/60 transition-colors">
<div class="min-w-0 flex-1">
<p class="font-body-md text-body-md text-on-surface font-medium truncate">{{ $alerte->titre }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $alerte->statut?->label() }} · {{ $alerte->debut?->format('d/m H:i') }}</p>
</div>
@if(! $alerte->validee)<span class="px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 font-label-sm text-label-sm font-semibold whitespace-nowrap">À valider</span>@endif
<span class="px-2.5 py-0.5 rounded-full {{ $alerte->niveau?->badgeClasses() }} font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $alerte->niveau?->label() }}</span>
</a>
@empty
<p class="px-space-md py-6 font-body-sm text-body-sm text-on-surface-variant">Aucune alerte dans votre périmètre.</p>
@endforelse
</div>
</div>

<div class="rounded-xl bg-surface-container/70 backdrop-blur-md shadow-md border border-outline-variant/20 overflow-hidden">
<div class="flex items-center justify-between gap-2 px-space-md py-3 border-b border-outline-variant/20">
<span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-orange-600 text-[20px]">bolt</span>Dernières coupures</span>
<a href="{{ route('back.coupures.index') }}" class="font-label-sm text-label-sm text-primary hover:underline whitespace-nowrap">Tout voir →</a>
</div>
<div class="divide-y divide-outline-variant/10">
@forelse ($dernieresCoupures as $coupure)
<a href="{{ route('back.coupures.edit', $coupure->id) }}" class="flex items-center gap-3 px-space-md py-3 hover:bg-surface-container/60 transition-colors">
<div class="min-w-0 flex-1">
<p class="font-body-md text-body-md text-on-surface font-medium truncate">{{ $coupure->type?->label() }} — {{ $coupure->quartier?->nom ?? $coupure->lieu ?? 'Point sur la carte' }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $coupure->debut?->format('d/m H:i') }} → {{ $coupure->fin?->format('d/m H:i') ?? '—' }}</p>
</div>
<span class="px-2.5 py-0.5 rounded-full {{ $coupureClasses[$coupure->statut?->value] ?? 'bg-surface-variant text-on-surface-variant' }} font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $coupure->statut?->label() }}</span>
</a>
@empty
<p class="px-space-md py-6 font-body-sm text-body-sm text-on-surface-variant">Aucune coupure dans votre périmètre.</p>
@endforelse
</div>
</div>

<div class="rounded-xl bg-surface-container/70 backdrop-blur-md shadow-md border border-outline-variant/20 overflow-hidden">
<div class="flex items-center justify-between gap-2 px-space-md py-3 border-b border-outline-variant/20">
<span class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary text-[20px]">report</span>Derniers signalements</span>
<a href="{{ route('back.signalements.index') }}" class="font-label-sm text-label-sm text-primary hover:underline whitespace-nowrap">Tout voir →</a>
</div>
<div class="divide-y divide-outline-variant/10">
@forelse ($derniersSignalements as $signalement)
<div class="flex items-center gap-3 px-space-md py-3">
<div class="min-w-0 flex-1">
<p class="font-body-md text-body-md text-on-surface font-medium truncate">{{ $catLabels[$signalement->categorie] ?? ucfirst($signalement->categorie) }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant truncate">{{ $signalement->residence?->nom ?? 'Sans résidence' }} · {{ $signalement->date_signalement?->format('d/m/Y') }} · {{ $statutSignalement[$signalement->statut] ?? $signalement->statut }}</p>
</div>
<span class="px-2.5 py-0.5 rounded-full {{ $urgClasses[$signalement->urgence] ?? 'bg-surface-variant text-on-surface-variant' }} font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $urgLabels[$signalement->urgence] ?? $signalement->urgence }}</span>
</div>
@empty
<p class="px-space-md py-6 font-body-sm text-body-sm text-on-surface-variant">Aucun signalement dans votre périmètre.</p>
@endforelse
</div>
</div>

</div>
</section>
</x-back-layout>
