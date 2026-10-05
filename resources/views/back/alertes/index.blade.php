<x-back-layout :title="'Alertes canicule'">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

{{-- En-tête du module --}}
<div class="rounded-2xl bg-surface-container-low shadow-sm border border-outline-variant/20 p-space-md flex flex-col lg:flex-row lg:items-center gap-4">
<div class="flex items-start gap-3 flex-1">
<div class="p-3 rounded-xl bg-red-100 text-red-800 shrink-0"><span class="material-symbols-outlined text-[28px]">warning</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-widest text-on-surface-variant font-semibold">Module 1 · @if(auth()->user()->isGestionnaire()) Votre zone uniquement @else Référentiel global @endif</p>
<p class="font-body-md text-body-md text-on-surface-variant mt-1">Créez une alerte canicule (une ou plusieurs zones), laissez l'IA <strong>classer le niveau</strong> et <strong>rédiger le message</strong>, puis <strong>validez-la</strong> pour la publier aux habitants.</p>
</div>
</div>
<a href="{{ route('back.alertes.create') }}" class="shrink-0 px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 inline-flex items-center justify-center gap-2 shadow"><span class="material-symbols-outlined text-[18px]">add</span>Nouvelle alerte</a>
</div>

{{-- Chiffres clés --}}
<div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
<div class="rounded-xl bg-red-50 border border-red-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-red-700 text-[28px]">local_fire_department</span>
<div><p class="font-headline-sm text-headline-sm text-red-900 font-bold">{{ $stats['actives'] }}</p><p class="font-body-sm text-body-sm text-red-800">active(s)</p></div>
</div>
<div class="rounded-xl bg-sky-50 border border-sky-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-sky-700 text-[28px]">event_upcoming</span>
<div><p class="font-headline-sm text-headline-sm text-sky-900 font-bold">{{ $stats['programmees'] }}</p><p class="font-body-sm text-body-sm text-sky-800">programmée(s)</p></div>
</div>
<div class="rounded-xl bg-amber-50 border border-amber-200 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-amber-700 text-[28px]">pending_actions</span>
<div><p class="font-headline-sm text-headline-sm text-amber-900 font-bold">{{ $stats['brouillons'] }}</p><p class="font-body-sm text-body-sm text-amber-800">à valider</p></div>
</div>
<div class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-4 flex items-center gap-3">
<span class="material-symbols-outlined text-primary text-[28px]">history</span>
<div><p class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $stats['terminees'] }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">terminée(s)</p></div>
</div>
</div>

{{-- Carte du périmètre : un point par alerte × quartier, couleur du niveau. --}}
<div class="rounded-2xl overflow-hidden bg-surface-container-low shadow-md border border-outline-variant/20">
<div class="flex items-center gap-2 px-space-md py-2.5 border-b border-outline-variant/20">
<span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-pulse"></span>
<span class="font-label-md text-label-md text-on-surface font-semibold">Carte du périmètre</span>
<span class="ml-auto hidden md:inline font-body-sm text-body-sm text-on-surface-variant">🟡 jaune · 🟠 orange · 🔴 rouge — cercle = zone couverte (rayon)</span>
</div>
<div id="carte-back-alertes" class="w-full h-72 md:h-80 z-0"></div>
</div>

