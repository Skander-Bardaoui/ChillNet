<x-public-layout title="Coupures de courant">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />

{{-- Fil d'Ariane --}}
<nav aria-label="Fil d'Ariane" class="flex items-center gap-2 font-body-sm text-body-sm text-on-surface-variant">
<a href="{{ route('home') }}" class="inline-flex items-center gap-1 text-primary hover:underline"><span class="material-symbols-outlined text-[16px]">home</span>Accueil</a>
<span aria-hidden="true">/</span>
<span class="text-on-surface font-medium">Coupures de courant</span>
</nav>

{{-- En-tête : titre + explication + actions --}}
<header class="rounded-2xl bg-surface-container-low p-space-md md:p-space-lg shadow-sm flex flex-col lg:flex-row lg:items-center gap-space-md">
<div class="flex items-start gap-space-sm flex-1">
<div class="p-3 rounded-xl bg-red-100 text-red-800 shrink-0"><span class="material-symbols-outlined text-[28px]">power_off</span></div>
<div>
<p class="font-label-sm text-label-sm uppercase tracking-widest text-red-800 font-semibold">Module 2 · Réseau électrique</p>
<h1 class="font-headline-md text-headline-md md:font-headline-lg md:text-headline-lg text-on-surface font-bold">Carte des coupures en direct</h1>
<p class="font-body-md text-body-md text-on-surface-variant mt-1 max-w-2xl">Les points <strong>rouges</strong> sont les coupures <strong>en cours</strong> signalées par les habitants. Les points <strong>orange</strong> sont les coupures <strong>prévues</strong> publiées par les gestionnaires (maintenance, délestage programmé). Cliquez un point pour le détail.</p>
</div>
</div>
<div class="flex flex-wrap gap-2 shrink-0">
@auth
@if (auth()->user()->isHabitant())
<a href="{{ route('coupures.create') }}" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">report</span>Signaler une coupure</a>
@else
<a href="{{ route('back.coupures.index') }}" class="px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">dashboard</span>Espace de gestion</a>
@endif
@else
<a href="{{ route('login') }}" class="px-space-md py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95 shadow inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">login</span>Se connecter pour signaler</a>
@endauth
<a href="#liste-coupures" class="px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant inline-flex items-center gap-2"><span class="material-symbols-outlined text-[18px]">list</span>Voir la liste</a>
</div>
</header>

{{-- 3 chiffres clés --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-space-sm">
<div class="rounded-xl bg-red-50 border border-red-200 p-space-md flex items-center gap-3">
<span class="material-symbols-outlined text-red-700 text-[28px]">bolt</span>
<div><p class="font-headline-sm text-headline-sm text-red-900 font-bold">{{ $nbActives }}</p><p class="font-body-sm text-body-sm text-red-800">coupure(s) en cours</p></div>
</div>
<div class="rounded-xl bg-orange-50 border border-orange-200 p-space-md flex items-center gap-3">
<span class="material-symbols-outlined text-orange-700 text-[28px]">construction</span>
<div><p class="font-headline-sm text-headline-sm text-orange-900 font-bold">{{ $nbPrevues }}</p><p class="font-body-sm text-body-sm text-orange-800">coupure(s) prévue(s)</p></div>
</div>
<div class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-space-md flex items-center gap-3">
<span class="material-symbols-outlined text-primary text-[28px]">location_on</span>
<div><p class="font-headline-sm text-headline-sm text-on-surface font-bold">{{ $nbZones }}</p><p class="font-body-sm text-body-sm text-on-surface-variant">zone(s) touchée(s)</p></div>
</div>
</div>

{{-- Anomalie IA : afflux de signalements = possible incident majeur non déclaré. --}}
@if ($anomalies->isNotEmpty())
<section class="rounded-2xl bg-red-50 border border-red-300 p-space-md flex items-start gap-3" role="alert">
<span class="material-symbols-outlined text-red-700 text-[28px] shrink-0">crisis_alert</span>
<div>
<p class="font-title-md text-title-md text-red-900 font-semibold">Anomalie détectée par l'IA</p>
<ul class="mt-1 flex flex-col gap-1">
@foreach ($anomalies as $anomalie)
<li class="font-body-sm text-body-sm text-red-800 flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">bolt</span>{{ $anomalie['message'] }}</li>
@endforeach
</ul>
</div>
</section>
@endif

{{-- Score de risque IA par zone : historique des coupures corrélé à la canicule. --}}
@if ($risques->isNotEmpty())
<section class="flex flex-col gap-space-sm">
<h2 class="font-title-lg text-title-lg text-on-surface font-semibold px-1 flex items-center gap-2">Risque de coupure par zone <span class="font-body-sm text-body-sm text-primary font-normal inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]">auto_awesome</span>IA · historique + canicule</span></h2>
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-space-sm">
@foreach ($risques->take(6) as $risque)
@php $nr = $risque['niveau']; @endphp
<article class="rounded-xl bg-surface-container-low border border-outline-variant/20 p-space-md shadow-sm flex flex-col gap-2">
<div class="flex items-center justify-between gap-2">
<span class="font-title-md text-title-md text-on-surface font-semibold inline-flex items-center gap-1"><span class="material-symbols-outlined text-[18px]" style="color: {{ $nr->couleurHex() }};">{{ $nr->icone() }}</span>{{ $risque['quartier']->nom }}</span>
<span class="px-2.5 py-0.5 rounded-full {{ $nr->badgeClasses() }} font-label-sm text-label-sm font-semibold whitespace-nowrap">{{ $nr->label() }}</span>
</div>
<div class="h-2 w-full rounded-full bg-surface-container-high overflow-hidden" role="img" aria-label="Score de risque {{ $risque['score'] }} sur 100">
<div class="h-full rounded-full" style="width: {{ $risque['score'] }}%; background: {{ $nr->couleurHex() }};"></div>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">Score {{ $risque['score'] }}/100 · {{ $risque['raison'] }}</p>
@if (! empty($risque['facteurs']))
<p class="font-label-sm text-label-sm text-on-surface-variant">{{ implode(' · ', $risque['facteurs']) }}</p>
@endif
</article>
@endforeach
</div>
</section>
@endif

{{-- Carte + filtre côte à côte --}}
<section class="grid grid-cols-1 lg:grid-cols-3 gap-space-sm">
<div class="lg:col-span-2 rounded-2xl overflow-hidden bg-surface-container-low shadow-md border border-outline-variant/20">
<div class="flex items-center gap-2 px-space-md py-2.5 border-b border-outline-variant/20">
<span class="h-2.5 w-2.5 rounded-full bg-red-600 animate-pulse"></span>
<span class="font-label-md text-label-md text-on-surface font-semibold">Carte interactive</span>
<span class="ml-auto hidden md:inline font-body-sm text-body-sm text-on-surface-variant">🔴 en cours · 🟠 prévue</span>
</div>
<div id="carte-coupures" class="w-full h-80 md:h-96 z-0"></div>
</div>
<div class="rounded-2xl bg-surface-container-low shadow-md border border-outline-variant/20 p-space-md flex flex-col gap-space-sm">
<h2 class="font-title-lg text-title-lg text-on-surface font-semibold flex items-center gap-2"><span class="material-symbols-outlined text-primary">filter_alt</span>Filtrer par zone</h2>
<p class="font-body-sm text-body-sm text-on-surface-variant">Choisissez une zone : la <strong>carte se recentre</strong> aussitôt et la <strong>liste se filtre</strong> après clic sur Filtrer.</p>
<form method="GET" action="{{ route('coupures.index') }}" class="flex flex-col gap-3">
<label for="quartier_id" class="font-label-md text-label-md text-on-surface font-medium">Zone touchée</label>
<select id="quartier_id" name="quartier_id" class="rounded-xl bg-surface-container-high text-on-surface px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary">
<option value="">— Toutes les zones —</option>
@foreach ($quartiers as $q)
<option value="{{ $q->id }}" @selected(request('quartier_id') == $q->id)>{{ $q->nom }} ({{ $q->ville }})</option>
@endforeach
</select>
<label for="tri" class="font-label-md text-label-md text-on-surface font-medium">Trier par</label>
<select id="tri" name="tri" class="rounded-xl bg-surface-container-high text-on-surface px-3 py-2.5 focus:outline-none focus:ring-2 focus:ring-primary">
<option value="recent" @selected($tri === 'recent')>Plus récentes d'abord</option>
<option value="ancien" @selected($tri === 'ancien')>Plus anciennes d'abord</option>
<option value="zone" @selected($tri === 'zone')>Zone (A → Z)</option>
<option value="statut" @selected($tri === 'statut')>Statut (en cours d'abord)</option>
<option value="type" @selected($tri === 'type')>Type</option>
</select>
<div class="flex gap-2">
<button type="submit" class="flex-1 px-4 py-2.5 rounded-xl bg-primary-container text-on-primary-container font-label-md text-label-md font-semibold hover:opacity-95">Filtrer la liste</button>
@if (request()->filled('quartier_id'))
<a href="{{ route('coupures.index') }}" class="px-4 py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant">Tout voir</a>
@endif
</div>
</form>
<div class="rounded-xl bg-surface-container-high p-3 font-body-sm text-body-sm text-on-surface-variant flex gap-2">
<span class="material-symbols-outlined text-primary text-[20px] shrink-0">info</span>
<span>Habitant ? Un bouton <strong>« Signaler »</strong> en haut de page crée une coupure <strong>en cours</strong>, visible ici après validation anti-doublon.</span>
</div>
</div>
</section>

{{-- Liste paginée (10 par page) : en cours en rouge, prévues en orange. --}}
<section id="liste-coupures" class="flex flex-col gap-space-sm scroll-mt-28">
<h2 class="font-title-lg text-title-lg text-on-surface font-semibold px-1">Détail des coupures <span class="font-body-md text-body-md text-on-surface-variant font-normal">({{ $nbActives }} en cours · {{ $nbPrevues }} prévues)</span></h2>
<p class="font-body-sm text-body-sm text-on-surface-variant px-1">Affichage {{ $coupures->firstItem() ?? 0 }}–{{ $coupures->lastItem() ?? 0 }} sur {{ $coupures->total() }} · page {{ $coupures->currentPage() }}/{{ $coupures->lastPage() }}</p>
@forelse ($coupures as $coupure)
@if (($coupure->statut?->value ?? $coupure->statut) === 'en_cours')
<article class="rounded-2xl bg-surface-container-low shadow-sm border-l-4 border-red-600 border border-outline-variant/20 p-space-md flex flex-col md:flex-row md:items-center gap-space-sm">
<div class="flex-1">
<div class="flex items-center gap-2 flex-wrap">
<h3 class="font-title-md text-title-md text-on-surface font-semibold">{{ $coupure->type?->label() }} — {{ $coupure->quartier?->nom }}</h3>
<span class="px-2.5 py-0.5 rounded-full bg-red-100 text-red-800 font-label-sm text-label-sm uppercase font-semibold">En cours</span>
</div>
@if ($coupure->lieu)
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">place</span>{{ $coupure->lieu }}</p>
@endif
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 flex flex-wrap gap-x-4 gap-y-1">
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">schedule</span>De {{ $coupure->debut?->format('d/m/Y H:i') }} à {{ $coupure->fin?->format('d/m/Y H:i') ?? 'heure inconnue' }}</span>
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">location_on</span>{{ $coupure->quartier?->ville }}</span>
</p>
@if ($coupure->description)
<p class="font-body-md text-body-md text-on-surface mt-2">{{ $coupure->description }}</p>
@endif
<p class="font-body-sm text-body-sm text-on-surface-variant mt-2 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">groups</span>{{ $coupure->confirmations }} voisin(s) confirment aussi cette coupure</p>
</div>
<div class="shrink-0 flex flex-col gap-2">
<a href="tel:0800066666" class="inline-flex items-center gap-2 px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant"><span class="material-symbols-outlined text-[18px] text-primary">call</span>0800 06 66 66</a>
@auth
@if (auth()->user()->isHabitant())
@if (session()->get('coupure_confirmee_'.$coupure->id))
<span class="inline-flex items-center justify-center gap-1 px-space-md py-2.5 rounded-xl bg-green-100 text-green-800 font-label-md text-label-md font-semibold"><span class="material-symbols-outlined text-[18px]">check_circle</span>Confirmé</span>
@elseif ($coupure->user_id && (int) $coupure->user_id === (int) auth()->id())
<span class="inline-flex items-center justify-center px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface-variant font-label-md text-label-md" title="Ce sont vos voisins qui confirment">Votre signalement</span>
@else
<form method="POST" action="{{ route('coupures.confirm', $coupure->id) }}">@csrf<button type="submit" class="w-full inline-flex items-center justify-center gap-1 px-space-md py-2.5 rounded-xl bg-red-700 text-white font-label-md text-label-md font-semibold hover:bg-red-800"><span class="material-symbols-outlined text-[18px]">thumb_up</span>Je confirme aussi</button></form>
@endif
@else
<a href="{{ route('back.coupures.index') }}" class="inline-flex items-center justify-center px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant">Gérer dans le back office</a>
@endif
@else
<a href="{{ route('login') }}" class="inline-flex items-center justify-center px-space-md py-2.5 rounded-xl bg-surface-container-high text-on-surface font-label-md text-label-md hover:bg-surface-variant">Connectez-vous pour confirmer</a>
@endauth
</div>
</article>
@else
<article class="rounded-2xl bg-surface-container-low shadow-sm border-l-4 border-orange-500 border border-outline-variant/20 p-space-md">
<div class="flex items-center gap-2 flex-wrap">
<h3 class="font-title-md text-title-md text-on-surface font-semibold">{{ $coupure->type?->label() }} — {{ $coupure->quartier?->nom }}</h3>
<span class="px-2.5 py-0.5 rounded-full bg-orange-100 text-orange-800 font-label-sm text-label-sm uppercase font-semibold">Prévue</span>
</div>
@if ($coupure->lieu)
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">place</span>{{ $coupure->lieu }}</p>
@endif
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1 flex flex-wrap gap-x-4 gap-y-1">
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">event</span>Intervention le {{ $coupure->debut?->format('d/m/Y H:i') }} → {{ $coupure->fin?->format('d/m/Y H:i') ?? '—' }}</span>
<span class="inline-flex items-center gap-1"><span class="material-symbols-outlined text-[16px]">location_on</span>{{ $coupure->quartier?->ville }}</span>
</p>
@if ($coupure->description)
<p class="font-body-md text-body-md text-on-surface mt-2">{{ $coupure->description }}</p>
@endif
</article>
@endif
@empty
<div class="rounded-2xl bg-green-50 border border-green-200 p-space-md flex items-center gap-3">
<span class="material-symbols-outlined text-green-700 text-[28px]">check_circle</span>
<p class="font-body-md text-body-md text-green-900">Aucune coupure @if(request()->filled('quartier_id')) sur cette zone @endif. Le réseau est stable.</p>
</div>
@endforelse
<div class="mt-2">
{{ $coupures->links() }}
</div>
</section>

<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
// Données préparées côté contrôleur (voir Front\CoupureController@index).
const CENTRE = @json($centre);
const QUARTIERS = @json($quartiersCoords ?? []);
const MARQUEURS = @json($marqueurs ?? []);

const carte = L.map('carte-coupures').setView(CENTRE, 12);
L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap',
}).addTo(carte);

// Les popups Leaflet affichent du HTML : on échappe les données saisies
// (descriptions d'habitants) pour éviter toute injection de script.
function echapper(texte) {
    const div = document.createElement('div');
    div.textContent = texte ?? '';
    return div.innerHTML;
}

MARQUEURS.forEach((m) => {
    const couleur = m.statut === 'en_cours' ? '#dc2626' : '#ea580c';
    L.circleMarker([m.lat, m.lng], {
        radius: 10, color: couleur, fillColor: couleur, fillOpacity: 0.7, weight: 2,
    }).addTo(carte).bindPopup('<strong>' + echapper(m.titre) + '</strong><br>' + echapper(m.detail));
});

// Filtre synchro : changer la zone recentre la carte sans attendre le submit.
document.getElementById('quartier_id').addEventListener('change', (e) => {
    const q = QUARTIERS[e.target.value];
    if (q) {
        carte.setView([q.lat, q.lng], 13);
    } else {
        carte.setView(CENTRE, 12);
    }
});
</script>
</x-public-layout>
