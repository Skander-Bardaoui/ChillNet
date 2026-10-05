<x-back-layout :title="'Coupure — '.($coupure->quartier?->nom ?? $coupure->lieu ?? 'Point sur la carte')">
@php
    $statutVal = $coupure->statut?->value ?? $coupure->statut;
    $statutClasses = match ($statutVal) {
        'en_cours' => 'bg-red-100 text-red-800',
        'prevue' => 'bg-orange-100 text-orange-800',
        default => 'bg-green-100 text-green-800',
    };
@endphp
<div class="flex flex-col gap-space-md max-w-5xl">

{{-- En-tête : zone, type, statut + actions --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
<div class="flex items-start gap-3 flex-1">
<div class="p-3 rounded-xl bg-red-100 text-red-800 shrink-0"><span class="material-symbols-outlined text-[28px]">power_off</span></div>
<div class="flex flex-col gap-2">
<p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module 2 · Coupure de courant</p>
<h1 class="font-headline-md text-headline-md text-on-surface font-bold">{{ $coupure->quartier?->nom ?? $coupure->lieu ?? 'Point sur la carte' }}</h1>
<div class="flex items-center gap-2 flex-wrap">
<span class="px-2.5 py-0.5 rounded-full bg-surface-container-high text-on-surface font-label-sm text-label-sm font-semibold">{{ $coupure->type?->label() ?? $coupure->type }}</span>
<span class="px-2.5 py-0.5 rounded-full {{ $statutClasses }} font-label-sm text-label-sm font-semibold">{{ $coupure->statut?->label() ?? $coupure->statut }}</span>
<span class="px-2.5 py-0.5 rounded-full bg-surface-container-high text-on-surface-variant font-label-sm text-label-sm inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">groups</span>{{ $coupure->confirmations }}</span>
</div>
</div>
</div>
<div class="flex flex-wrap gap-2 shrink-0">
<a href="{{ route('back.coupures.edit', $coupure->id) }}" class="px-4 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">edit</span>Modifier</a>
<form method="POST" action="{{ route('back.coupures.destroy', $coupure->id) }}" onsubmit="return confirm('Supprimer cette coupure ?');">@csrf @method('DELETE')<button type="submit" class="px-4 py-2.5 rounded-xl bg-surface-container-high text-error font-label-md text-label-md font-semibold hover:bg-error-container inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">delete</span>Supprimer</button></form>
</div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-2 gap-space-md">

{{-- Zone touchée --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">location_on</span>Zone touchée</h2>
<dl class="grid grid-cols-2 gap-3 font-body-sm text-body-sm">
<div><dt class="text-on-surface-variant">Quartier</dt><dd class="text-on-surface font-medium">{{ $coupure->quartier?->nom ?? '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Ville</dt><dd class="text-on-surface font-medium">{{ $coupure->quartier?->ville ?? '—' }}</dd></div>
<div class="col-span-2"><dt class="text-on-surface-variant">Rue / lieu précis</dt><dd class="text-on-surface font-medium">{{ $coupure->lieu ?? '—' }}</dd></div>
<div class="col-span-2"><dt class="text-on-surface-variant">Point sur la carte</dt><dd class="text-on-surface font-medium">@if ($coupure->hasCoordinates()){{ rtrim(rtrim(number_format((float) $coupure->latitude, 4, ',', ''), '0'), ',') }}, {{ rtrim(rtrim(number_format((float) $coupure->longitude, 4, ',', ''), '0'), ',') }}@else — @endif</dd></div>
</dl>
</section>

{{-- Nature & horaires --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">schedule</span>Nature &amp; horaires</h2>
<dl class="grid grid-cols-2 gap-3 font-body-sm text-body-sm">
<div><dt class="text-on-surface-variant">Type</dt><dd class="text-on-surface font-medium">{{ $coupure->type?->label() ?? $coupure->type }}</dd></div>
<div><dt class="text-on-surface-variant">Statut</dt><dd class="text-on-surface font-medium">{{ $coupure->statut?->label() ?? $coupure->statut }}</dd></div>
<div><dt class="text-on-surface-variant">Début</dt><dd class="text-on-surface font-medium">{{ $coupure->debut?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Fin</dt><dd class="text-on-surface font-medium">{{ $coupure->fin?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
<div class="col-span-2"><dt class="text-on-surface-variant">Confirmations habitants</dt><dd class="text-on-surface font-medium">{{ $coupure->confirmations }}</dd></div>
</dl>
</section>

{{-- Point sur la carte --}}
@if ($coupure->hasCoordinates())
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md lg:col-span-2">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">map</span>Localisation</h2>
<div id="carte-coupure-show" class="w-full h-64 rounded-xl overflow-hidden z-0 border border-outline-variant/30"></div>
</section>
@endif

{{-- Message --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md lg:col-span-2">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">message</span>Message aux habitants</h2>
@if ($coupure->description)
<p class="font-body-md text-body-md text-on-surface whitespace-pre-line">{{ $coupure->description }}</p>
@else
<p class="font-body-sm text-body-sm text-on-surface-variant">Aucun message rédigé.</p>
@endif
</section>

{{-- Traçabilité --}}
<section class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md lg:col-span-2">
<h2 class="font-title-md text-title-md text-on-surface font-semibold flex items-center gap-2 mb-3"><span class="material-symbols-outlined text-primary text-[20px]">history</span>Traçabilité</h2>
<dl class="grid grid-cols-1 sm:grid-cols-2 gap-3 font-body-sm text-body-sm">
<div><dt class="text-on-surface-variant">Signalée / publiée par</dt><dd class="text-on-surface font-medium">{{ $coupure->user?->name ?? '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Créée le</dt><dd class="text-on-surface font-medium">{{ $coupure->created_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
<div><dt class="text-on-surface-variant">Modifiée le</dt><dd class="text-on-surface font-medium">{{ $coupure->updated_at?->format('d/m/Y à H:i') ?? '—' }}</dd></div>
</dl>
</section>
</div>

<a href="{{ route('back.coupures.index') }}" class="self-start px-4 py-2.5 rounded-xl font-label-md text-label-md text-on-surface-variant hover:bg-surface-container-high inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">arrow_back</span>Retour à la liste</a>
</div>

@if ($coupure->hasCoordinates())
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const COUPURE_SHOW = { lat: {{ (float) $coupure->latitude }}, lng: {{ (float) $coupure->longitude }} };
const carteCoupureShow = L.map('carte-coupure-show').setView([COUPURE_SHOW.lat, COUPURE_SHOW.lng], 14);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(carteCoupureShow);
L.circleMarker([COUPURE_SHOW.lat, COUPURE_SHOW.lng], { radius: 10, color: '#dc2626', fillColor: '#dc2626', fillOpacity: 0.7, weight: 2 }).addTo(carteCoupureShow);
</script>
@endif
</x-back-layout>
