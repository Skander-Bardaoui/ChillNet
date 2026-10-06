<x-back-layout :title="'Alerte — '.$alerte->titre">
<div class="flex flex-col gap-space-md max-w-5xl">

{{-- En-tête : niveau, statut, validation + actions --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
<div class="flex items-start gap-3 flex-1">
<div class="p-3 rounded-xl shrink-0" @style(['background: '.$alerte->niveau?->couleurHex().'22', 'color: '.$alerte->niveau?->couleurHex()])><span class="material-symbols-outlined text-[28px]">{{ $alerte->niveau?->icone() }}</span></div>
<div class="flex flex-col gap-2">
<p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module 1 · Alerte canicule</p>
<h1 class="font-headline-md text-headline-md text-on-surface font-bold">{{ $alerte->titre }}</h1>
<div class="flex items-center gap-2 flex-wrap">
<span class="px-2.5 py-0.5 rounded-full {{ $alerte->niveau?->badgeClasses() }} font-label-sm text-label-sm font-semibold">{{ $alerte->niveau?->label() }}</span>
<span class="px-2.5 py-0.5 rounded-full {{ $alerte->statut->badgeClasses() }} font-label-sm text-label-sm font-semibold">{{ $alerte->statut->label() }}</span>
@if ($alerte->validee)
<span class="px-2.5 py-0.5 rounded-full bg-green-100 text-green-800 font-label-sm text-label-sm font-semibold">Validée</span>
@else
<span class="px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-label-sm text-label-sm font-semibold">À valider</span>
@endif
</div>
</div>
</div>
<div class="flex flex-wrap gap-2 shrink-0">
@if (! $alerte->validee)
<form method="POST" action="{{ route('back.alertes.valider', $alerte->id) }}">@csrf @method('PATCH')<button type="submit" class="px-4 py-2.5 rounded-xl bg-green-700 text-white font-label-md text-label-md font-semibold hover:bg-green-800 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">check_circle</span>Valider</button></form>
@endif
<a href="{{ route('back.alertes.edit', $alerte->id) }}" class="px-4 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">edit</span>Modifier</a>
<form method="POST" action="{{ route('back.alertes.destroy', $alerte->id) }}" onsubmit="return confirm('Supprimer cette alerte ?');">@csrf @method('DELETE')<button type="submit" class="px-4 py-2.5 rounded-xl bg-surface-container-high text-error font-label-md text-label-md font-semibold hover:bg-error-container inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">delete</span>Supprimer</button></form>
</div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">

{{-- Créneau --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">schedule</span>Créneau de vigilance</h2>
<dl class="grid grid-cols-2 gap-3 font-body-sm text-body-sm">
<div><dt class="text-on-surface-variant">Début</dt><dd class="text-on-surface font-medium">{{ $alerte->debut?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Fin</dt><dd class="text-on-surface font-medium">{{ $alerte->fin?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
</dl>
</section>

{{-- Mesure météo --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">thermostat</span>Mesure météo</h2>
<dl class="grid grid-cols-2 sm:grid-cols-4 gap-3 font-body-sm text-body-sm">
<div><dt class="text-on-surface-variant">Seuil</dt><dd class="text-on-surface font-medium">{{ rtrim(rtrim(number_format((float) $alerte->seuil_temperature, 1, ',', ''), '0'), ',') }}°C</dd></div>
<div><dt class="text-on-surface-variant">Température</dt><dd class="text-on-surface font-medium">{{ $alerte->temperature_actuelle !== null ? rtrim(rtrim(number_format((float) $alerte->temperature_actuelle, 1, ',', ''), '0'), ',').'°C' : '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Ressentie</dt><dd class="text-on-surface font-medium">{{ $alerte->temperature_ressentie !== null ? rtrim(rtrim(number_format((float) $alerte->temperature_ressentie, 1, ',', ''), '0'), ',').'°C' : '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Humidité</dt><dd class="text-on-surface font-medium">{{ $alerte->humidite !== null ? $alerte->humidite.' %' : '—' }}</dd></div>
</dl>
<p class="mt-2 font-body-sm text-body-sm text-on-surface-variant">Source : {{ $alerte->source_meteo === 'weatherapi' ? 'météo live (WeatherAPI)' : 'saisie manuelle' }}</p>
</section>

{{-- Ciblage géographique --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md lg:col-span-2">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">location_on</span>Ciblage géographique</h2>
<div class="flex flex-col gap-3 font-body-sm text-body-sm">
<div>
<dt class="text-on-surface-variant">Quartiers couverts ({{ $alerte->quartiers->count() }})</dt>
<dd class="mt-1 flex flex-wrap gap-2">
@forelse ($alerte->quartiers as $quartier)
<span class="px-2.5 py-0.5 rounded-full bg-surface-container-high text-on-surface font-label-sm text-label-sm">{{ $quartier->nom }} <span class="text-on-surface-variant">({{ $quartier->ville }})</span></span>
@empty
<span class="text-on-surface-variant">Aucun quartier (ciblage par cercle uniquement)</span>
@endforelse
</dd>
</div>
<div>
<dt class="text-on-surface-variant">Zone géographique</dt>
<dd class="text-on-surface mt-1">
@if ($alerte->hasCoordinates())
Point {{ rtrim(rtrim(number_format((float) $alerte->latitude, 4, ',', ''), '0'), ',') }}, {{ rtrim(rtrim(number_format((float) $alerte->longitude, 4, ',', ''), '0'), ',') }} · rayon {{ rtrim(rtrim(number_format($alerte->rayonMetres() / 1000, 1, ',', ''), '0'), ',') }} km
@else
Aucun cercle posé
@endif
</dd>
</div>
</div>
@if ($alerte->hasCoordinates())
<div id="carte-alerte-show" data-lat="{{ (float) $alerte->latitude }}" data-lng="{{ (float) $alerte->longitude }}" data-rayon="{{ $alerte->rayonMetres() }}" data-couleur="{{ json_encode($alerte->niveau?->couleurHex() ?? '#1b77ba') }}" class="mt-3 w-full h-64 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
@endif
</section>

{{-- Message --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md lg:col-span-2">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">message</span>Message aux habitants</h2>
@if ($alerte->message)
<p class="font-body-md text-body-md text-on-surface whitespace-pre-line">{{ $alerte->message }}</p>
@else
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun message rédigé.</p>
@endif
</section>

{{-- Traçabilité --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md lg:col-span-2">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">history</span>Traçabilité</h2>
<dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-body-sm text-body-sm">
<div><dt class="text-on-surface-variant">Créée par</dt><dd class="text-on-surface font-medium">{{ $alerte->user?->name ?? '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Validée par</dt><dd class="text-on-surface font-medium">{{ $alerte->validateur?->name ?? '—' }}{{ $alerte->validee_le ? ' le '.$alerte->validee_le->format('d/m/Y à H:i') : '' }}</dd></div>
<div><dt class="text-on-surface-variant">Créée le</dt><dd class="text-on-surface font-medium">{{ $alerte->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Modifiée le</dt><dd class="text-on-surface font-medium">{{ $alerte->updated_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
</dl>
</section>
</div>

<a href="{{ route('back.alertes.index') }}" class="self-start px-4 py-2.5 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour à la liste</a>
</div>

@if ($alerte->hasCoordinates())
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const el = document.getElementById('carte-alerte-show');
const ALERTE_SHOW = {
    lat: parseFloat(el.dataset.lat),
    lng: parseFloat(el.dataset.lng),
    rayon: parseFloat(el.dataset.rayon),
    couleur: JSON.parse(el.dataset.couleur),
};
const carteShow = L.map('carte-alerte-show').setView([ALERTE_SHOW.lat, ALERTE_SHOW.lng], 13);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(carteShow);
L.circle([ALERTE_SHOW.lat, ALERTE_SHOW.lng], { radius: ALERTE_SHOW.rayon, color: ALERTE_SHOW.couleur, fillColor: ALERTE_SHOW.couleur, fillOpacity: 0.15, weight: 1.5 }).addTo(carteShow);
L.circleMarker([ALERTE_SHOW.lat, ALERTE_SHOW.lng], { radius: 9, color: ALERTE_SHOW.couleur, fillColor: ALERTE_SHOW.couleur, fillOpacity: 0.8, weight: 2 }).addTo(carteShow);
</script>
@endif
</x-back-layout>
