<x-public-layout title="Conseils">
{{-- Screen Stitch — conseils & entraide de quartier (contenu statique FR) --}}

<!-- Command Strip / Ambient Subheader -->
<div class="w-full bg-surface-container-lowest/90 px-space-md py-space-sm backdrop-blur-xl rounded-xl">
<div class="flex flex-col md:flex-row items-start md:items-center justify-between gap-space-sm">
<div class="flex items-center gap-space-sm flex-wrap">
<div class="flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-error-container/40 text-on-error-container font-label-sm text-label-sm">
<span class="inline-block w-2 h-2 rounded-full bg-error animate-pulse"></span>
SEUIL CRITIQUE : 41.8°C RESSENTI
</div>
<span class="font-body-sm text-body-sm text-on-surface-variant hidden lg:inline">Plan Canicule Niveau 3 activé • Conseils vérifiés</span>
</div>
<div class="flex items-center gap-space-sm w-full md:w-auto justify-end">
<div class="flex items-center gap-2 px-3 py-1 rounded-lg bg-surface-container-high text-on-surface text-body-sm font-body-sm">
<span class="material-symbols-outlined text-primary-fixed-dim text-[16px]">bolt</span>
<span>Délestage préventif : <strong class="text-tertiary-fixed">14h00 – 16h30</strong></span>
</div>
<button class="flex items-center gap-1.5 px-3 py-1 rounded-lg bg-error-container text-on-error-container font-label-md text-label-md hover:bg-red-500 hover:text-white transition-all shadow-[0_0_16px_rgba(255,180,171,0.2)]" id="toggleUrgenceBtn" type="button" aria-haspopup="dialog" aria-controls="urgenceModal">
<span class="material-symbols-outlined text-[16px]">emergency_home</span>
<span>Alerte Non-Réponse</span>
</button>
</div>
</div>
</div>

<div class="flex flex-col gap-space-xl">
<!-- Top Hub Intro & Navigation Tabs -->
<div class="flex flex-col gap-space-md">
<div class="flex flex-col lg:flex-row lg:items-end justify-between gap-space-md">
<div class="max-w-3xl">
<div class="flex items-center gap-2 mb-1">
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary">Guide d'Entraide Locale &amp; Résilience</span>
<span class="text-outline-variant">•</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Version 4.2 Hors-Ligne Synchronisée</span>
</div>
<h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight">
Survie Thermique, Protection Médicale &amp; Entraide de Quartier
</h1>
<p class="font-body-lg text-body-lg text-on-surface-variant mt-1">
Protocoles techniques vérifiés pour faire face à la surchauffe urbaine combinée aux coupures électriques programmées.
</p>
</div>
<div class="flex flex-wrap items-center gap-space-xs bg-surface-container-low p-1.5 rounded-xl self-start lg:self-auto">
<a class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high transition-colors text-error font-label-md text-label-md" href="tel:190">
<span class="material-symbols-outlined text-[16px]">medical_services</span>
SAMU 190
</a>
<a class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high transition-colors text-tertiary-fixed font-label-md text-label-md" href="tel:0800066666">
<span class="material-symbols-outlined text-[16px]">support_agent</span>
Canicule Info 0800
</a>
<a class="flex items-center gap-1 px-3 py-1.5 rounded-lg bg-surface-container hover:bg-surface-container-high transition-colors text-primary font-label-md text-label-md" href="tel:114">
<span class="material-symbols-outlined text-[16px]">sms</span>
Urgence SMS 114
</a>
</div>
</div>
<div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-transparent" role="tablist" aria-label="Rubriques des conseils">
<button class="tab-button active flex items-center gap-2 px-4 py-2.5 rounded-lg font-title-md text-title-md bg-primary-container text-on-primary-container shadow-[0_0_16px_rgba(27,119,186,0.25)] transition-all" data-tab="gestes" type="button" role="tab" id="tabbtn-gestes" aria-controls="tab-gestes" aria-selected="true">
<span class="material-symbols-outlined text-[20px]">ac_unit</span>
<span>Conseils &amp; Gestes Clés</span>
</button>
<button class="tab-button flex items-center gap-2 px-4 py-2.5 rounded-lg font-title-md text-title-md bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-all" data-tab="sante" type="button" role="tab" id="tabbtn-sante" aria-controls="tab-sante" aria-selected="false" tabindex="-1">
<span class="material-symbols-outlined text-[20px]">vaccines</span>
<span>Médicaments &amp; Chaîne du Froid</span>
</button>
<button class="tab-button flex items-center gap-2 px-4 py-2.5 rounded-lg font-title-md text-title-md bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-all" data-tab="solidarite" type="button" role="tab" id="tabbtn-solidarite" aria-controls="tab-solidarite" aria-selected="false" tabindex="-1">
<span class="material-symbols-outlined text-[20px]">group</span>
<span>Réseau Solidarité Voisins</span>
<span class="px-1.5 py-0.5 rounded-full text-label-sm font-label-sm bg-tertiary-container text-on-tertiary-container">3 alertes</span>
</button>
<button class="tab-button flex items-center gap-2 px-4 py-2.5 rounded-lg font-title-md text-title-md bg-surface-container text-on-surface-variant hover:text-on-surface hover:bg-surface-container-high transition-all" data-tab="coupure" type="button" role="tab" id="tabbtn-coupure" aria-controls="tab-coupure" aria-selected="false" tabindex="-1">
<span class="material-symbols-outlined text-[20px]">power_off</span>
<span>Délestage &amp; Coupure Électrique</span>
</button>
</div>
</div>