{{-- Filtres --}}
<form method="GET" action="{{ route('back.alertes.index') }}" class="flex flex-col sm:flex-row sm:items-center gap-3">
<p class="font-body-sm text-body-sm text-on-surface-variant">{{ $alertes->total() }} alerte(s) au total</p>
<div class="sm:ml-auto flex flex-wrap gap-2 items-center">
@if ($quartiers->count() > 1)
<label for="quartier_id" class="font-label-md text-label-md text-on-surface-variant">Quartier :</label>
<select id="quartier_id" name="quartier_id" onchange="this.form.submit()" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">Tous</option>
@foreach ($quartiers as $q)
<option value="{{ $q->id }}" @selected((int) request('quartier_id') === $q->id)>{{ $q->nom }}</option>
@endforeach
</select>
@endif
<label for="niveau" class="font-label-md text-label-md text-on-surface-variant">Niveau :</label>
<select id="niveau" name="niveau" onchange="this.form.submit()" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="">Tous</option>
<option value="rouge" @selected(request('niveau') === 'rouge')>Rouge</option>
<option value="orange" @selected(request('niveau') === 'orange')>Orange</option>
<option value="jaune" @selected(request('niveau') === 'jaune')>Jaune</option>
</select>
<label for="tri" class="font-label-md text-label-md text-on-surface-variant">Trier :</label>
<select id="tri" name="tri" onchange="this.form.submit()" class="rounded-xl bg-surface-container-low border border-outline-variant/40 px-3 py-2 text-on-surface focus:border-primary-container focus:outline-none">
<option value="recent" @selected($tri === 'recent')>Plus récentes</option>
<option value="ancien" @selected($tri === 'ancien')>Plus anciennes</option>
<option value="niveau" @selected($tri === 'niveau')>Gravité</option>
<option value="statut" @selected($tri === 'statut')>Statut</option>
<option value="zone" @selected($tri === 'zone')>Zone (A → Z)</option>
</select>
</div>
</form>

