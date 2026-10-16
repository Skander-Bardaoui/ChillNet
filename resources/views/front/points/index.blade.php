<x-public-layout title="Points de fraîcheur">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">home</span>Accueil</a>
<span aria-hidden="true">/</span>
<span class="text-on-surface font-medium">Points de fraîcheur</span>
</nav>

{{-- En-tête --}}
<header class="rounded-2xl bg-surface-container-low p-space-md md:p-space-lg shadow-sm flex flex-col lg:flex-row lg:items-center gap-space-md">
<div class="flex items-start gap-space-sm flex-1">
<div class="p-3 rounded-xl bg-sky-100 text-sky-800 shrink-0"><span class="material-symbols-outlined text-[28px]">ac_unit</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-widest text-sky-800 font-semibold">Module 3 · Se rafraîchir</p>
<h1 class="font-headline-md text-headline-md md:font-headline-lg md:text-headline-lg text-on-surface font-bold">Refuges et points de fraîcheur</h1>
<p class="font-body-md text-body-md text-on-surface-variant mt-1 max-w-2xl">Parcs ombragés, salles climatisées et fontaines autour de vous, classés par notre IA selon la distance, l'affluence estimée et vos besoins.</p>
</div>
</div>
<div class="flex flex-wrap gap-2 shrink-0">
@auth
@if (auth()->user()->isHabitant())
<a href="{{ route('points.create') }}" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">add_location_alt</span>Proposer un point</a>
<a href="{{ route('points.mine') }}" class="px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">list_alt</span>Mes propositions</a>
@endif
@else
<a href="{{ route('login') }}" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">login</span>Se connecter pour proposer</a>
@endauth
</div>
</header>

{{-- Préférences → moteur de recommandation --}}
<form method="GET" action="{{ route('refuges.index') }}" id="form-reco" class="rounded-2xl bg-surface-container-low p-space-md shadow-sm flex flex-col gap-3">
<input type="hidden" name="lat" id="reco-lat" value="{{ request('lat') }}" />
<input type="hidden" name="lng" id="reco-lng" value="{{ request('lng') }}" />
<div class="flex flex-wrap items-center gap-2">
<span class="font-label-md text-label-md text-on-surface font-semibold mr-1">Mes besoins :</span>
@foreach (['pmr' => ['Accessible PMR', 'accessible'], 'climatise' => ['Climatisé', 'ac_unit'], 'ombrage' => ['Avec de l\'ombre', 'park'], 'eau' => ['Eau potable', 'water_drop']] as $cle => [$libelle, $icone])
<label class="cursor-pointer">
<input type="checkbox" name="{{ $cle }}" value="1" @checked($preferences[$cle]) class="peer sr-only" />
<span class="inline-flex items-center gap-1 px-3 py-1.5 rounded-full border border-outline-variant/40 font-label-md text-label-md text-on-surface-variant peer-checked:bg-primary-container peer-checked:text-on-primary-container peer-checked:border-primary-container peer-focus-visible:ring-2 peer-focus-visible:ring-primary"><span class="material-symbols-outlined text-[16px]">{{ $icone }}</span>{{ $libelle }}</span>
</label>
@endforeach
</div>
<div class="flex flex-wrap items-end gap-3">
<div class="flex flex-col gap-1">
<label for="reco-type" class="font-label-sm text-label-sm text-on-surface-variant">Type</label>
<select id="reco-type" name="type" class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface">
<option value="">Tous les types</option>
@foreach (\App\Enums\TypePointFraicheur::cases() as $type)
<option value="{{ $type->value }}" @selected(($filtres['type'] ?? '') === $type->value)>{{ $type->label() }}</option>
@endforeach
</select>
</div>
<div class="flex flex-col gap-1">
<label for="reco-rayon" class="font-label-sm text-label-sm text-on-surface-variant">Rayon</label>
<select id="reco-rayon" name="rayon" class="rounded-xl bg-surface-container border border-outline-variant/40 px-3 py-2 text-on-surface">
@foreach ([1, 2, 5, 10, 50] as $r)
<option value="{{ $r }}" @selected((int) $rayon === $r)>{{ $r }} km</option>
@endforeach
</select>
</div>
<button type="button" id="btn-ma-position" class="px-4 py-2 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">my_location</span>Utiliser ma position</button>
<button type="submit" class="px-4 py-2 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">auto_awesome</span>Recommander</button>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
<span class="material-symbols-outlined text-[16px]">pin_drop</span>
@if ($sourcePosition === 'gps') Position : votre localisation actuelle.
@elseif (str_starts_with($sourcePosition, 'lieu:')) Position : votre lieu « {{ substr($sourcePosition, 5) }} ».
@else Position : centre de Tunis (activez « Utiliser ma position » pour des résultats autour de vous).
@endif
@if ($meteo) · <span class="material-symbols-outlined text-[16px]">thermostat</span>{{ number_format($meteo->temperature, 0) }} °C (ressenti {{ number_format($meteo->ressentie ?? $meteo->temperature, 0) }} °C)@endif
@if ($pression >= 2) · <strong class="text-red-700">forte chaleur : priorité aux lieux climatisés</strong>@endif
</p>
</form>

