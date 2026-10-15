<x-back-layout :title="'Coupures de courant'">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

{{-- En-tête du module --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
<div class="flex items-start gap-3 flex-1">
<div class="p-3 rounded-xl bg-red-100 text-red-800 shrink-0"><span class="material-symbols-outlined text-[28px]">power_off</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module 2 · @if(auth()->user()->isGestionnaire()) Votre zone uniquement @else Référentiel global @endif</p>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">Publiez les coupures <strong>prévues</strong> (maintenance, délestage programmé), suivez celles <strong>en cours</strong> signalées par les habitants, et clôturez en <strong>résolue</strong>.</p>
</div>
</div>
<a href="{{ route('back.coupures.create') }}" class="shrink-0 px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center justify-center gap-2 shadow"><span class="material-symbols-outlined text-[18px]">add</span>Nouvelle coupure</a>
</div>

{{-- 4 chiffres clés --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
<div class="rounded-xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-red-700 text-[28px]">bolt</span>
<div><p class="font-headline-sm text-headline-sm text-red-900 font-bold">{{ $stats['en_cours'] }}</p><p class="font-body-sm text-body-sm text-red-800">en cours</p></div>
</div>
<div class="rounded-xl bg-orange-50 border border-orange-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-orange-700 text-[28px]">construction</span>
<div><p class="font-headline-sm text-headline-sm text-orange-900 font-bold">{{ $stats['prevues'] }}</p><p class="font-body-sm text-body-sm text-orange-800">prévues</p></div>
</div>
<div class="rounded-xl bg-green-50 border border-green-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-green-700 text-[28px]">check_circle</span>
<div><p class="font-headline-sm text-headline-sm text-green-900 font-bold">{{ $stats['resolues'] }}</p><p class="font-body-sm text-body-sm text-green-800">résolues</p></div>
</div>
<div class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-primary text-[28px]">groups</span>
<div><p class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $stats['confirmations'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">confirmations habitants</p></div>
</div>
</div>

{{-- Carte du périmètre : rouge = en cours, orange = prévue, vert = résolue. --}}
<div class="rounded-2xl overflow-hidden bg-surface-container-low shadow-md border border-outline-variant/20">
<div class="flex items-center gap-2 px-space-md py-2.5 border-b border-outline-variant/20">
<span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-pulse"></span>
<span class="font-label-md text-label-md text-on-surface font-semibold">Carte du périmètre</span>
<span class="ml-auto hidden md:inline font-body-sm text-body-sm text-on-surface-variant">🔴 en cours · 🟠 prévue · 🟢 résolue — cliquez un point</span>
</div>
<div id="carte-back-coupures" class="w-full h-72 md:h-80 z-0"></div>
</div>

{{-- Anomalie IA : afflux de signalements = possible incident majeur non déclaré. --}}
@if ($anomalies->isNotEmpty())
<div class="rounded-2xl bg-red-50 border border-red-300 p-space-md flex items-start gap-3" role="alert">
<span class="material-symbols-outlined text-red-700 text-[28px] shrink-0">crisis_alert</span>
<div>
<p class="font-title-md text-title-md text-red-900 font-semibold">Anomalie détectée par l'IA</p>
<ul class="mt-1 flex flex-col gap-1">
@foreach ($anomalies as $anomalie)
<li class="font-body-sm text-body-sm text-red-800 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">bolt</span>{{ $anomalie['message'] }}</li>
@endforeach
</ul>
</div>
</div>
@endif

{{-- Risque de coupure IA par zone : historique corrélé à la canicule. --}}
@if ($risques->isNotEmpty())
@php $modeRegles = collect($risques)->every(fn ($r) => ($r['source'] ?? 'regles') === 'regles'); @endphp
<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
<div class="flex items-center gap-2 mb-3 flex-wrap">
<span class="material-symbols-outlined text-primary text-[22px]">auto_awesome</span>
<h2 class="font-title-md text-title-md text-on-surface font-semibold">Risque de coupure par zone <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(IA · historique + canicule)</span></h2>
@if ($modeRegles)
<span class="ml-auto px-2.5 py-0.5 rounded-full bg-surface-variant text-on-surface-variant font-label-sm text-label-sm font-semibold inline-flex items-center gap-1" title="Le microservice IA est indisponible : seules les règles déterministes s'appliquent."><span class="material-symbols-outlined text-[15px]">cloud_off</span>mode règles</span>
@endif
</div>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
@foreach ($risques->take(6) as $risque)
@php $nr = $risque['niveau']; $p24 = $risque['p24'] ?? null; $p48 = $risque['p48'] ?? null; @endphp
<div class="rounded-xl bg-surface-container border border-outline-variant/20 p-3 flex flex-col gap-2">
<div class="flex items-center justify-between gap-2">
<span class="font-title-sm text-title-sm text-on-surface font-medium inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]" style="color: {{ $nr->couleurHex() }};">{{ $nr->icone() }}</span>{{ $risque['quartier']->nom }}</span>
<span class="px-2.5 py-0.5 rounded-full {{ $nr->badgeClasses() }} font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $nr->label() }}</span>
</div>
<div class="h-2 w-full rounded-full bg-surface-container-high overflow-hidden">
<div class="h-full rounded-full" style="width: {{ $risque['score'] }}%; background: {{ $nr->couleurHex() }};"></div>
</div>
@if ($p24 !== null || $p48 !== null)
<div class="grid grid-cols-2 gap-2">
<div>
<p class="font-label-sm text-label-sm text-on-surface-variant">Coupure sous 24 h · {{ $p24 !== null ? round($p24 * 100) : '—' }} %</p>
<div class="h-1.5 w-full rounded-full bg-surface-container-high overflow-hidden"><div class="h-full rounded-full" style="width: {{ $p24 !== null ? round($p24 * 100) : 0 }}%; background: {{ $nr->couleurHex() }};"></div></div>
</div>
<div>
<p class="font-label-sm text-label-sm text-on-surface-variant">Sous 48 h · {{ $p48 !== null ? round($p48 * 100) : '—' }} %</p>
<div class="h-1.5 w-full rounded-full bg-surface-container-high overflow-hidden"><div class="h-full rounded-full" style="width: {{ $p48 !== null ? round($p48 * 100) : 0 }}%; background: {{ $nr->couleurHex() }};"></div></div>
</div>
</div>
@endif
@if (! empty($risque['desaccord']))
<span class="self-start px-2 py-0.5 rounded-md bg-amber-100 text-amber-800 font-label-sm text-label-sm font-semibold inline-flex items-center gap-1" title="Le modèle et les règles ne proposent pas le même niveau ; le plus grave est conservé."><span class="material-symbols-outlined text-[14px]">compare_arrows</span>IA et règles en désaccord</span>
@endif
<p class="font-body-sm text-body-sm text-on-surface-variant">Score {{ $risque['score'] }}/100 · {{ $risque['raison'] }}</p>
@if (! empty($risque['facteurs']))
<p class="font-label-sm text-label-sm text-on-surface-variant">{{ implode(' · ', array_slice($risque['facteurs'], 0, 3)) }}</p>
@endif
</div>
@endforeach
</div>
</div>
@endif

{{-- Corrélation chaleur ↔ coupures : dépendance partielle du modèle (admin). --}}
@php
    $pd = collect($insights['partial_dependence'] ?? [])
        ->filter(fn ($p) => isset($p['temp'], $p['p24']))
        ->sortBy('temp')
        ->values();
    $maxP = $pd->isNotEmpty() ? max((float) $pd->max('p48'), 0.01) : 0.01;
@endphp
@if ($pd->count() >= 2)
@php
    $xG = 48.0; $xD = 700.0;
    $tMin = (float) $pd->first()['temp']; $tMax = (float) $pd->last()['temp'];
    $xPour = fn (float $t): float => round($xG + (($t - $tMin) / max(0.001, $tMax - $tMin)) * ($xD - $xG), 1);
    $yPour = fn (float $p): float => round(198.0 - ($p / $maxP) * 160.0, 1);
    $pts24 = $pd->map(fn ($p) => $xPour((float) $p['temp']).','.$yPour((float) $p['p24']))->implode(' ');
    $pts48 = $pd->map(fn ($p) => $xPour((float) $p['temp']).','.$yPour((float) $p['p48']))->implode(' ');
@endphp
<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md">
<div class="flex items-center gap-2 mb-2">
<span class="material-symbols-outlined text-tertiary-container text-[22px]">trending_up</span>
<h2 class="font-title-md text-title-md text-on-surface font-semibold">Corrélation chaleur ↔ coupures <span class="font-body-sm text-body-sm text-on-surface-variant font-normal">(risque moyen selon la température)</span></h2>
</div>
<svg viewBox="0 0 720 220" class="w-full h-auto" role="img" aria-label="Risque de coupure croissant avec la température">
<line x1="{{ $xG }}" y1="198" x2="{{ $xD }}" y2="198" stroke="rgb(var(--md-outline-variant))" stroke-width="1" />
<polyline points="{{ $pts48 }}" fill="none" stroke="#ea580c" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
<polyline points="{{ $pts24 }}" fill="none" stroke="#dc2626" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" />
@foreach ($pd as $p)
<circle cx="{{ $xPour((float) $p['temp']) }}" cy="{{ $yPour((float) $p['p24']) }}" r="3" fill="#dc2626" />
<text x="{{ $xPour((float) $p['temp']) }}" y="214" text-anchor="middle" font-size="10" fill="rgb(var(--md-on-surface-variant))">{{ round($p['temp']) }}</text>
@endforeach
<text x="{{ $xG }}" y="20" font-size="11" fill="rgb(var(--md-on-surface-variant))">Probabilité (%) — axe des températures (°C)</text>
</svg>
<div class="flex items-center gap-4 mt-1 font-body-sm text-body-sm text-on-surface-variant">
<span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full" style="background:#dc2626"></span>Coupure sous 24 h</span>
<span class="inline-flex items-center gap-1"><span class="h-2 w-4 rounded-full" style="background:#ea580c"></span>Coupure sous 48 h</span>
<span class="ml-auto">Pic affiché ≈ {{ round($maxP * 100, 1) }} %</span>
</div>
</div>
@endif

{{-- Barre d'outils : tri --}}
<div class="flex flex-col sm:flex-row sm:items-center gap-3">
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $coupures->total() }} coupure(s) au total</p>
<form method="GET" action="{{ route('back.coupures.index') }}" class="sm:ml-auto flex gap-2 items-center">
<label for="tri" class="font-label-md text-label-md text-on-surface-variant">Trier :</label>
<select id="tri" name="tri" onchange="this.form.submit()" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="recent" @selected($tri === 'recent')>Plus récentes</option>
<option value="ancien" @selected($tri === 'ancien')>Plus anciennes</option>
<option value="zone" @selected($tri === 'zone')>Zone (A → Z)</option>
<option value="statut" @selected($tri === 'statut')>Statut</option>
<option value="type" @selected($tri === 'type')>Type</option>
</select>
</form>
</div>

{{-- Tableau --}}
<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 overflow-hidden">
<div class="overflow-x-auto">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20 bg-surface-container-lowest/60">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Zone</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Type</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Confirm.</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Horaires</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
@forelse ($coupures as $coupure)
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60 last:border-0">
<td class="px-6 py-4">
<p class="font-title-md text-title-md text-on-surface font-medium flex items-center gap-2"><span class="h-2 w-2 rounded-full shrink-0 @if(($coupure->statut?->value ?? $coupure->statut) === 'en_cours') bg-red-600 @elseif(($coupure->statut?->value ?? $coupure->statut) === 'prevue') bg-orange-500 @else bg-green-600 @endif"></span>{{ $coupure->quartier?->nom ?? $coupure->lieu ?? 'Point sur la carte' }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant ml-4">@if ($coupure->quartier){{ $coupure->quartier->ville }}@if($coupure->lieu) · {{ $coupure->lieu }}@endif @elseif ($coupure->hasCoordinates())<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[14px]">my_location</span>{{ rtrim(rtrim(number_format((float) $coupure->latitude, 4, ',', ''), '0'), ',') }}, {{ rtrim(rtrim(number_format((float) $coupure->longitude, 4, ',', ''), '0'), ',') }}</span>@else—@endif</p>
</td>
<td class="px-6 py-4 text-on-surface-variant whitespace-nowrap">{{ $coupure->type?->label() ?? $coupure->type }}</td>
<td class="px-6 py-4">
@if (($coupure->statut?->value ?? $coupure->statut) === 'en_cours')
<span class="px-2.5 py-0.5 rounded-full bg-red-100 text-red-800 font-label-sm text-label-sm font-semibold whitespace-nowrap">En cours</span>
@elseif (($coupure->statut?->value ?? $coupure->statut) === 'prevue')
<span class="px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-800 font-label-sm text-label-sm font-semibold whitespace-nowrap">Prévue</span>
@else
<span class="px-2.5 py-0.5 rounded-full bg-green-100 text-green-800 font-label-sm text-label-sm font-semibold whitespace-nowrap">Résolue</span>
@endif
</td>
<td class="px-6 py-4 text-on-surface-variant whitespace-nowrap"><span class="inline-flex items-center gap-1" title="{{ $coupure->confirmations }} habitant(s) confirment"><span class="material-symbols-outlined text-[16px]">groups</span>{{ $coupure->confirmations }}</span></td>
<td class="px-6 py-4 text-on-surface-variant whitespace-nowrap font-body-sm text-body-sm">{{ $coupure->debut?->format('d/m/Y H:i') }}<br /><span class="text-on-surface-variant/80">→ {{ $coupure->fin?->format('d/m/Y H:i') ?? '—' }}</span></td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
<a href="{{ route('back.coupures.show', $coupure->id) }}" title="Voir" aria-label="Voir" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-on-surface-variant hover:bg-surface-container-high"><span class="material-symbols-outlined text-[18px]">visibility</span></a>
<a href="{{ route('back.coupures.edit', $coupure->id) }}" title="Modifier" aria-label="Modifier" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-primary hover:bg-primary-container/20"><span class="material-symbols-outlined text-[18px]">edit</span></a>
<form method="POST" action="{{ route('back.coupures.destroy', $coupure->id) }}" class="inline" onsubmit="return confirm('Supprimer cette coupure ?');">@csrf @method('DELETE')<button type="submit" title="Supprimer" aria-label="Supprimer" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-error hover:bg-error-container/40"><span class="material-symbols-outlined text-[18px]">delete</span></button></form>
</td>
</tr>
@empty
<tr><td colspan="6" class="px-6 py-10 text-center">
<p class="font-title-md text-title-md text-on-surface font-medium">Aucune coupure pour le moment</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Publiez une coupure prévue (maintenance, délestage) avec le bouton ci-dessus.</p>
</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div class="flex flex-col md:flex-row md:items-center gap-2 md:justify-between">
<p class="font-body-sm text-body-sm text-on-surface-variant">Affichage {{ $coupures->firstItem() ?? 0 }}–{{ $coupures->lastItem() ?? 0 }} sur {{ $coupures->total() }} · page {{ $coupures->currentPage() }}/{{ $coupures->lastPage() }}</p>
{{ $coupures->links() }}
</div>

@php
    // Incidents IA géolocalisés (centroïde + rayon) pour la carte Leaflet.
    $incidentsJs = collect($anomalies)
        ->map(fn ($a) => [
            'centroid' => $a['centroid'] ?? null,
            'radius_m' => $a['radius_m'] ?? 300,
            'logements' => $a['logements_estimes'] ?? 0,
            'message' => $a['message'] ?? '',
        ])
        ->filter(fn ($a) => is_array($a['centroid']) && count($a['centroid']) === 2)
        ->values();
@endphp
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Marqueurs préparés côté contrôleur (Back\CoupureController@index) : tout le
// périmètre (pas seulement la page), avec lien direct vers la modification.
const CENTRE_BACK = @json($centre ?? [36.8065, 10.1815]);
const MARQUEURS_BACK = @json($marqueurs ?? []);
const INCIDENTS_BACK = @json($incidentsJs ?? []);

const carteBack = L.map('carte-back-coupures').setView(CENTRE_BACK, 12);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(carteBack);

const COULEURS_BACK = { en_cours: '#dc2626', prevue: '#ea580c', resolue: '#16a34a' };

// Les popups affichent du HTML : on échappe les données saisies.
function echapperBack(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
}

MARQUEURS_BACK.forEach((m) => {
    const couleur = COULEURS_BACK[m.statut] || '#1b77ba';
    L.circleMarker([m.lat, m.lng], {
        radius: 10, color: couleur, fillColor: couleur, fillOpacity: 0.7, weight: 2,
    }).addTo(carteBack).bindPopup(
        '<strong>' + echapperBack(m.titre) + '</strong><br>' + echapperBack(m.detail) +
        '<br><a href="' + m.edit + '">Modifier →</a>'
    );
});

// Incidents suspects (IA) : cercle de rayon autour du centroïde + logements estimés.
INCIDENTS_BACK.forEach((inc) => {
    L.circle([inc.centroid[0], inc.centroid[1]], {
        radius: inc.radius_m, color: '#7c3aed', fillColor: '#7c3aed', fillOpacity: 0.12, weight: 2, dashArray: '6 4',
    }).addTo(carteBack).bindPopup(
        '<strong>Incident suspect (IA)</strong><br>' + echapperBack(inc.message) +
        (inc.logements ? '<br>~' + inc.logements + ' logements concernés' : '')
    );
});
</script>
</x-back-layout>
