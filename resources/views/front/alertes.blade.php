<x-app-layout>
<x-slot name="header">
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
<div>
<p class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Module canicule — Alertes de mes lieux</p>
<h1 class="font-headline-lg text-headline-lg text-on-surface">Alertes &amp; vigilance</h1>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
@if ($lieuFiltre)
{{ $lieuFiltre->nom }}@if ($lieuFiltre->adresse) — {{ $lieuFiltre->adresse }}@endif
@else
<a href="{{ route('lieux.index') }}" class="text-primary hover:underline">Ajoutez un lieu pour voir les alertes qui vous concernent →</a>
@endif
@foreach (auth()->user()->profilsVulnerabilite() as $profil)
<span class="ml-1 inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-surface-variant text-on-surface-variant font-label-sm text-label-sm"><span class="material-symbols-outlined text-[14px]">{{ $profil->icone() }}</span>{{ $profil->label() }}</span>
@endforeach
</p>
</div>
<a href="{{ route('conseils') }}" class="shrink-0 inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-surface-variant text-on-surface font-label-md text-label-md hover:bg-surface-bright transition-colors"><span class="material-symbols-outlined text-[18px]">health_and_safety</span>Les réflexes canicule</a>
</div>
</x-slot>

{{-- Filtre par lieu du foyer (Domicile, Travail…) --}}
<form method="GET" action="{{ route('alertes.index') }}" class="flex flex-wrap items-center gap-2">
<label for="lieu_id" class="font-label-md text-label-md text-on-surface-variant">Mes lieux :</label>
@if ($lieux->isNotEmpty())
<select id="lieu_id" name="lieu_id" onchange="this.form.submit()" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
@foreach ($lieux as $l)
<option value="{{ $l->id }}" @selected((int) $filtreId === $l->id)>{{ $l->nom }}</option>
@endforeach
</select>
@else
<a href="{{ route('lieux.index') }}" class="rounded-xl bg-primary-container/15 text-primary px-3 py-2 font-label-md text-label-md hover:bg-primary-container/25">Ajouter un lieu</a>
@endif
@if ($meteo)
<span class="ml-auto inline-flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<span class="material-symbols-outlined text-[18px] text-primary">thermostat</span>
Météo locale :{{ rtrim(rtrim(number_format($meteo->temperature, 1, ',', ''), '0'), ',') }}°C
@if ($meteo->humidite !== null) · {{ $meteo->humidite }}% d'humidité @endif
@if ($meteo->condition) · {{ $meteo->condition }} @endif
</span>
@endif
</form>