{{-- Recommandations IA --}}
<section class="flex flex-col gap-space-sm" aria-labelledby="titre-reco">
<h2 id="titre-reco" class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2"><span class="material-symbols-outlined text-primary">auto_awesome</span>Recommandés pour vous</h2>
@if ($conseil)
<div class="rounded-2xl bg-primary-container/10 border border-primary-container/30 p-space-md flex gap-3">
<span class="material-symbols-outlined text-primary shrink-0">tips_and_updates</span>
<p class="font-body-md text-body-md text-on-surface">{{ $conseil }}</p>
</div>
@endif
@forelse ($recommandations as $rang => $reco)
@php $p = $reco['point']; @endphp
<article class="rounded-2xl bg-surface-container-low shadow-md border {{ $rang === 0 ? 'border-primary-container' : 'border-outline-variant/20' }} p-space-md flex flex-col md:flex-row gap-space-md">
<div class="flex md:flex-col items-center md:items-start gap-3 md:w-40 shrink-0">
<span class="flex h-10 w-10 items-center justify-center rounded-full {{ $rang === 0 ? 'bg-primary-container text-on-primary-container' : 'bg-surface-container-high text-on-surface' }} font-title-md text-title-md font-bold">#{{ $rang + 1 }}</span>
<div class="flex-1 md:w-full">
<p class="font-label-sm text-label-sm text-on-surface-variant">Score IA</p>
<p class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $reco['score'] }}<span class="font-body-sm text-body-sm text-on-surface-variant">/100</span></p>
<div class="h-2 w-full rounded-full bg-surface-container-high overflow-hidden"><div class="h-full rounded-full bg-primary-container" style="width: {{ $reco['score'] }}%"></div></div>
</div>
</div>
<div class="flex-1 min-w-0 flex flex-col gap-2">
<div class="flex flex-wrap items-center gap-2">
<a href="{{ route('points.show', $p) }}" class="font-title-lg text-title-lg text-on-surface font-semibold hover:underline">{{ $p->nom }}</a>
<span class="px-2 py-0.5 rounded-full font-label-sm text-label-sm font-semibold {{ $reco['ouvert'] ? 'bg-green-100 text-green-800' : 'bg-slate-200 text-slate-700' }}">{{ $reco['ouvert'] ? 'Ouvert' : 'Fermé' }}</span>
</div>
<x-point-equipements :point="$p" />
<ul class="flex flex-col gap-1 font-body-sm text-body-sm text-on-surface-variant">
@foreach ($reco['raisons'] as $raison)
<li class="flex items-start gap-1"><span class="material-symbols-outlined text-[16px] text-primary shrink-0">check_small</span>{{ $raison }}</li>
@endforeach
</ul>
</div>
<div class="md:w-56 shrink-0 grid grid-cols-2 gap-2 content-start" aria-label="Détail du score">
@foreach (['proximite' => ['Proximité', \App\Services\RecommandationFraicheurService::POIDS_PROXIMITE], 'preferences' => ['Besoins', \App\Services\RecommandationFraicheurService::POIDS_PREFERENCES], 'affluence' => ['Calme', \App\Services\RecommandationFraicheurService::POIDS_AFFLUENCE], 'qualite' => ['Avis', \App\Services\RecommandationFraicheurService::POIDS_QUALITE]] as $cle => [$libelle, $max])
<div class="rounded-lg bg-surface-container p-2">
<p class="font-label-sm text-label-sm text-on-surface-variant">{{ $libelle }}</p>
<p class="font-title-sm text-title-sm text-on-surface font-semibold">{{ $reco['criteres'][$cle] }}/{{ $max }}</p>
</div>
@endforeach
</div>
</article>
@empty
<div class="rounded-2xl bg-surface-container-low p-space-md text-on-surface-variant">Aucun point ne correspond à ces critères dans un rayon de {{ $rayon }} km. Élargissez le rayon ou retirez un besoin.</div>
@endforelse
</section>