<!-- Active Tab Panel 1: Conseils & Fiches Réflexes (Bento Layout) -->
<div class="tab-content flex flex-col gap-space-lg" id="tab-gestes" role="tabpanel" aria-labelledby="tabbtn-gestes" tabindex="0">
<div class="grid grid-cols-1 md:grid-cols-12 gap-space-md">
<div class="md:col-span-8 bg-surface-container-low rounded-xl p-space-lg flex flex-col justify-between relative overflow-hidden shadow-md">
<div class="absolute -right-16 -top-16 w-64 h-64 bg-primary/5 rounded-full blur-3xl pointer-events-none"></div>
<div class="flex flex-col gap-space-md relative z-10">
<div class="flex items-center justify-between">
<span class="px-2.5 py-1 rounded bg-surface-container text-primary font-label-sm text-label-sm tracking-wider uppercase">Fiche Protocole 01 • Physiologie</span>
<span class="font-body-sm text-body-sm text-on-surface-variant flex items-center gap-1">
<span class="material-symbols-outlined text-[16px] text-tertiary-container">schedule</span> 1 verre d'eau toutes les 25 min
</span>
</div>
<div>
<h3 class="font-headline-lg text-headline-lg text-on-surface font-semibold">
Maîtriser le stress thermo-physiologique en milieu confiné
</h3>
<p class="font-body-md text-body-md text-on-surface-variant mt-2 max-w-2xl">
Lorsque la température ambiante dépasse 37°C, le corps cesse d'évacuer la chaleur par convection. L'évaporation de la sueur devient le seul mécanisme résiduel. Sans brumisation ni renouvellement d'air sec, le risque de coup de chaleur létal augmente rapidement.
</p>
</div>
<div class="grid grid-cols-1 sm:grid-cols-3 gap-space-sm pt-space-xs">
<div class="bg-surface-container p-3 rounded-lg flex flex-col gap-1">
<div class="flex items-center justify-between text-on-surface-variant font-label-sm text-label-sm">
<span>HYDRATATION OPTIMALE</span>
<span class="text-primary font-semibold">2.8 L / jour</span>
</div>
<div class="w-full bg-surface-container-highest h-2 rounded-full overflow-hidden mt-1">
<div class="bg-primary-container h-full w-[65%] rounded-full shadow-[0_0_8px_rgba(27,119,186,0.4)]"></div>
</div>
<span class="font-body-sm text-body-sm text-on-surface-variant mt-1">Éviter eau glacée (&lt;10°C) qui coupe la transpiration.</span>
</div>
<div class="bg-surface-container p-3 rounded-lg flex flex-col gap-1">
<div class="flex items-center justify-between text-on-surface-variant font-label-sm text-label-sm">
<span>BRUMISATION ACTIVE</span>
<span class="text-secondary font-semibold">Effet -3°C</span>
</div>
<div class="w-full bg-surface-container-highest h-2 rounded-full overflow-hidden mt-1">
<div class="bg-secondary-container h-full w-[85%] rounded-full"></div>
</div>
<span class="font-body-sm text-body-sm text-on-surface-variant mt-1">Appliquer sur avant-bras, nuque et tempes.</span>
</div>
<div class="bg-surface-container p-3 rounded-lg flex flex-col gap-1">
<div class="flex items-center justify-between text-on-surface-variant font-label-sm text-label-sm">
<span>VENTILATION PASSIVE</span>
<span class="text-tertiary-fixed font-semibold">Après 22h00</span>
</div>
<div class="w-full bg-surface-container-highest h-2 rounded-full overflow-hidden mt-1">
<div class="bg-tertiary-container h-full w-[40%] rounded-full"></div>
</div>
<span class="font-body-sm text-body-sm text-on-surface-variant mt-1">Créer un courant d'air traversant dès inversion nocturne.</span>
</div>
</div>
</div>
<div class="mt-space-md pt-space-sm border-t border-transparent bg-surface-container/60 p-3 rounded-lg flex flex-col sm:flex-row sm:items-center justify-between gap-2">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-error text-[20px]">warning</span>
<span class="font-label-md text-label-md text-on-surface font-semibold">Signes d'alerte rouge :</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Absence de sueur, propos incohérents, peau brûlante, vertiges.</span>
</div>
<a class="inline-flex items-center gap-1 text-error font-label-md text-label-md hover:underline shrink-0" href="tel:190">
Appeler le 190 immédiatement →
</a>
</div>
</div>
<div class="md:col-span-4 bg-surface-container rounded-xl overflow-hidden shadow-md flex flex-col">
<div class="relative w-full h-52 bg-cover bg-center" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuACYCmFhpxtTryvEUBxElcI4ZFHI6oBg5Kh_ipUh7ozV3QxNyp7gUEIxv5w8O1ix7bZAk1Y0hx8yNEeMUF8NAJgxlwlt17Y5_fCW1Mg4hMeu35mUi2GHKbsUgQ_vRQp-w90aSDYXbXzYEgkqULE7Nyd77ftR-nCEMRBJq705WXsbcnAMgB_IUXez9k-9hnS9ah9grgQ9opmYTy4FJ90sj4ow0E2I-Tz9i2vnLuNFdFND6-tHzsMmBOaWg')">
<div class="absolute inset-0 bg-gradient-to-t from-surface-container via-surface-container/30 to-transparent"></div>
<span class="absolute top-3 left-3 px-2 py-1 rounded bg-surface-container-lowest/80 backdrop-blur-md text-primary font-label-sm text-label-sm uppercase">
Îlot Fraîcheur Municipal
</span>
</div>
<div class="p-space-md flex flex-col flex-1 justify-between gap-space-sm">
<div>
<h4 class="font-title-md text-title-md text-on-surface">Bibliothèque &amp; Crypte Saint-Cyprien</h4>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
Espace climatisé autonome alimenté par groupe électrogène communal. Ouvert 24h/24 pendant le niveau 3.
</p>
</div>
<div class="flex items-center justify-between text-body-sm font-body-sm pt-2">
<span class="text-on-surface-variant flex items-center gap-1">
<span class="material-symbols-outlined text-[16px] text-primary">directions_walk</span> 350m (4 min)
</span>
<span class="px-2 py-0.5 rounded bg-surface-container-high text-primary font-label-sm text-label-sm">
42 places dispo
</span>
</div>
</div>
</div>
<div class="md:col-span-4 bg-surface-container-low rounded-xl p-space-md shadow-md flex flex-col gap-space-sm">
<div class="flex items-center gap-2">
<span class="p-2 rounded-lg bg-surface-container text-tertiary-container">
<span class="material-symbols-outlined text-[20px]">kitchen</span>
</span>
<div>
<h4 class="font-title-md text-title-md text-on-surface">Frigo &amp; Congélateur</h4>
<span class="font-label-sm text-label-sm text-on-surface-variant">Règles d'or en coupure réseau</span>
</div>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">
Ne pas ouvrir les portes pendant le délestage. Un congélateur plein reste à température sécuritaire pendant <strong>48 heures</strong> s'il n'est jamais entrouvert.
</p>
<div class="space-y-2 mt-1">
<div class="flex items-start gap-2 p-2 rounded bg-surface-container">
<span class="material-symbols-outlined text-[16px] text-tertiary-container mt-0.5">lock_clock</span>
<div class="font-body-sm text-body-sm">
<span class="text-on-surface font-medium">Réfrigérateur fermé :</span>
<span class="text-on-surface-variant"> Garde le froid 4 à 6 heures max.</span>
</div>
</div>
<div class="flex items-start gap-2 p-2 rounded bg-surface-container">
<span class="material-symbols-outlined text-[16px] text-error mt-0.5">delete_forever</span>
<div class="font-body-sm text-body-sm">
<span class="text-on-surface font-medium">Zone de danger :</span>
<span class="text-on-surface-variant"> Jetez viandes, poissons, produits laitiers après 2h au-delà de 8°C.</span>
</div>
</div>
</div>
<div class="mt-auto pt-2">
<a href="#faq-conseils" class="w-full py-2 px-3 rounded-lg bg-surface-container-high hover:bg-surface-variant text-on-surface text-label-md font-label-md flex items-center justify-center gap-1 transition-colors">
<span class="material-symbols-outlined text-[16px]">fact_check</span>
Guide complet du tri sanitaire
</a>
</div>
</div>
<div class="md:col-span-4 bg-surface-container-low rounded-xl p-space-md shadow-md flex flex-col gap-space-sm relative">
<div class="flex items-center gap-2">
<span class="p-2 rounded-lg bg-surface-container text-primary">
<span class="material-symbols-outlined text-[20px]">medication_liquid</span>
</span>
<div>
<h4 class="font-title-md text-title-md text-on-surface">Chaîne Médicale</h4>
<span class="font-label-sm text-label-sm text-on-surface-variant">Insuline, vaccins, collyres</span>
</div>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">
Les flacons entamés supportent jusqu'à 30°C pendant 28 jours. Pour le stock non ouvert, maintenir impérativement entre <strong>2°C et 8°C</strong>.
</p>
<div class="bg-surface-container p-3 rounded-lg flex flex-col gap-2">
<div class="flex justify-between items-center text-label-sm font-label-sm">
<span class="text-on-surface">Glacière passive certifiée :</span>
<span class="text-primary font-semibold">12h d'autonomie</span>
</div>
<div class="w-full bg-surface-container-highest h-1.5 rounded-full overflow-hidden">
<div class="bg-primary h-full w-[70%] rounded-full"></div>
</div>
<span class="text-body-sm font-body-sm text-on-surface-variant">
Utiliser des blocs eutectiques sans contact direct avec les flacons (risque de congélation irréversible).
</span>
</div>
<div class="mt-auto pt-2">
<a href="tel:0800066666" class="w-full py-2 px-3 rounded-lg bg-primary/10 hover:bg-primary/20 text-primary text-label-md font-label-md flex items-center justify-center gap-1 transition-colors">
<span class="material-symbols-outlined text-[16px]">local_pharmacy</span>
Pharmacies avec générateurs sécurisés
</a>
</div>
</div>
<div class="md:col-span-4 bg-surface-container-low rounded-xl p-space-md shadow-md flex flex-col gap-space-sm">
<div class="flex items-center gap-2">
<span class="p-2 rounded-lg bg-surface-container text-secondary">
<span class="material-symbols-outlined text-[20px]">pets</span>
</span>
<div>
<h4 class="font-title-md text-title-md text-on-surface">Animaux &amp; Seniors</h4>
<span class="font-label-sm text-label-sm text-on-surface-variant">Protocoles d'apaisement</span>
</div>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant">
Les chiens et chats ne régulent leur température que par le halètement et les coussinets. Les trottoirs à 55°C provoquent des brûlures au second degré en moins de 60 secondes.
</p>
<ul class="space-y-1.5 font-body-sm text-body-sm text-on-surface-variant">
<li class="flex items-center gap-2">
<span class="material-symbols-outlined text-secondary text-[16px]">check</span>
Test des 7 secondes au dos de la main sur l'asphalte
</li>
<li class="flex items-center gap-2">
<span class="material-symbols-outlined text-secondary text-[16px]">check</span>
Serviettes humides posées sur le carrelage
</li>
<li class="flex items-center gap-2">
<span class="material-symbols-outlined text-secondary text-[16px]">check</span>
Coupelles d'eau fraîche changées toutes les 3h
</li>
</ul>
<div class="mt-auto pt-2">
<a href="tel:190" class="w-full py-2 px-3 rounded-lg bg-surface-container-high hover:bg-surface-variant text-on-surface text-label-md font-label-md flex items-center justify-center gap-1 transition-colors">
<span class="material-symbols-outlined text-[16px]">health_and_safety</span>
Fiche vétérinaire d'urgence
</a>
</div>
</div>
</div>
</div>