{{-- Tableau --}}
<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 overflow-hidden">
<div class="overflow-x-auto">
<table class="min-w-full">
<thead><tr class="border-b border-outline-variant/20 bg-surface-container-lowest/60">
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Alerte</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Niveau</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Zone</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Créneau</th>
<th scope="col" class="px-6 py-3 text-left font-label-sm text-label-sm uppercase text-on-surface-variant">Statut</th>
<th scope="col" class="px-6 py-3"><span class="sr-only">Actions</span></th>
</tr></thead>
<tbody>
@forelse ($alertes as $alerte)
<tr class="border-b border-outline-variant/10 hover:bg-surface-container/60 last:border-0">
<td class="px-6 py-4">
<p class="font-title-md text-title-md text-on-surface font-medium">{{ $alerte->titre }}</p>
<p class="font-body-sm text-body-sm text-on-surface-variant">Seuil {{ rtrim(rtrim(number_format((float) $alerte->seuil_temperature, 1, ',', ''), '0'), ',') }}°C
@if ($alerte->temperature_actuelle !== null) · mesuré {{ rtrim(rtrim(number_format((float) $alerte->temperature_actuelle, 1, ',', ''), '0'), ',') }}°C @endif
@if ($alerte->source_meteo === 'weatherapi')<span class="text-primary">· météo live</span> @endif
</p>
</td>
<td class="px-6 py-4">
<span class="px-2.5 py-0.5 rounded-full {{ $alerte->niveau?->badgeClasses() }} font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $alerte->niveau?->label() }}</span>
</td>
<td class="px-6 py-4 text-on-surface-variant">
@php $noms = $alerte->quartiers->pluck('nom'); @endphp
@if ($alerte->hasCoordinates())
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">radio_button_unchecked</span>Rayon {{ rtrim(rtrim(number_format($alerte->rayonMetres() / 1000, 1, ',', ''), '0'), ',') }} km</span>
@if ($noms->isNotEmpty())
<span class="block font-body-sm text-body-sm">+ {{ $noms->implode(', ') }}</span>
@endif
@elseif ($noms->isEmpty())
—
@else
<span title="{{ $noms->implode(', ') }}">{{ $noms->first() }}@if($noms->count() > 1) <span class="font-label-sm text-label-sm text-primary">+{{ $noms->count() - 1 }}</span>@endif</span>
@endif
</td>
<td class="px-6 py-4 text-on-surface-variant whitespace-nowrap font-body-sm text-body-sm">{{ $alerte->debut?->format('d/m/Y H:i') }}<br /><span class="text-on-surface-variant/80">→ {{ $alerte->fin?->format('d/m/Y H:i') }}</span></td>
<td class="px-6 py-4 whitespace-nowrap">
<span class="px-2.5 py-0.5 rounded-full {{ $alerte->statut->badgeClasses() }} font-label-sm text-label-sm font-semibold">{{ $alerte->statut->label() }}</span>
@if (! $alerte->validee)
<span class="ml-1 px-2.5 py-0.5 rounded-full bg-amber-100 text-amber-800 font-label-sm text-label-sm font-semibold">À valider</span>
@endif
</td>
<td class="px-6 py-4 text-right space-x-3 whitespace-nowrap">
@if (! $alerte->validee)
<form method="POST" action="{{ route('back.alertes.valider', $alerte->id) }}" class="inline">@csrf @method('PATCH')<button type="submit" class="inline-flex items-center gap-1 text-green-700 hover:underline font-label-md text-label-md"><span class="material-symbols-outlined text-[16px]">check_circle</span>Valider</button></form>
@endif
<a href="{{ route('back.alertes.show', $alerte->id) }}" title="Voir" aria-label="Voir" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-on-surface-variant hover:bg-surface-container-high"><span class="material-symbols-outlined text-[18px]">visibility</span></a>
<a href="{{ route('back.alertes.edit', $alerte->id) }}" title="Modifier" aria-label="Modifier" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-primary hover:bg-primary-container/20"><span class="material-symbols-outlined text-[18px]">edit</span></a>
<form method="POST" action="{{ route('back.alertes.destroy', $alerte->id) }}" class="inline" onsubmit="return confirm('Supprimer cette alerte ?');">@csrf @method('DELETE')<button type="submit" title="Supprimer" aria-label="Supprimer" class="inline-flex items-center justify-center h-9 w-9 rounded-lg text-error hover:bg-error-container/40"><span class="material-symbols-outlined text-[18px]">delete</span></button></form>
</td>
</tr>
@empty
<tr><td colspan="6" class="px-6 py-10 text-center">
<p class="font-title-md text-title-md text-on-surface font-medium">Aucune alerte pour le moment</p>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">Créez une alerte canicule avec le bouton ci-dessus — l'IA pré-remplit le niveau et le message.</p>
</td></tr>
@endforelse
</tbody>
</table>
</div>
</div>
<div class="flex flex-col md:flex-row md:items-center gap-2 md:justify-between">
<p class="font-body-sm text-body-sm text-on-surface-variant">Affichage {{ $alertes->firstItem() ?? 0 }}–{{ $alertes->lastItem() ?? 0 }} sur {{ $alertes->total() }} · page {{ $alertes->currentPage() }}/{{ $alertes->lastPage() }}</p>
{{ $alertes->links() }}
</div>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const CENTRE_BACK_ALERTES = @json($centre ?? [36.8065, 10.1815]);
const MARQUEURS_BACK_ALERTES = @json($marqueurs ?? []);

const carteBackAlertes = L.map('carte-back-alertes').setView(CENTRE_BACK_ALERTES, 12);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(carteBackAlertes);

// Les popups affichent du HTML : on échappe les données saisies.
function echapperAlerte(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
}

MARQUEURS_BACK_ALERTES.forEach((m) => {
    const couleur = m.couleur || '#1b77ba';

    // Zone géographique : un cercle rempli + un point central cliquable.
    if (m.rayon) {
        L.circle([m.lat, m.lng], {
            radius: m.rayon, color: couleur, fillColor: couleur, fillOpacity: 0.15, weight: 1.5,
        }).addTo(carteBackAlertes);
    }

    L.circleMarker([m.lat, m.lng], {
        radius: 11, color: couleur, fillColor: couleur, fillOpacity: 0.75, weight: 2,
    }).addTo(carteBackAlertes).bindPopup(
        '<strong>' + echapperAlerte(m.titre) + '</strong><br>' + echapperAlerte(m.detail) +
        (m.rayon ? '<br>Rayon ' + Math.round(m.rayon / 100) / 10 + ' km' : '') +
        '<br><a href="' + m.edit + '">Modifier →</a>'
    );
});
</script>
</x-back-layout>