{{-- Carte --}}
<div class="rounded-2xl overflow-hidden bg-surface-container-low shadow-md border border-outline-variant/20">
<div class="flex items-center gap-2 px-space-md py-2.5 border-b border-outline-variant/20">
<span class="font-label-md text-label-md text-on-surface font-semibold">Carte · {{ $proches->count() }} point(s) dans un rayon de {{ $rayon }} km</span>
<span class="ml-auto hidden md:inline font-body-sm text-body-sm text-on-surface-variant">🟢 parc · 🔵 salle climatisée · 🩵 fontaine</span>
</div>
<div id="carte-points" class="w-full h-80 md:h-96 z-0"></div>
</div>

{{-- Liste par distance --}}
<section class="flex flex-col gap-space-sm" aria-labelledby="titre-proches">
<h2 id="titre-proches" class="font-headline-sm text-headline-sm text-on-surface">Les plus proches</h2>
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-space-md">
@foreach ($proches as $reco)
@php $p = $reco['point']; @endphp
<article class="flex flex-col rounded-2xl bg-surface-container-low shadow-md overflow-hidden">
@if ($p->photo_url)
<img src="{{ $p->photo_url }}" alt="" class="w-full h-36 object-cover" loading="lazy" />
@else
<div class="w-full h-36 flex items-center justify-center" style="background: {{ $p->type->couleurHex() }}1a"><span class="material-symbols-outlined text-[48px]" style="color: {{ $p->type->couleurHex() }}">{{ $p->type->icone() }}</span></div>
@endif
<div class="p-space-md flex flex-col gap-2 flex-1">
<div class="flex items-start justify-between gap-2">
<h3 class="font-title-md text-title-md text-on-surface font-semibold">{{ $p->nom }}</h3>
<span class="shrink-0 font-label-md text-label-md text-primary font-semibold">{{ $reco['distance_m'] < 1000 ? $reco['distance_m'].' m' : number_format($reco['distance_m'] / 1000, 1, ',', '').' km' }}</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">schedule</span>{{ $p->horaires() }} · {{ $reco['ouvert'] ? 'ouvert' : 'fermé' }} · {{ mb_strtolower($reco['affluence']['libelle']) }}</p>
<div class="flex items-center gap-2"><x-etoiles :note="$p->noteMoyenne()" :taille="14" /><span class="font-body-sm text-body-sm text-on-surface-variant">{{ $p->avis_count ? number_format($p->noteMoyenne(), 1, ',', '').' · '.$p->avis_count.' avis' : 'Pas encore d\'avis' }}</span></div>
<x-point-equipements :point="$p" />
<a href="{{ route('points.show', $p) }}" class="mt-auto pt-2 inline-flex items-center gap-1 font-label-md text-label-md text-primary hover:underline">Détails et avis<span class="material-symbols-outlined text-[16px]">arrow_forward</span></a>
</div>
</article>
@endforeach
</div>
</section>

@include('points-fraicheur._carte-script', ['idCarte' => 'carte-points', 'marqueurs' => $marqueurs, 'origine' => $origine, 'rayonKm' => $rayon])
<script>
(function () {
    const btn = document.getElementById('btn-ma-position');
    if (! btn || ! navigator.geolocation) { if (btn) btn.hidden = true; return; }
    btn.addEventListener('click', () => {
        btn.disabled = true;
        navigator.geolocation.getCurrentPosition((p) => {
            document.getElementById('reco-lat').value = p.coords.latitude.toFixed(6);
            document.getElementById('reco-lng').value = p.coords.longitude.toFixed(6);
            document.getElementById('form-reco').submit();
        }, () => { btn.disabled = false; alert('Localisation refusée ou indisponible.'); });
    });
})();
</script>
</x-public-layout>