<!-- Tab Panel 2: Sante / Medicaments -->
<div class="tab-content hidden flex-col gap-space-lg" id="tab-sante" role="tabpanel" aria-labelledby="tabbtn-sante" tabindex="0">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-space-md">
<div class="lg:col-span-7 bg-surface-container-low p-space-lg rounded-xl flex flex-col gap-space-md shadow-md">
<div class="flex items-center justify-between">
<h3 class="font-headline-sm text-headline-sm text-on-surface">
Protocole 'Coupure Froide' pour Traitements Chroniques
</h3>
<span class="px-2.5 py-1 rounded bg-primary-container/20 text-primary-fixed-dim font-label-sm text-label-sm">
Ordre National des Pharmaciens
</span>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">
En cas de coupure supérieure à 4 heures, ne tentez pas de déplacer vos médicaments dans la voiture ou chez un voisin non prévenu. Suivez les étapes de stabilisation thermique :
</p>
<div class="space-y-3">
<div class="p-3 rounded-lg bg-surface-container flex gap-3">
<div class="w-8 h-8 rounded-full bg-surface-container-highest text-primary font-title-md flex items-center justify-center shrink-0">1</div>
<div class="flex flex-col">
<span class="text-on-surface font-title-md text-title-md">Sceller le bac à légumes</span>
<span class="text-on-surface-variant font-body-sm text-body-sm">C'est la zone la plus tempérée et stable du frigo. Enveloppez les boîtes dans un linge épais pour amortir les amplitudes thermiques.</span>
</div>
</div>
<div class="p-3 rounded-lg bg-surface-container flex gap-3">
<div class="w-8 h-8 rounded-full bg-surface-container-highest text-primary font-title-md flex items-center justify-center shrink-0">2</div>
<div class="flex flex-col">
<span class="text-on-surface font-title-md text-title-md">Pas de pain de glace au contact</span>
<span class="text-on-surface-variant font-body-sm text-body-sm">Un médicament congelé accidentellement (insuline, hormones de croissance) perd irréversiblement son efficacité et devient toxique.</span>
</div>
</div>
<div class="p-3 rounded-lg bg-surface-container flex gap-3">
<div class="w-8 h-8 rounded-full bg-surface-container-highest text-primary font-title-md flex items-center justify-center shrink-0">3</div>
<div class="flex flex-col">
<span class="text-on-surface font-title-md text-title-md">Rejoindre une officine relais sécurisée</span>
<span class="text-on-surface-variant font-body-sm text-body-sm">Les 4 officines partenaires du quartier disposent d'un compartiment réfrigéré de secours sur onduleur médical.</span>
</div>
</div>
</div>
</div>
<div class="lg:col-span-5 flex flex-col gap-space-md">
<div class="bg-surface-container-low p-space-md rounded-xl shadow-md">
<h4 class="font-title-md text-title-md text-on-surface mb-2">Officines Équipées d'Onduleurs Relais</h4>
<div class="space-y-2">
<div class="p-2.5 rounded-lg bg-surface-container flex items-center justify-between">
<div>
<div class="text-on-surface font-label-md text-label-md">Pharmacie du Marché Central</div>
<div class="text-on-surface-variant text-body-sm font-body-sm">12 Rue Gambetta • 180m</div>
</div>
<span class="px-2 py-1 rounded bg-secondary-container/40 text-secondary-fixed text-label-sm font-label-sm">En Service</span>
</div>
<div class="p-2.5 rounded-lg bg-surface-container flex items-center justify-between">
<div>
<div class="text-on-surface font-label-md text-label-md">Pharmacie des Allées</div>
<div class="text-on-surface-variant text-body-sm font-body-sm">45 Boulevard Victor Hugo • 620m</div>
</div>
<span class="px-2 py-1 rounded bg-secondary-container/40 text-secondary-fixed text-label-sm font-label-sm">En Service</span>
</div>
<div class="p-2.5 rounded-lg bg-surface-container flex items-center justify-between">
<div>
<div class="text-on-surface font-label-md text-label-md">Pharmacie Saint-Roch</div>
<div class="text-on-surface-variant text-body-sm font-body-sm">3 Place des Carmes • 950m</div>
</div>
<span class="px-2 py-1 rounded bg-tertiary-container/30 text-tertiary-fixed text-label-sm font-label-sm">Froid Limité</span>
</div>
</div>
</div>
<div class="bg-surface-container p-space-md rounded-xl flex items-center gap-space-md">
<span class="material-symbols-outlined text-tertiary-container text-[36px]">contact_support</span>
<div>
<span class="font-title-md text-title-md text-on-surface block">Doute sur la couleur d'un liquide ?</span>
<span class="font-body-sm text-body-sm text-on-surface-variant">Ligne directe avec le centre antipoison : <a class="text-primary hover:underline" href="tel:0800066666">0800 06 66 66</a> • Urgence : <a class="text-error hover:underline" href="tel:190">190</a>.</span>
</div>
</div>
</div>
</div>
</div>

