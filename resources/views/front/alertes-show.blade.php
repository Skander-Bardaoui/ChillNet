<x-app-layout>
<x-slot name="header">
<div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
<div>
<p class="font-label-sm text-label-sm text-primary uppercase tracking-wider font-semibold">Module canicule — Détail de l'alerte</p>
<h1 class="font-headline-lg text-headline-lg text-on-surface">{{ $alerte->titre }}</h1>
<div class="flex items-center gap-2 flex-wrap mt-1">
<span class="px-2 py-0.5 rounded-full {{ $alerte->niveau?->badgeClasses() }} font-label-sm text-label-sm uppercase tracking-wider font-semibold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">{{ $alerte->niveau?->icone() }}</span>{{ $alerte->niveau?->label() }}</span>
<span class="px-2 py-0.5 rounded-full {{ $alerte->statut->badgeClasses() }} font-label-sm text-label-sm font-semibold">{{ $alerte->statut->label() }}</span>
</div>
</div>
<div class="shrink-0 flex flex-wrap items-center gap-2">
<a href="{{ route('alertes.index', ['vue' => 'general']) }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-surface-variant text-on-surface font-label-md text-label-md hover:bg-surface-bright transition-colors"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Alertes générales</a>
<a href="{{ route('alertes.index') }}" class="inline-flex items-center gap-2 px-4 py-2.5 rounded-lg bg-primary-container/15 text-primary font-label-md text-label-md hover:bg-primary-container/25 transition-colors"><span class="material-symbols-outlined text-[18px]">home</span>Mes lieux</a>
</div>
</div>
</x-slot>

@php
    $niveau = $alerte->niveau;
    $couleur = $niveau?->couleurHex() ?? '#1b77ba';
@endphp