@if ($alertePrincipale)
{{-- Bandeau principal : alerte validée la plus grave --}}
@php $n = $alertePrincipale->niveau; @endphp
<section class="relative overflow-hidden rounded-xl bg-surface-container-low p-space-md md:p-space-lg shadow-xl border border-outline-variant/20">
<div class="absolute inset-y-0 left-0 w-2" style="background: linear-gradient(to bottom, {{ $n->couleurHex() }}, {{ $n->couleurHex() }}cc);"></div>
<div class="flex flex-col lg:flex-row items-start lg:items-center justify-between gap-space-md pl-space-sm">
<div class="flex items-start gap-space-md">
<div class="relative flex items-center justify-center w-12 h-12 rounded-xl shrink-0" style="background: {{ $n->couleurHex() }}22; color: {{ $n->couleurHex() }};">
<span class="material-symbols-outlined text-[26px]">{{ $n->icone() }}</span>
<span class="absolute -top-1 -right-1 flex h-3 w-3"><span class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-75" style="background: {{ $n->couleurHex() }};"></span><span class="relative inline-flex rounded-full h-3 w-3" style="background: {{ $n->couleurHex() }};"></span></span>
</div>
<div class="flex flex-col gap-1">
<div class="flex items-center gap-2 flex-wrap">
<span class="px-2 py-0.5 rounded-full {{ $n->badgeClasses() }} font-label-sm text-label-sm tracking-wider uppercase font-semibold">{{ $n->label() }}</span>
<span class="px-2 py-0.5 rounded-full {{ $alertePrincipale->statut->badgeClasses() }} font-label-sm text-label-sm font-semibold">{{ $alertePrincipale->statut->label() }}</span>
</div>
<p class="font-title-md text-title-md text-on-surface">{{ $alertePrincipale->titre }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">
Du {{ $alertePrincipale->debut?->format('d/m/Y à H:i') }} au {{ $alertePrincipale->fin?->format('d/m/Y à H:i') }}
· Seuil {{ rtrim(rtrim(number_format((float) $alertePrincipale->seuil_temperature, 1, ',', ''), '0'), ',') }}°C
</p>
@if ($alertePrincipale->hasCoordinates())
<p class="font-body-sm text-body-sm text-on-surface-variant">Zone : rayon {{ rtrim(rtrim(number_format($alertePrincipale->rayonMetres() / 1000, 1, ',', ''), '0'), ',') }} km @if ($alertePrincipale->quartiers->isNotEmpty())· {{ $alertePrincipale->quartiers->pluck('nom')->implode(', ') }}@endif</p>
@elseif ($alertePrincipale->quartiers->isNotEmpty())
<p class="font-body-sm text-body-sm text-on-surface-variant">Quartiers : {{ $alertePrincipale->quartiers->pluck('nom')->implode(', ') }}</p>
@endif
</div>
</div>
</div>
</section>
@else
{{-- Aucune alerte active --}}
<section class="rounded-xl bg-surface-container-low p-space-md md:p-space-lg shadow-md flex items-start gap-space-md border border-outline-variant/20">
<div class="flex items-center justify-center w-12 h-12 rounded-xl bg-primary-container/15 text-primary shrink-0"><span class="material-symbols-outlined text-[26px]">check_circle</span></div>
<div>
<p class="font-title-md text-title-md text-on-surface">Aucune vigilance canicule active @if ($lieuFiltre) sur « {{ $lieuFiltre->nom }} » @endif</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Restez hydraté et prenez des nouvelles de vos voisins isolés. Les alertes validées par votre gestionnaire apparaîtront ici automatiquement.</p>
</div>
</section>
@endif

@if ($courbe)
@php
    // Bornes de la zone tracée + libellés formatés une seule fois.
    $cxG = $courbe['insetGauche'];
    $cxD = $courbe['largeur'] - $courbe['insetDroit'];
    $basTrace = $courbe['hauteur'] - 32;
    $basAxe = $courbe['hauteur'] - 12;
    $seuilTexte = rtrim(rtrim(number_format($courbe['seuil'], 1, ',', ''), '0'), ',');
@endphp
{{-- Courbe de température des prochaines 48 h : situer l'alerte dans le temps --}}
<section class="relative overflow-hidden rounded-2xl bg-surface-container-low border border-outline-variant/20 shadow-md p-space-md md:p-space-lg flex flex-col gap-space-md">
<div class="pointer-events-none absolute -top-24 -right-20 w-72 h-72 rounded-full bg-primary-container/10 blur-3xl"></div>

<div class="relative flex flex-col sm:flex-row sm:items-start sm:justify-between gap-3">
<div class="flex items-start gap-3">
<div class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary-container/15 text-primary shrink-0"><span class="material-symbols-outlined text-[22px]">show_chart</span></div>
<div>
<span class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Tendance thermique</span>
<h2 class="font-headline-sm text-headline-sm text-on-surface">Courbe de température</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant">Prochaines 48 h · {{ count($courbe['points']) }} points de prévision</p>
</div>
</div>
<div class="flex items-center gap-2 flex-wrap">
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm">
<span class="w-2.5 h-2.5 rounded-full" style="background: linear-gradient(135deg, rgb(var(--md-primary)), rgb(var(--md-primary-container)));"></span>Température
</span>
@if ($courbe['seuilY'] !== null)
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-error-container/60 text-on-error-container font-label-sm text-label-sm">
<span class="w-4 border-t-2 border-dashed border-error"></span>Seuil {{ $seuilTexte }}°C
</span>
@endif
<span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-error-container/60 text-on-error-container font-label-sm text-label-sm">
<span class="w-2.5 h-2.5 rounded-full bg-error"></span>Pic canicule
</span>
</div>
</div>

<div class="relative" x-data="{ actif: null, serie: {{ \Illuminate\Support\Js::from($courbe['serie']) }} }">
<svg viewBox="0 0 {{ $courbe['largeur'] }} {{ $courbe['hauteur'] }}" class="w-full h-auto" role="img"
aria-label="Courbe des températures des prochaines 48 heures, minimum {{ $courbe['min'] }} degrés et maximum {{ $courbe['max'] }} degrés">
<defs>
<linearGradient id="courbeTempStroke" x1="0" y1="0" x2="1" y2="0">
<stop offset="0%" stop-color="rgb(var(--md-primary))" />
<stop offset="100%" stop-color="rgb(var(--md-primary-container))" />
</linearGradient>
<linearGradient id="courbeTempFill" x1="0" y1="0" x2="0" y2="1">
<stop offset="0%" stop-color="rgb(var(--md-primary-container))" stop-opacity="0.32" />
<stop offset="55%" stop-color="rgb(var(--md-primary-container))" stop-opacity="0.12" />
<stop offset="100%" stop-color="rgb(var(--md-primary-container))" stop-opacity="0" />
</linearGradient>
</defs>

{{-- Grille horizontale + échelle de température --}}
<g>
@foreach ($courbe['grille'] as $ligne)
<line x1="{{ $cxG }}" y1="{{ $ligne['y'] }}" x2="{{ $cxD }}" y2="{{ $ligne['y'] }}" stroke="rgb(var(--md-outline-variant))" stroke-opacity="0.45" stroke-width="1" stroke-dasharray="2 6" />
<text x="8" y="{{ $ligne['y'] + 4 }}" class="fill-on-surface-variant" font-size="11" opacity="0.85">{{ $ligne['valeur'] }}°</text>
@endforeach
</g>

{{-- Séparateurs de jour --}}
<g>
@foreach ($courbe['jours'] as $jour)
<line x1="{{ $jour['x'] }}" y1="22" x2="{{ $jour['x'] }}" y2="{{ $basTrace }}" stroke="rgb(var(--md-outline-variant))" stroke-opacity="0.35" stroke-width="1" stroke-dasharray="3 5" />
<text x="{{ $jour['x'] + 4 }}" y="15" class="fill-on-surface-variant" font-size="11" font-weight="600" opacity="0.9">{{ $jour['label'] }}</text>
@endforeach
</g>

{{-- Aire + courbe lissées --}}
<path d="{{ $courbe['aire'] }}" fill="url(#courbeTempFill)" />
<path d="{{ $courbe['chemin'] }}" fill="none" stroke="url(#courbeTempStroke)" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round" vector-effect="non-scaling-stroke" />

{{-- Seuil canicule --}}
@if ($courbe['seuilY'] !== null)
<line x1="{{ $cxG }}" y1="{{ $courbe['seuilY'] }}" x2="{{ $cxD }}" y2="{{ $courbe['seuilY'] }}" stroke="rgb(var(--md-error))" stroke-width="1.5" stroke-dasharray="7 5" />
<text x="{{ $cxD }}" y="{{ $courbe['seuilY'] - 7 }}" text-anchor="end" class="fill-error" font-size="11" font-weight="600">Seuil {{ $seuilTexte }}°C</text>
@endif

{{-- Repère « maintenant » (premier point = heure en cours) --}}
<line x1="{{ $courbe['maintenant']['x'] }}" y1="22" x2="{{ $courbe['maintenant']['x'] }}" y2="{{ $basTrace }}" stroke="rgb(var(--md-primary))" stroke-opacity="0.45" stroke-width="1.5" />
<circle cx="{{ $courbe['maintenant']['x'] }}" cy="{{ $courbe['maintenant']['y'] }}" r="4.5" fill="rgb(var(--md-primary))" stroke="rgb(var(--md-surface-container-low))" stroke-width="2" />

{{-- Marqueurs de pic canicule --}}
@foreach ($courbe['points'] as $pt)
@if ($pt['pic'])
<circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="5.5" fill="rgb(var(--md-error))" stroke="rgb(var(--md-surface-container-low))" stroke-width="2" />
@endif
@endforeach

{{-- Axe horaire --}}
<g class="fill-on-surface-variant" font-size="11" opacity="0.9">
@foreach ($courbe['labels'] as $label)
<text x="{{ $label['x'] }}" y="{{ $basAxe }}" text-anchor="middle">{{ $label['texte'] }}</text>
@endforeach
</g>

{{-- Zones de survol (une colonne par point) --}}
@foreach ($courbe['points'] as $i => $pt)
<rect x="{{ $pt['x'] - 8 }}" y="22" width="16" height="{{ $basTrace - 22 }}" fill="transparent" class="cursor-crosshair" @mouseenter="actif = {{ $i }}" @mouseleave="actif = null" />
@endforeach

{{-- Curseur vertical + point mis en évidence --}}
<line x-show="actif !== null" x-cloak y1="22" y2="{{ $basTrace }}" x1="0" x2="0"
:x1="actif !== null ? serie[actif].x : 0" :x2="actif !== null ? serie[actif].x : 0"
stroke="rgb(var(--md-primary-container))" stroke-width="1.2" stroke-opacity="0.7" />
<circle x-show="actif !== null" x-cloak r="7" fill="none" :cx="actif !== null ? serie[actif].x : 0" :cy="actif !== null ? serie[actif].y : 0"
stroke="rgb(var(--md-primary-container))" stroke-width="2" stroke-opacity="0.9" />

{{-- Infobulle flottante --}}
<g x-show="actif !== null" x-cloak
:transform="actif !== null ? 'translate(' + serie[actif].x + ',' + (serie[actif].bas ? (serie[actif].y + 18) : (serie[actif].y - 54)) + ')' : ''">
<rect x="-42" y="0" width="84" height="42" rx="11" fill="rgb(var(--md-surface-container-highest))" stroke="rgb(var(--md-outline-variant))" stroke-width="1" />
<text x="0" y="17" text-anchor="middle" class="fill-on-surface-variant" font-size="10" x-text="actif !== null ? serie[actif].j + ' ' + serie[actif].h : ''"></text>
<text x="0" y="34" text-anchor="middle" class="fill-on-surface" font-size="14" font-weight="700" x-text="actif !== null ? String(serie[actif].t).replace('.', ',') + '°C' : ''"></text>
</g>
</svg>
<p class="sr-only">Utilisez la prévision détaillée ci-dessous pour connaître les températures heure par heure.</p>
</div>

<div class="relative flex items-center justify-between gap-3 flex-wrap">
<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-container-high/70 text-on-surface font-label-sm text-label-sm">
<span class="material-symbols-outlined text-[16px] text-primary">south</span>Min {{ number_format($courbe['min'], 0) }}°C
</span>
<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-container-high/70 text-on-surface-variant font-label-sm text-label-sm">
<span class="material-symbols-outlined text-[16px]">schedule</span>Fenêtre {{ count($courbe['points']) }} h
</span>
<span class="inline-flex items-center gap-2 px-3 py-1.5 rounded-lg bg-surface-container-high/70 text-on-surface font-label-sm text-label-sm">
<span class="material-symbols-outlined text-[16px] text-error">north</span>Max {{ number_format($courbe['max'], 0) }}°C
</span>
</div>
</section>
@endif

@if ($messagePersonnalise)
{{-- Message personnalisé par l'IA selon le profil du foyer --}}
<section class="rounded-xl bg-primary-container/10 border border-primary-container/30 p-space-md md:p-space-lg shadow-md">
<div class="flex items-start gap-space-md">
<div class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary-container/20 text-primary shrink-0"><span class="material-symbols-outlined text-[22px]">auto_awesome</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-semibold">Message personnalisé pour votre foyer</p>
<p class="font-body-md text-body-md text-on-surface mt-1">{{ $messagePersonnalise }}</p>
</div>
</div>
</section>
@endif

{{-- Autres alertes actives --}}
@if ($actives->count() > 1)
<section class="flex flex-col gap-space-sm">
<h2 class="font-headline-sm text-headline-sm text-on-surface">Autres alertes actives @if ($lieuFiltre) — {{ $lieuFiltre->nom }} @endif</h2>
<div class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
@foreach ($actives->skip(1) as $alerte)
@php $na = $alerte->niveau; @endphp
<article class="rounded-xl bg-surface-container-low p-space-md shadow-md flex flex-col gap-space-sm border border-outline-variant/20">
<div class="flex items-center justify-between">
<span class="px-2 py-0.5 rounded-full {{ $na->badgeClasses() }} font-label-sm text-label-sm uppercase tracking-wider font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">{{ $na->icone() }}</span>{{ $na->label() }}</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Seuil {{ rtrim(rtrim(number_format((float) $alerte->seuil_temperature, 1, ',', ''), '0'), ',') }}°C</span>
</div>
<h3 class="font-title-md text-title-md text-on-surface">{{ $alerte->titre }}</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">Du {{ $alerte->debut?->format('d/m/Y H:i') }} au {{ $alerte->fin?->format('d/m/Y H:i') }}</p>
@if ($alerte->message)
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ \Illuminate\Support\Str::limit($alerte->message, 160) }}</p>
@endif
</article>
@endforeach
</div>
</section>
@endif

{{-- Alertes à venir --}}
@if ($aVenir->isNotEmpty())
<section class="flex flex-col gap-space-sm">
<h2 class="font-headline-sm text-headline-sm text-on-surface">Alertes programmées</h2>
<div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
@foreach ($aVenir as $alerte)
@php $np = $alerte->niveau; @endphp
<article class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex items-start gap-space-sm border border-outline-variant/20">
<span class="material-symbols-outlined text-[22px]" style="color: {{ $np->couleurHex() }};">event_upcoming</span>
<div>
<p class="font-title-md text-title-md text-on-surface">{{ $alerte->titre }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Début le {{ $alerte->debut?->format('d/m/Y à H:i') }} · {{ $np->label() }}</p>
</div>
</article>
@endforeach
</div>
</section>
@endif

{{-- Rappel numéros (conservé) --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-wrap items-center gap-space-md">
<span class="material-symbols-outlined text-primary text-[22px]">info</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">En cas de malaise : appelez le <a href="tel:190" class="text-primary font-semibold hover:underline">190</a> ou le <a href="tel:198" class="text-primary font-semibold hover:underline">198</a>. Plateforme canicule : <a href="tel:0800066666" class="text-primary font-semibold hover:underline">0800 06 66 66</a>.</p>
</section>
</x-app-layout>