<!-- Tab Panel 3: Solidarite Voisins & Mutual Aid -->
<div class="tab-content hidden flex-col gap-space-lg" id="tab-solidarite" role="tabpanel" aria-labelledby="tabbtn-solidarite" tabindex="0">
<div class="grid grid-cols-1 lg:grid-cols-12 gap-space-md">
<div class="lg:col-span-8 flex flex-col gap-space-md">
<div class="bg-surface-container-low p-space-md rounded-xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-space-sm shadow-md">
<div class="flex items-center gap-space-sm">
<span class="p-2.5 rounded-xl bg-primary/10 text-primary">
<span class="material-symbols-outlined text-[24px]">volunteer_activism</span>
</span>
<div>
<h3 class="font-title-md text-title-md text-on-surface">Réseau Sentinelle &amp; Visites de Courtoisie</h3>
<p class="font-body-sm text-body-sm text-on-surface-variant">14 veilles actives aujourd'hui dans votre rayon de 500 mètres.</p>
</div>
</div>
<button class="px-4 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md hover:shadow-[0_0_20px_rgba(27,119,186,0.4)] transition-all shrink-0" id="openAideModal" type="button" aria-haspopup="dialog" aria-controls="aideModal">
+ Proposer mon aide / équipement
</button>
</div>
<div class="flex flex-col gap-space-sm">
<div class="bg-surface-container p-space-md rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-space-md hover:bg-surface-container-high transition-colors">
<div class="flex items-start gap-space-sm">
<div class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface-variant shrink-0 font-title-md">MD</div>
<div class="flex flex-col">
<div class="flex items-center gap-2 flex-wrap">
<span class="font-title-md text-title-md text-on-surface">Mme Dupont</span>
<span class="px-2 py-0.5 rounded bg-surface-container-highest text-on-surface-variant font-label-sm text-label-sm">Résidence Les Tilleuls • Étage 4</span>
<span class="px-2 py-0.5 rounded bg-surface-container-low text-primary font-label-sm text-label-sm flex items-center gap-1">
<span class="w-1.5 h-1.5 rounded-full bg-primary"></span> Visite effectuée ce matin (09:40)
</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
Brumisateur rechargé, ventilateur sur batterie installé par Lucas (voisin du 3ème). Température salon à 27.5°C. Prochaine visite suggérée : 17h00.
</p>
</div>
</div>
<div class="flex items-center gap-2 shrink-0 self-end md:self-center">
<button class="js-open-aide px-3 py-1.5 rounded-lg bg-surface-container-highest text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors" type="button">
Prendre le relais 17h
</button>
</div>
</div>
<div class="bg-surface-container p-space-md rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-space-md border-l-4 border-tertiary-container shadow-[0_0_24px_-4px_rgba(255,199,105,0.15)]">
<div class="flex items-start gap-space-sm">
<div class="w-10 h-10 rounded-full bg-tertiary-container/20 text-tertiary-fixed flex items-center justify-center shrink-0 font-title-md">MR</div>
<div class="flex flex-col">
<div class="flex items-center gap-2 flex-wrap">
<span class="font-title-md text-title-md text-on-surface">M. Robert (82 ans)</span>
<span class="px-2 py-0.5 rounded bg-surface-container-highest text-on-surface-variant font-label-sm text-label-sm">18 Rue Voltaire • Étage 2 (sans ascenseur)</span>
<span class="px-2 py-0.5 rounded bg-tertiary-container text-on-tertiary-container font-label-sm text-label-sm">
Besoin : 2 packs eau fraîche + pain de glace
</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
Son petit réfrigérateur a disjoncté. Mobilité réduite. Recherche un voisin pouvant apporter des blocs congelés ou une glacière de maintien.
</p>
</div>
</div>
<div class="flex items-center gap-2 shrink-0 self-end md:self-center">
<button class="js-open-aide px-4 py-1.5 rounded-lg bg-tertiary-container text-on-tertiary-container font-label-md text-label-md hover:bg-tertiary-fixed transition-colors" type="button">
J'apporte la glace
</button>
</div>
</div>
<div class="bg-surface-container p-space-md rounded-xl flex flex-col md:flex-row items-start md:items-center justify-between gap-space-md hover:bg-surface-container-high transition-colors">
<div class="flex items-start gap-space-sm">
<div class="w-10 h-10 rounded-full bg-surface-container-highest flex items-center justify-center text-on-surface-variant shrink-0 font-title-md">SK</div>
<div class="flex flex-col">
<div class="flex items-center gap-2 flex-wrap">
<span class="font-title-md text-title-md text-on-surface">Famille Khelif (Nourrisson 4 mois)</span>
<span class="px-2 py-0.5 rounded bg-surface-container-highest text-on-surface-variant font-label-sm text-label-sm">2 Boulevard Sud • Combles sous toit</span>
<span class="px-2 py-0.5 rounded bg-surface-container-low text-secondary font-label-sm text-label-sm">
Recherche pièce fraîche l'après-midi
</span>
</div>
<p class="font-body-sm text-body-sm text-on-surface-variant mt-1">
Température sous combles mesurée à 36°C à midi. Sollicitent 2h d'accueil chez un voisin disposant d'un rez-de-chaussée ou sous-sol frais.
</p>
</div>
</div>
<div class="flex items-center gap-2 shrink-0 self-end md:self-center">
<button class="js-open-aide px-3 py-1.5 rounded-lg bg-surface-container-highest text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors" type="button">
Proposer mon salon
</button>
</div>
</div>
</div>
</div>
<div class="lg:col-span-4 flex flex-col gap-space-md">
<div class="bg-surface-container-low p-space-md rounded-xl shadow-md flex flex-col gap-space-sm">
<div class="flex items-center justify-between">
<h4 class="font-title-md text-title-md text-on-surface">Matériel Mutualisé</h4>
<span class="font-label-sm text-label-sm text-primary">6 équipements</span>
</div>
<div class="space-y-2">
<div class="p-2.5 rounded-lg bg-surface-container flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-primary">battery_charging_full</span>
<div>
<div class="text-on-surface font-label-md text-label-md">Station Électrique Anker 1000W</div>
<div class="text-on-surface-variant text-body-sm font-body-sm">Dispo chez Marc (Bât B)</div>
</div>
</div>
<span class="px-2 py-0.5 rounded bg-surface-container-high text-primary font-label-sm text-label-sm">Dispo</span>
</div>
<div class="p-2.5 rounded-lg bg-surface-container flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-tertiary-container">ac_unit</span>
<div>
<div class="text-on-surface font-label-md text-label-md">2 Glacières Rigides 40L</div>
<div class="text-on-surface-variant text-body-sm font-body-sm">Dispo chez Sarah (Place)</div>
</div>
</div>
<span class="px-2 py-0.5 rounded bg-surface-container-high text-primary font-label-sm text-label-sm">Dispo</span>
</div>
<div class="p-2.5 rounded-lg bg-surface-container flex items-center justify-between">
<div class="flex items-center gap-2">
<span class="material-symbols-outlined text-[18px] text-secondary">mode_fan</span>
<div>
<div class="text-on-surface font-label-md text-label-md">3 Ventilateurs Brumisateurs USB</div>
<div class="text-on-surface-variant text-body-sm font-body-sm">Local gardien d'immeuble</div>
</div>
</div>
<span class="px-2 py-0.5 rounded bg-surface-container-high text-primary font-label-sm text-label-sm">Dispo</span>
</div>
</div>
</div>
<div class="bg-surface-container-low p-space-md rounded-xl shadow-md flex flex-col gap-space-sm">
<div class="flex items-center justify-between">
<span class="font-title-md text-title-md text-on-surface">Carte Micro-Secteur</span>
<span class="font-label-sm text-label-sm text-on-surface-variant">Rayon 800m</span>
</div>
<div class="w-full h-44 rounded-lg bg-surface-container bg-cover bg-center relative overflow-hidden flex items-end p-2.5" style="background-image: url('https://lh3.googleusercontent.com/aida-public/AB6AXuBHvz8PRtQCIKgJT22tvaROP56NlY37tHajryHhchYoFP71fis9Mq7W7sAPSIz469pAorktr150Bat9VhnBf4Qbnmq8LmN-0vQJHCKEw7TdL2DaxV1c1lH6uAtH4FVNRqwj1jRdQPBwvP_Xsr-p0NL9nzaEa3Q-K9_Un9niMeJPiWxvmyzAnW6kpkJNbsMnIYcSjOgIe8S5-cWEp5WM9iLOdjykz8zQ-mAhSFQaJ7WY3QskdgKbfOAOzQ')">
<div class="bg-surface-container-lowest/90 backdrop-blur-md px-2.5 py-1.5 rounded-md flex items-center gap-2 w-full">
<span class="w-2 h-2 rounded-full bg-primary-container animate-ping"></span>
<span class="font-body-sm text-body-sm text-on-surface truncate">3 points d'eau potable opérationnels</span>
</div>
</div>
</div>
</div>
</div>
</div>