{{-- Bandeau principal : niveau, statut, créneau et seuil --}}
<section class="relative overflow-hidden rounded-xl bg-surface-container-low p-space-md md:p-space-lg shadow-xl border border-outline-variant/20">
<div class="absolute inset-y-0 left-0 w-2" @style(['background: linear-gradient(to bottom, '.$couleur.', '.$couleur.'cc)'])></div>
<div class="flex items-start gap-space-md pl-space-sm">
<div class="flex items-center justify-center w-12 h-12 rounded-xl shrink-0" @style(['background: '.$couleur.'22', 'color: '.$couleur])>
<span class="material-symbols-outlined text-[26px]">{{ $niveau?->icone() ?? 'warning' }}</span>
</div>
<div class="flex flex-col gap-1">
<p class="font-title-md text-title-md text-on-surface">Du {{ $alerte->debut?->format('d/m/Y à H:i') }} au {{ $alerte->fin?->format('d/m/Y à H:i') }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Seuil de vigilance {{ rtrim(rtrim(number_format((float) $alerte->seuil_temperature, 1, ',', ''), '0'), ',') }}°C</p>
</div>
</div>
</section>

{{-- Message personnalisé par l'IA selon le profil du foyer --}}
@if ($messagePersonnalise)
<section class="rounded-xl bg-primary-container/10 border border-primary-container/30 p-space-md md:p-space-lg shadow-md">
<div class="flex items-start gap-space-md">
<div class="flex items-center justify-center w-10 h-10 rounded-xl bg-primary-container/20 text-primary shrink-0"><span class="material-symbols-outlined text-[22px]">auto_awesome</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-wider text-primary font-semibold">Message pour votre foyer</p>
<p class="font-body-md text-body-md text-on-surface mt-1">{{ $messagePersonnalise }}</p>
</div>
</div>
</section>
@endif

{{-- Message rédigé par les autorités --}}
<section class="rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<h2 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">campaign</span>Message des autorités</h2>
@if ($alerte->message)
<p class="font-body-md text-body-md text-on-surface whitespace-pre-line">{{ $alerte->message }}</p>
@else
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun message rédigé pour cette alerte.</p>
@endif
</section>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">
{{-- Mesure enregistrée au moment de la création de l'alerte --}}
<section class="rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<h2 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">thermostat</span>Mesure de l'alerte</h2>
<dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-body-sm text-body-sm">
<div><dt class="text-on-surface-variant">Seuil</dt><dd class="text-on-surface font-medium">{{ rtrim(rtrim(number_format((float) $alerte->seuil_temperature, 1, ',', ''), '0'), ',') }}°C</dd></div>
<div><dt class="text-on-surface-variant">Température</dt><dd class="text-on-surface font-medium">{{ $alerte->temperature_actuelle !== null ? rtrim(rtrim(number_format((float) $alerte->temperature_actuelle, 1, ',', ''), '0'), ',').'°C' : '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Ressentie</dt><dd class="text-on-surface font-medium">{{ $alerte->temperature_ressentie !== null ? rtrim(rtrim(number_format((float) $alerte->temperature_ressentie, 1, ',', ''), '0'), ',').'°C' : '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Humidité</dt><dd class="text-on-surface font-medium">{{ $alerte->humidite !== null ? $alerte->humidite.' %' : '—' }}</dd></div>
</dl>
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Source : {{ $alerte->source_meteo === 'weatherapi' ? 'météo live (WeatherAPI)' : 'saisie manuelle' }}</p>
</section>

{{-- Météo actuelle sur la zone de l'alerte --}}
<section class="rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<h2 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">cloud</span>Météo locale en direct</h2>
@if ($meteo)
<div class="flex items-baseline gap-2">
<span class="font-display-lg text-display-lg text-on-surface tracking-tighter">{{ rtrim(rtrim(number_format($meteo->temperature, 1, ',', ''), '0'), ',') }}</span>
<span class="font-title-md text-title-md text-on-surface-variant font-medium">°C</span>
@if ($meteo->condition)
<span class="ml-auto px-2 py-0.5 rounded-md bg-surface-variant text-on-surface-variant font-label-sm text-label-sm">{{ $meteo->condition }}</span>
@endif
</div>
<dl class="grid grid-cols-2 sm:grid-cols-3 gap-3 font-body-sm text-body-sm mt-3">
@if ($meteo->ressentie !== null)<div><dt class="text-on-surface-variant">Ressenti</dt><dd class="text-on-surface font-medium">{{ rtrim(rtrim(number_format($meteo->ressentie, 1, ',', ''), '0'), ',') }}°C</dd></div>@endif
@if ($meteo->humidite !== null)<div><dt class="text-on-surface-variant">Humidité</dt><dd class="text-on-surface font-medium">{{ $meteo->humidite }} %</dd></div>@endif
@if ($meteo->vent !== null)<div><dt class="text-on-surface-variant">Vent</dt><dd class="text-on-surface font-medium">{{ number_format($meteo->vent, 0) }} km/h</dd></div>@endif
</dl>
@if ($meteo->ville)<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">{{ $meteo->ville }}</p>@endif
@else
<p class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-2"><span class="material-symbols-outlined text-[20px]">cloud_off</span>Météo en direct indisponible pour cette zone.</p>
@endif
</section>
</div>

{{-- Ciblage géographique : quartiers + cercle, avec carte Leaflet --}}
<section class="rounded-xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md md:p-space-lg">
<h2 class="font-headline-sm text-headline-sm text-on-surface flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">location_on</span>Zone concernée</h2>
<div class="flex flex-col gap-2 font-body-sm text-body-sm">
@if ($alerte->quartiers->isNotEmpty())
<p class="text-on-surface-variant">Quartiers : <span class="text-on-surface font-medium">{{ $alerte->quartiers->pluck('nom')->implode(', ') }}</span></p>
@endif
@if ($alerte->hasCoordinates())
<p class="text-on-surface-variant">Cercle de {{ rtrim(rtrim(number_format($alerte->rayonMetres() / 1000, 1, ',', ''), '0'), ',') }} km autour du point {{ number_format((float) $alerte->latitude, 4) }}, {{ number_format((float) $alerte->longitude, 4) }}.</p>
@elseif ($alerte->quartiers->isEmpty())
<p class="text-on-surface-variant">Zone non géolocalisée.</p>
@endif
</div>
@if ($marqueurs !== [])
<div id="carte-alerte-habitant" data-centre="{{ json_encode($centre) }}" data-marqueurs="{{ json_encode($marqueurs) }}" data-couleur="{{ json_encode($couleur) }}" class="mt-3 w-full h-72 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
@endif
</section>

{{-- Rappel numéros --}}
<section class="rounded-xl bg-surface-container-low p-space-md shadow-sm flex flex-wrap items-center gap-space-md">
<span class="material-symbols-outlined text-primary text-[22px]">info</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">En cas de malaise : appelez le <a href="tel:190" class="text-primary font-semibold hover:underline">190</a> ou le <a href="tel:198" class="text-primary font-semibold hover:underline">198</a>. Plateforme canicule : <a href="tel:0800066666" class="text-primary font-semibold hover:underline">0800 06 66 66</a>.</p>
</section>

@if ($marqueurs !== [])
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
(function initCarteAlerteHabitant() {
  const el = document.getElementById('carte-alerte-habitant');
  if (!el || typeof L === 'undefined') return;

  const CENTRE = JSON.parse(el.dataset.centre);
  const MARQUEURS = JSON.parse(el.dataset.marqueurs);
  const COULEUR = JSON.parse(el.dataset.couleur);

  const carte = L.map('carte-alerte-habitant').setView(CENTRE, 12);
  L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(carte);

  // Les popups affichent du HTML : on échappe les libellés.
  function echapper(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
  }

  MARQUEURS.forEach((m) => {
    if (m.rayon) {
      L.circle([m.lat, m.lng], { radius: m.rayon, color: COULEUR, fillColor: COULEUR, fillOpacity: 0.15, weight: 1.5 }).addTo(carte);
    }
    const contenu = '<strong>' + echapper(m.label) + '</strong>'
      + (m.zone ? '<br>' + echapper(m.zone) : '')
      + (m.rayon ? '<br>Rayon ' + (Math.round(m.rayon / 100) / 10) + ' km' : '');
    L.circleMarker([m.lat, m.lng], { radius: 9, color: COULEUR, fillColor: COULEUR, fillOpacity: 0.85, weight: 2 })
      .addTo(carte).bindPopup(contenu);
  });

  if (MARQUEURS.length > 1) {
    carte.fitBounds(L.latLngBounds(MARQUEURS.map((m) => [m.lat, m.lng])), { padding: [30, 30] });
  }
})();
</script>
@endif
</x-app-layout>