<!-- Tab Panel 4: Coupure & Delestage -->
<div class="tab-content hidden flex-col gap-space-lg" id="tab-coupure" role="tabpanel" aria-labelledby="tabbtn-coupure" tabindex="0">
<div class="grid grid-cols-1 md:grid-cols-3 gap-space-md">
<div class="bg-surface-container-low p-space-md rounded-xl flex flex-col gap-space-sm shadow-md">
<div class="w-10 h-10 rounded-lg bg-tertiary-container/20 text-tertiary-fixed flex items-center justify-center">
<span class="material-symbols-outlined text-[24px]">flash_off</span>
</div>
<h4 class="font-title-md text-title-md text-on-surface">Pendant la Coupure</h4>
<ul class="space-y-2 font-body-sm text-body-sm text-on-surface-variant">
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Débrancher les appareils électroniques sensibles (risque de surtension au réenclenchement).</span>
</li>
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Laisser un seul interrupteur allumé pour identifier le retour du courant sans surcharge.</span>
</li>
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Ne jamais utiliser de bougies (risque majeur d'incendie en ambiance surchauffée).</span>
</li>
</ul>
</div>
<div class="bg-surface-container-low p-space-md rounded-xl flex flex-col gap-space-sm shadow-md">
<div class="w-10 h-10 rounded-lg bg-primary/20 text-primary flex items-center justify-center">
<span class="material-symbols-outlined text-[24px]">power</span>
</div>
<h4 class="font-title-md text-title-md text-on-surface">Au Rétablissement du Réseau</h4>
<ul class="space-y-2 font-body-sm text-body-sm text-on-surface-variant">
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Attendre 20 à 30 minutes avant de réenclencher climatiseurs ou chauffe-eau.</span>
</li>
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Vérifier la température de votre congélateur avec un thermomètre sonde.</span>
</li>
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Signaler tout dysfonctionnement résiduel d'éclairage public sur la plateforme.</span>
</li>
</ul>
</div>
<div class="bg-surface-container-low p-space-md rounded-xl flex flex-col gap-space-sm shadow-md">
<div class="w-10 h-10 rounded-lg bg-secondary/20 text-secondary flex items-center justify-center">
<span class="material-symbols-outlined text-[24px]">cell_tower</span>
</div>
<h4 class="font-title-md text-title-md text-on-surface">Réseau Télécom &amp; 4G</h4>
<ul class="space-y-2 font-body-sm text-body-sm text-on-surface-variant">
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Les antennes relais fonctionnent sur batteries de secours (autonomie 2h à 4h).</span>
</li>
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Privilégier impérativement les SMS aux appels vocaux et au streaming vidéo.</span>
</li>
<li class="flex items-start gap-2">
<span class="material-symbols-outlined text-primary text-[16px] shrink-0 mt-0.5">check</span>
<span>Activez le mode 'Économie Ultra de Batterie' sur vos smartphones.</span>
</li>
</ul>
</div>
</div>
</div>

<!-- Section FAQ & Questions Vitales -->
<div class="bg-surface-container-low p-space-lg rounded-xl shadow-md flex flex-col gap-space-md" id="faq-conseils">
<div class="flex flex-col md:flex-row md:items-center justify-between gap-2">
<div>
<span class="font-label-sm text-label-sm uppercase tracking-widest text-primary">Support &amp; Précisions</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface">Questions fréquentes lors d'un pic canicule • blackout</h3>
</div>
<span class="font-body-sm text-body-sm text-on-surface-variant">Réponses certifiées par la Cellule Municipale de Crise</span>
</div>
<div class="grid grid-cols-1 md:grid-cols-2 gap-space-md">
<div class="bg-surface-container p-4 rounded-lg flex flex-col gap-1">
<span class="font-title-md text-title-md text-on-surface">Mon ventilateur est-il utile si la pièce dépasse 35°C ?</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">
Non, s'il est utilisé à sec : il agit alors comme un four à chaleur tournante et accélère la déshydratation sans abaisser la température corporelle. Il devient efficace <strong>uniquement si vous mouillez votre peau</strong> ou suspendez un linge humide devant l'hélice.
</p>
</div>
<div class="bg-surface-container p-4 rounded-lg flex flex-col gap-1">
<span class="font-title-md text-title-md text-on-surface">Comment savoir si mon voisin âgé est en situation de détresse ?</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">
Les volets fermés en continu l'après-midi sont normaux, mais si les volets ne s'ouvrent pas la nuit ou à l'aube pour aérer, et en l'absence de réponse aux coups de sonnette répétés, déclenchez sans délai l'alerte municipale ou composez le <a class="text-error hover:underline" href="tel:190">190</a> / <a class="text-error hover:underline" href="tel:198">198</a>.
</p>
</div>
<div class="bg-surface-container p-4 rounded-lg flex flex-col gap-1">
<span class="font-title-md text-title-md text-on-surface">Où trouver de l'eau si le réseau d'immeuble est coupé par surpresseur ?</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">
En cas de panne d'électricité dans les tours résidentielles, les pompes d'eau peuvent s'arrêter. Les 6 bornes-fontaines au sol du réseau d'eau gravitaire de la ville restent fonctionnelles 24h/24 (notamment Place du Capitole et Quai de la Daurade).
</p>
</div>
<div class="bg-surface-container p-4 rounded-lg flex flex-col gap-1">
<span class="font-title-md text-title-md text-on-surface">Puis-je refuser le délestage de mon foyer ?</span>
<p class="font-body-sm text-body-sm text-on-surface-variant">
Les délestages tournants sont automatisés sur les postes sources haute-tension. Seules les personnes inscrites sur le registre préfectoral PHV (Patients à Haut Risque Vital) font l'objet d'une préservation ou d'une relocalisation prioritaire.
</p>
</div>
</div>
</div>

<!-- Banner Community Engagement -->
<div class="bg-surface-container-highest/60 rounded-xl p-space-lg flex flex-col md:flex-row items-center justify-between gap-space-md">
<div class="flex items-center gap-space-md">
<div class="w-14 h-14 rounded-2xl bg-primary-container text-on-primary-container flex items-center justify-center shrink-0 shadow-[0_0_24px_rgba(27,119,186,0.3)]">
<span class="material-symbols-outlined text-[32px]">shield_with_heart</span>
</div>
<div>
<h4 class="font-title-md text-title-md text-on-surface">Rejoindre la brigade citoyenne du quartier</h4>
<p class="font-body-md text-body-md text-on-surface-variant">Recevez un kit glacière thermos, pastilles électrolytiques et un talkie-walkie basse fréquence pour relayer les secours en cas de blackout total. Urgence : <a class="text-error hover:underline" href="tel:190">190</a> • <a class="text-primary hover:underline" href="tel:0800066666">0800 06 66 66</a>.</p>
</div>
</div>
<a href="{{ route('register') }}" class="px-5 py-2.5 rounded-lg bg-secondary text-on-secondary font-title-md text-title-md hover:bg-secondary-fixed transition-colors shrink-0">
Devenir Sentinelle Résilience
</a>
</div>
</div>

<!-- Modal : Déclencher une Alerte pour un Voisin qui ne répond pas -->
<div class="fixed inset-0 z-50 flex items-center justify-center bg-surface-dim/80 backdrop-blur-md hidden p-4" id="urgenceModal" role="dialog" aria-modal="true" aria-labelledby="urgenceModalTitle">
<div class="bg-surface-container-high rounded-xl max-w-lg w-full p-space-lg flex flex-col gap-space-md shadow-2xl relative">
<div class="flex items-center justify-between">
<div class="flex items-center gap-2 text-error">
<span class="material-symbols-outlined text-[28px]">notification_important</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface" id="urgenceModalTitle" tabindex="-1">Alerte Non-Réponse Voisin</h3>
</div>
<button class="p-1 rounded-full text-on-surface-variant hover:text-on-surface" id="closeUrgenceModal" type="button" aria-label="Fermer la fenêtre d'alerte voisin">
<span class="material-symbols-outlined text-[20px]">close</span>
</button>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">
Ce formulaire transmet instantanément une notification géolocalisée à la brigade municipale de proximité et aux voisins sentinelles situés à moins de 150m. En danger immédiat, appelez le <a class="text-error hover:underline" href="tel:190">190</a>.
</p>
<div class="space-y-3">
<div>
<label for="voisin-identite" class="block font-label-md text-label-md text-on-surface mb-1">Identité ou description du voisin</label>
<input id="voisin-identite" name="voisin-identite" class="w-full bg-surface-container px-3 py-2 rounded-lg text-on-surface placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Ex: M. Mercier, 78 ans" type="text" />
</div>
<div>
<label for="voisin-adresse" class="block font-label-md text-label-md text-on-surface mb-1">Adresse précise &amp; Appartement</label>
<input id="voisin-adresse" name="voisin-adresse" class="w-full bg-surface-container px-3 py-2 rounded-lg text-on-surface placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Ex: 14 Rue des Lilas, Bât A, 3e étage gauche" type="text" />
</div>
<div>
<label for="voisin-signes" class="block font-label-md text-label-md text-on-surface mb-1">Dernier contact &amp; Signes observés</label>
<textarea id="voisin-signes" name="voisin-signes" class="w-full bg-surface-container px-3 py-2 rounded-lg text-on-surface placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Volets clos depuis 36h, téléphone sonne dans le vide, pas de réponse aux frappes répétées..." rows="3"></textarea>
</div>
</div>
<div class="flex items-center justify-end gap-2 pt-2">
<button class="px-4 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors" id="cancelUrgenceModal" type="button">
Annuler
</button>
<button class="px-5 py-2 rounded-lg bg-error-container text-on-error-container font-label-md text-label-md hover:bg-red-500 hover:text-white transition-all shadow-[0_0_16px_rgba(255,180,171,0.2)]" id="submitUrgenceAlert" type="button">
Transmettre aux Secours
</button>
</div>
</div>
</div>

<!-- Modal : Proposer son aide ou du matériel -->
<div class="fixed inset-0 z-50 flex items-center justify-center bg-surface-dim/80 backdrop-blur-md hidden p-4" id="aideModal" role="dialog" aria-modal="true" aria-labelledby="aideModalTitle">
<div class="bg-surface-container-high rounded-xl max-w-lg w-full p-space-lg flex flex-col gap-space-md shadow-2xl relative">
<div class="flex items-center justify-between">
<div class="flex items-center gap-2 text-primary">
<span class="material-symbols-outlined text-[28px]">handshake</span>
<h3 class="font-headline-sm text-headline-sm text-on-surface" id="aideModalTitle" tabindex="-1">Proposer Entraide &amp; Équipement</h3>
</div>
<button class="p-1 rounded-full text-on-surface-variant hover:text-on-surface" id="closeAideModal" type="button" aria-label="Fermer la fenêtre d'entraide">
<span class="material-symbols-outlined text-[20px]">close</span>
</button>
</div>
<p class="font-body-md text-body-md text-on-surface-variant">
Mettez à disposition une ressource critique pour sécuriser les personnes vulnérables de votre cage d'escalier ou de votre rue.
</p>
<div class="space-y-3">
<div>
<label for="aide-type" class="block font-label-md text-label-md text-on-surface mb-1">Type de soutien offert</label>
<select id="aide-type" name="aide-type" class="w-full bg-surface-container px-3 py-2 rounded-lg text-on-surface focus:outline-none focus:ring-1 focus:ring-primary">
<option>Pièce climatisée / salon frais (accueil 1-2h)</option>
<option>Partage de groupe électrogène / batterie</option>
<option>Glacière ou packs de glace congelés</option>
<option>Portage de courses &amp; eau potable</option>
<option>Visite de veille et prise de nouvelles</option>
</select>
</div>
<div>
<label for="aide-coordonnees" class="block font-label-md text-label-md text-on-surface mb-1">Vos coordonnées &amp; Localisation</label>
<input id="aide-coordonnees" name="aide-coordonnees" class="w-full bg-surface-container px-3 py-2 rounded-lg text-on-surface placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Ex: Thomas • Résidence Flore, 2ème • 06 12 34 56 78" type="text" />
</div>
<div>
<label for="aide-creneaux" class="block font-label-md text-label-md text-on-surface mb-1">Créneaux de disponibilité</label>
<input id="aide-creneaux" name="aide-creneaux" class="w-full bg-surface-container px-3 py-2 rounded-lg text-on-surface placeholder:text-outline focus:outline-none focus:ring-1 focus:ring-primary" placeholder="Ex: Tout l'après-midi, joignable par SMS" type="text" />
</div>
</div>
<div class="flex items-center justify-end gap-2 pt-2">
<button class="px-4 py-2 rounded-lg bg-surface-container text-on-surface font-label-md text-label-md hover:bg-surface-variant transition-colors" id="cancelAideModal" type="button">
Fermer
</button>
<button class="px-5 py-2 rounded-lg bg-primary-container text-on-primary-container font-label-md text-label-md hover:shadow-[0_0_16px_rgba(27,119,186,0.3)] transition-all" id="submitAideAlert" type="button">
Publier sur le réseau local
</button>
</div>
</div>
</div>

<script>
  // Onglets accessibles (motif APG Tabs) : aria-selected + navigation clavier.
  const tabButtons = document.querySelectorAll('.tab-button');
  const tabContents = document.querySelectorAll('.tab-content');
  const selectTab = (btn, focus) => {
    const target = btn.getAttribute('data-tab');
    tabButtons.forEach(b => {
      const selected = b === btn;
      b.classList.remove('bg-primary-container', 'text-on-primary-container', 'shadow-[0_0_16px_rgba(27,119,186,0.25)]');
      b.classList.add('bg-surface-container', 'text-on-surface-variant');
      b.setAttribute('aria-selected', selected ? 'true' : 'false');
      b.tabIndex = selected ? 0 : -1;
    });
    btn.classList.add('bg-primary-container', 'text-on-primary-container', 'shadow-[0_0_16px_rgba(27,119,186,0.25)]');
    btn.classList.remove('bg-surface-container', 'text-on-surface-variant');
    tabContents.forEach(content => {
      if (content.id === 'tab-' + target) {
        content.classList.remove('hidden');
        content.classList.add('flex');
      } else {
        content.classList.add('hidden');
        content.classList.remove('flex');
      }
    });
    if (focus) btn.focus();
  };
  tabButtons.forEach((btn, i) => {
    btn.addEventListener('click', () => selectTab(btn, false));
    btn.addEventListener('keydown', (e) => {
      let next = null;
      if (e.key === 'ArrowRight') next = tabButtons[(i + 1) % tabButtons.length];
      else if (e.key === 'ArrowLeft') next = tabButtons[(i - 1 + tabButtons.length) % tabButtons.length];
      else if (e.key === 'Home') next = tabButtons[0];
      else if (e.key === 'End') next = tabButtons[tabButtons.length - 1];
      if (next) { e.preventDefault(); selectTab(next, true); }
    });
  });
  const urgenceModal = document.getElementById('urgenceModal');
  const toggleUrgenceBtn = document.getElementById('toggleUrgenceBtn');
  const closeUrgenceModal = document.getElementById('closeUrgenceModal');
  const cancelUrgenceModal = document.getElementById('cancelUrgenceModal');
  const submitUrgenceAlert = document.getElementById('submitUrgenceAlert');
  const openUrgence = () => { urgenceModal.classList.remove('hidden'); document.getElementById('urgenceModalTitle').focus(); };
  const closeUrgence = () => { urgenceModal.classList.add('hidden'); if (toggleUrgenceBtn) toggleUrgenceBtn.focus(); };
  if (toggleUrgenceBtn) toggleUrgenceBtn.addEventListener('click', openUrgence);
  if (closeUrgenceModal) closeUrgenceModal.addEventListener('click', closeUrgence);
  if (cancelUrgenceModal) cancelUrgenceModal.addEventListener('click', closeUrgence);
  if (submitUrgenceAlert) {
    submitUrgenceAlert.addEventListener('click', () => {
      submitUrgenceAlert.innerText = 'Signalement Transmis ✓';
      submitUrgenceAlert.classList.remove('bg-error-container');
      submitUrgenceAlert.classList.add('bg-secondary');
      setTimeout(() => {
        closeUrgence();
        submitUrgenceAlert.innerText = 'Transmettre aux Secours';
        submitUrgenceAlert.classList.add('bg-error-container');
        submitUrgenceAlert.classList.remove('bg-secondary');
      }, 1200);
    });
  }
  const aideModal = document.getElementById('aideModal');
  const openAideModal = document.getElementById('openAideModal');
  const closeAideModal = document.getElementById('closeAideModal');
  const cancelAideModal = document.getElementById('cancelAideModal');
  const submitAideAlert = document.getElementById('submitAideAlert');
  const openAide = () => { aideModal.classList.remove('hidden'); document.getElementById('aideModalTitle').focus(); };
  const closeAide = () => { aideModal.classList.add('hidden'); if (openAideModal) openAideModal.focus(); };
  if (openAideModal) openAideModal.addEventListener('click', openAide);
  document.querySelectorAll('.js-open-aide').forEach(b => b.addEventListener('click', openAide));
  if (closeAideModal) closeAideModal.addEventListener('click', closeAide);
  if (cancelAideModal) cancelAideModal.addEventListener('click', closeAide);
  // Échap ferme la modale ouverte et rend le focus (SC 2.1.2).
  document.addEventListener('keydown', (e) => {
    if (e.key !== 'Escape') return;
    if (urgenceModal && !urgenceModal.classList.contains('hidden')) closeUrgence();
    else if (aideModal && !aideModal.classList.contains('hidden')) closeAide();
  });
  if (submitAideAlert) {
    submitAideAlert.addEventListener('click', () => {
      submitAideAlert.innerText = 'Proposition En Ligne ✓';
      setTimeout(() => {
        aideModal.classList.add('hidden');
        submitAideAlert.innerText = 'Publier sur le réseau local';
      }, 1200);
    });
  }
</script>
</x-public-layout>
