<x-public-layout title="Accueil">
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Space+Grotesk:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet" />
<style>
    .font-display { font-family: 'Space Grotesk', sans-serif; }
    .font-mono { font-family: 'JetBrains Mono', monospace !important; }

    /* Palette de la landing. Les canaux RGB alimentent les couleurs Tailwind "ln-*"
       (déclarées dans layouts/stitch-head) ; les valeurs rgba servent aux effets de verre. */
    :root, html.dark {
        --ln-bg: 10 15 29;
        --ln-bg-alt: 13 21 39;
        --ln-bg-alt2: 12 19 34;
        --ln-bg-cta: 12 20 36;
        --ln-heading: 248 250 252;
        --ln-text: 241 245 249;
        --ln-body: 203 213 225;
        --ln-muted: 148 163 184;
        --ln-faint: 100 116 139;
        --ln-border: 30 41 59;
        --ln-border-grid: 51 65 85;
        --ln-accent: 56 189 248;
        --ln-accent-strong: 186 230 253;
        --ln-warn: 253 186 116;
        --ln-chip: 30 41 59;
        --ln-glass-hover: 30 41 59;
        --ln-row-hover: 15 23 42;
        --ln-hero: 10 15 29;
        --ln-glass-bg: rgba(15, 23, 42, 0.68);
        --ln-glass-card-bg: rgba(15, 23, 42, 0.6);
        --ln-glass-border: rgba(148, 163, 184, 0.12);
        --ln-glass-shadow: 0 10px 32px -4px rgba(2, 6, 23, 0.6), inset 0 1px 1px rgba(255, 255, 255, 0.08);
        --ln-glass-card-shadow: 0 8px 24px -4px rgba(2, 6, 23, 0.5);
        --ln-scroll-track: #0a0f1d;
        --ln-scroll-thumb: #334155;
        --ln-scroll-thumb-hover: #475569;
    }
    /* Thème clair : base « papier bleuté » plutôt que du blanc pur, pour que les
       cartes blanches et les liserés ressortent au lieu de tout se confondre. */
    html.light {
        --ln-bg: 246 248 251;
        --ln-bg-alt: 240 244 250;
        --ln-bg-alt2: 235 240 247;
        --ln-bg-cta: 237 242 249;
        --ln-heading: 12 20 33;
        --ln-text: 23 33 48;
        --ln-body: 51 65 85;
        --ln-muted: 88 104 126;
        --ln-faint: 130 146 168;
        --ln-border: 219 227 238;
        --ln-border-grid: 207 217 231;
        --ln-accent: 2 132 199;
        --ln-accent-strong: 3 105 161;
        --ln-warn: 180 83 9;
        --ln-chip: 255 255 255;
        --ln-glass-hover: 240 245 251;
        --ln-row-hover: 255 255 255;
        --ln-hero: 10 15 29;
        --ln-glass-bg: rgba(255, 255, 255, 0.82);
        --ln-glass-card-bg: rgba(255, 255, 255, 0.92);
        --ln-glass-border: rgba(15, 23, 42, 0.07);
        --ln-glass-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 12px 32px -12px rgba(15, 23, 42, 0.18);
        --ln-glass-card-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 8px 24px -12px rgba(15, 23, 42, 0.16);
        --ln-scroll-track: #f6f8fb;
        --ln-scroll-thumb: #c7d2e0;
        --ln-scroll-thumb-hover: #9fb0c6;
    }
    /* Le hero reste une zone sombre dans les deux thèmes (la vidéo y est très claire par
       endroits) : en clair on allège le scrim au lieu de le supprimer, sinon le titre et
       le sous-titre blancs deviennent illisibles par-dessus la vidéo. */
    html.light .chillnet-hero-overlay {
        background: linear-gradient(to bottom,
            rgba(10, 15, 29, 0.42) 0%,
            rgba(10, 15, 29, 0.30) 40%,
            rgba(10, 15, 29, 0.68) 100%);
    }
    /* Panneaux posés sur la page (hero vidéo, bande de statistiques) : même arrondi et même
       liseré, pour qu'ils se lisent comme des blocs détachés du fond. */
    .ln-panel {
        border-radius: 1rem;
        border: 1px solid rgb(var(--ln-border));
    }
    html.light .ln-panel {
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 14px 32px -20px rgba(15, 23, 42, 0.32);
    }
    /* Le panneau vidéo est sombre : il supporte une ombre un peu plus marquée en clair. */
    html.light #accueil { box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05), 0 24px 56px -28px rgba(15, 23, 42, 0.5); }

    .liquid-glass {
        background: var(--ln-glass-bg);
        backdrop-filter: blur(16px);
        -webkit-backdrop-filter: blur(16px);
        border: 1px solid var(--ln-glass-border);
        box-shadow: var(--ln-glass-shadow);
        position: relative;
    }
    .liquid-glass-card {
        background: var(--ln-glass-card-bg);
        backdrop-filter: blur(12px);
        -webkit-backdrop-filter: blur(12px);
        border: 1px solid var(--ln-glass-border);
        box-shadow: var(--ln-glass-card-shadow);
    }
    .liquid-glass-card:hover {
        border-color: rgb(var(--ln-accent));
        box-shadow: 0 12px 36px -4px rgba(56, 189, 248, 0.08);
    }
    html.light .liquid-glass-card:hover {
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04), 0 16px 40px -12px rgba(2, 132, 199, 0.30);
    }
    .hero-heading-char {
        display: inline-block;
        opacity: 0;
        transform: translateX(-18px);
        transition: opacity 500ms cubic-bezier(0.16, 1, 0.3, 1), transform 500ms cubic-bezier(0.16, 1, 0.3, 1);
        will-change: opacity, transform;
    }
    .hero-heading-char.revealed { opacity: 1; transform: translateX(0); }
    .fade-in-element {
        opacity: 0;
        transition-property: opacity, transform;
        transition-timing-function: cubic-bezier(0.16, 1, 0.3, 1);
        will-change: opacity, transform;
    }
    .fade-in-element.revealed { opacity: 1; transform: translateY(0); }
    .chillnet-bleed ::-webkit-scrollbar { width: 6px; }
    .chillnet-bleed ::-webkit-scrollbar-track { background: var(--ln-scroll-track); }
    .chillnet-bleed ::-webkit-scrollbar-thumb { background: var(--ln-scroll-thumb); border-radius: 3px; }
    .chillnet-bleed ::-webkit-scrollbar-thumb:hover { background: var(--ln-scroll-thumb-hover); }
</style>

@php
    $quartiers = $quartiers ?? collect();
    $pointsFraicheur = $pointsFraicheur ?? collect();
    $nbQuartiers = $quartiers->count();
    $nbRefuges = $pointsFraicheur->count();
    $totalLogements = $pointsFraicheur->sum('nombre_logements');
    $nbClimatisees = $pointsFraicheur->where('salle_climatisee', true)->count();
    $lienConseils = \Illuminate\Support\Facades\Route::has('conseils') ? route('conseils') : route('home') . '#mission';
@endphp

<div class="chillnet-bleed -mx-4 md:-mx-10 mt-2 bg-ln-bg text-ln-text selection:bg-sky-500 selection:text-slate-950 rounded-2xl overflow-hidden">

    {{-- ==================== HERO ====================
         Panneau vidéo arrondi qui tient entièrement dans le premier écran : la barre de
         navigation reste dégagée (28px de respiration) et le bloc sous la vidéo n'est plus
         coupé par le pli de la page. --}}
    <section id="accueil" class="ln-panel relative w-full h-[calc(100svh_-_8rem)] min-h-[560px] overflow-hidden flex flex-col justify-end scroll-mt-28">
        <video autoplay loop muted playsinline preload="auto" aria-hidden="true" disablepictureinpicture class="absolute inset-0 w-full h-full object-cover object-center z-0">
            <source src="https://d8j0ntlcm91z4.cloudfront.net/user_38xzZboKViGWJOttwIXH07lWA1P/hf_20260403_050628_c4e32401-fab4-4a27-b7a8-6e9291cd5959.mp4" type="video/mp4" />
            Votre navigateur ne supporte pas la vidéo.
        </video>
        <div class="chillnet-hero-overlay absolute inset-0 bg-gradient-to-b from-ln-hero/70 via-ln-hero/40 to-ln-hero pointer-events-none z-[1]"></div>

        <div class="relative z-10 w-full px-6 md:px-12 lg:px-16 pb-12 lg:pb-16 flex-1 flex flex-col justify-end">
            <div class="max-w-3xl">
                <span class="inline-flex w-fit items-center gap-2 rounded-full bg-black/40 px-3 py-1 mb-5 text-xs font-semibold uppercase tracking-widest text-sky-300 backdrop-blur font-mono">
                    <span class="relative flex h-2 w-2"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-sky-400 opacity-75"></span><span class="relative inline-flex h-2 w-2 rounded-full bg-sky-400"></span></span>
                    ChillNet · Vigilance canicule
                </span>
                <h1 id="animated-hero-heading" class="font-display text-4xl md:text-5xl lg:text-6xl xl:text-7xl font-normal mb-4 text-[#f8fafc] leading-[1.08] drop-shadow-[0_2px_12px_rgba(0,0,0,0.7)]" style="letter-spacing: -0.04em;"></h1>
                <p id="hero-subheading" class="fade-in-element text-base md:text-lg text-slate-300 mb-6 max-w-xl font-normal leading-relaxed drop-shadow-[0_2px_8px_rgba(0,0,0,0.8)]">
                    Alertes canicule, refuges climatisés et entraide de quartier : restez informés, trouvez de la fraîcheur près de chez vous et veillez sur vos voisins.
                </p>
                <div id="hero-buttons" class="fade-in-element flex flex-wrap items-center gap-4">
                    <a href="#refuges" class="bg-sky-500 text-slate-950 px-8 py-3 rounded-lg font-semibold hover:bg-ln-accent transition-all duration-200 shadow-lg shadow-sky-500/20 active:scale-95 text-center text-sm">
                        Trouver un refuge
                    </a>
                    @guest
                        <a href="{{ route('register') }}" class="liquid-glass border border-ln-border text-ln-text px-8 py-3 rounded-lg font-medium hover:bg-ln-glass-hover hover:text-ln-heading hover:border-ln-accent transition-all duration-300 active:scale-95 text-center text-sm">
                            Rejoindre
                        </a>
                    @else
                        <a href="{{ route('dashboard') }}" class="liquid-glass border border-ln-border text-ln-text px-8 py-3 rounded-lg font-medium hover:bg-ln-glass-hover hover:text-ln-heading hover:border-ln-accent transition-all duration-300 active:scale-95 text-center text-sm">
                            Mon espace
                        </a>
                    @endauth
                </div>
            </div>
            <div class="mt-8 flex items-start justify-start lg:justify-end">
                <div id="hero-tag" class="fade-in-element liquid-glass border border-ln-accent px-6 py-3.5 rounded-xl shadow-xl backdrop-blur-md">
                    <span class="font-display text-lg md:text-xl lg:text-2xl font-normal tracking-tight text-ln-text">
                        Alertes. Refuges. Entraide.
                    </span>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== STATS STRIP (DONNÉES RÉELLES) ==================== --}}
    <section class="ln-panel mt-7 bg-ln-bg-alt py-12 px-6 md:px-12 lg:px-16">
        <div class="max-w-7xl mx-auto grid grid-cols-2 md:grid-cols-4 gap-8 md:gap-12">
            <div>
                <p class="font-display text-3xl lg:text-4xl font-semibold tracking-tight text-ln-heading mb-1">{{ $nbQuartiers }}</p>
                <p class="text-sm font-medium text-ln-muted">Quartiers couverts</p>
            </div>
            <div>
                <p class="font-display text-3xl lg:text-4xl font-semibold tracking-tight text-ln-heading mb-1">{{ $nbRefuges }}</p>
                <p class="text-sm font-medium text-ln-muted">Points de fraîcheur</p>
            </div>
            <div>
                <p class="font-display text-3xl lg:text-4xl font-semibold tracking-tight text-ln-heading mb-1">{{ number_format($totalLogements, 0, ',', ' ') }}</p>
                <p class="text-sm font-medium text-ln-muted">Logements concernés</p>
            </div>
            <div>
                <p class="font-display text-3xl lg:text-4xl font-semibold tracking-tight text-ln-heading mb-1">{{ $nbClimatisees }}</p>
                <p class="text-sm font-medium text-ln-muted">Salles climatisées</p>
            </div>
        </div>
    </section>

    {{-- ==================== ALERTE DU MOMENT (connectés uniquement) ==================== --}}
    @auth
    <section id="alertes" class="ln-panel mt-7 bg-ln-bg-alt px-6 md:px-12 lg:px-16 py-10 scroll-mt-28">
        <div class="max-w-7xl mx-auto flex flex-col md:flex-row md:items-center gap-4 justify-between rounded-2xl border border-orange-400/25 bg-orange-500/5 px-6 py-5">
            <div class="flex items-start gap-4">
                <span class="relative flex h-3 w-3 mt-1.5 shrink-0"><span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-orange-400 opacity-75"></span><span class="relative inline-flex h-3 w-3 rounded-full bg-orange-400"></span></span>
                <div>
                    <p class="text-xs font-mono uppercase tracking-widest text-ln-warn font-semibold mb-1">Vigilance orange canicule</p>
                    <p class="text-sm text-ln-body font-normal">Pic de chaleur attendu à 16h30. Restez au frais, hydratez-vous et prenez des nouvelles de vos voisins.</p>
                </div>
            </div>
            <a href="{{ route('alertes.index') }}" class="shrink-0 text-sm font-mono text-ln-accent hover:text-ln-accent-strong transition-colors">Toutes les alertes →</a>
        </div>
    </section>
    @endauth

    {{-- ==================== MISSION ==================== --}}
    <section id="mission" class="py-24 lg:py-32 px-6 md:px-12 lg:px-16 bg-ln-bg scroll-mt-28">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center gap-3 mb-6">
                <span class="w-2 h-2 rounded-full bg-ln-accent shadow-[0_0_8px_rgba(56,189,248,0.6)]"></span>
                <span class="text-xs uppercase tracking-widest text-ln-accent font-semibold font-mono">Notre mission</span>
            </div>
            <div class="grid lg:grid-cols-12 gap-12 lg:gap-16 items-start">
                <div class="lg:col-span-7">
                    <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-normal tracking-tight text-ln-heading leading-tight mb-8" style="letter-spacing: -0.03em;">
                        Face à la canicule, aucun voisin ne doit rester seul face à la chaleur.
                    </h2>
                    <p class="text-base lg:text-lg text-ln-muted leading-relaxed font-normal mb-6">
                        ChillNet relie les habitants, les résidences et les quartiers : nous diffusons des alertes claires, nous cartographions les refuges climatisés proches de chez vous et nous organisons l'entraide entre voisins, matin et soir pendant les pics de chaleur.
                    </p>
                    <p class="text-base lg:text-lg text-ln-muted leading-relaxed font-normal">
                        Une plateforme citoyenne simple : repérez un point de fraîcheur, suivez les conseils essentiels et rejoignez votre quartier pour veiller sur les plus fragiles.
                    </p>
                    <div class="mt-8 flex flex-wrap gap-4">
                        <a href="{{ $lienConseils }}" class="text-sm font-mono text-ln-accent hover:text-ln-accent-strong transition-colors">Voir les conseils fraîcheur →</a>
                        <a href="{{ route('home') }}#quartiers" class="text-sm font-mono text-ln-muted hover:text-ln-accent transition-colors">Explorer les quartiers →</a>
                    </div>
                </div>
                <div class="lg:col-span-5 flex flex-col gap-6">
                    <div class="liquid-glass-card p-8 rounded-2xl transition-all duration-300">
                        <h3 class="font-display text-xl font-medium text-ln-text mb-3">Vigilance locale</h3>
                        <p class="text-sm text-ln-muted leading-relaxed font-normal">
                            Des alertes et des repères concrets à l'échelle de votre résidence et de votre quartier, pour agir au bon moment.
                        </p>
                    </div>
                    <div class="liquid-glass-card p-8 rounded-2xl transition-all duration-300">
                        <h3 class="font-display text-xl font-medium text-ln-text mb-3">Solidarité de proximité</h3>
                        <p class="text-sm text-ln-muted leading-relaxed font-normal">
                            Un appel, une visite, une salle fraîche partagée : l'entraide de voisinage fait baisser le risque pour les plus vulnérables.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== 3 PILIERS ==================== --}}
    <section class="py-24 lg:py-32 px-6 md:px-12 lg:px-16 border-t border-ln-border-grid bg-ln-bg-alt2">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center justify-between mb-16 flex-wrap gap-4">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-2 h-2 rounded-full bg-ln-accent shadow-[0_0_8px_rgba(56,189,248,0.6)]"></span>
                        <span class="text-xs uppercase tracking-widest text-ln-accent font-semibold font-mono">Nos piliers</span>
                    </div>
                    <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-normal tracking-tight text-ln-heading" style="letter-spacing: -0.03em;">
                        Trois piliers. Un même objectif.
                    </h2>
                </div>
                <p class="text-ln-muted text-sm max-w-sm">
                    Rester au frais et rester ensemble : un dispositif simple pour anticiper chaque vague de chaleur.
                </p>
            </div>
            <div class="grid md:grid-cols-3 gap-6 lg:gap-8">
                <div class="liquid-glass-card p-8 lg:p-10 rounded-2xl flex flex-col justify-between group transition-all duration-300 min-h-[420px]">
                    <div>
                        <div class="flex items-center justify-between mb-8">
                            <span class="text-xs font-mono font-medium text-ln-accent tracking-wider">01 // ALERTES</span>
                            <span class="text-xl font-light text-ln-muted group-hover:text-ln-accent group-hover:translate-x-1 transition-all">→</span>
                        </div>
                        <h3 class="font-display text-2xl lg:text-3xl font-medium tracking-tight text-ln-heading mb-4">Alertes canicule</h3>
                        <p class="text-sm text-ln-muted font-normal leading-relaxed mb-6">
                            Soyez prévenus avant le pic : niveaux de vigilance, bons réflexes et numéros d'urgence (15, 112, 0800 06 66 66).
                        </p>
                    </div>
                    <ul class="space-y-2.5 text-xs text-ln-body font-mono pt-6 border-t border-ln-border">
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Vigilance par quartier</li>
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Réflexes hydratation</li>
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Numéros d'urgence</li>
                    </ul>
                </div>
                <div class="liquid-glass-card p-8 lg:p-10 rounded-2xl flex flex-col justify-between group transition-all duration-300 min-h-[420px]">
                    <div>
                        <div class="flex items-center justify-between mb-8">
                            <span class="text-xs font-mono font-medium text-ln-accent tracking-wider">02 // REFUGES</span>
                            <span class="text-xl font-light text-ln-muted group-hover:text-ln-accent group-hover:translate-x-1 transition-all">→</span>
                        </div>
                        <h3 class="font-display text-2xl lg:text-3xl font-medium tracking-tight text-ln-heading mb-4">Refuges climatisés</h3>
                        <p class="text-sm text-ln-muted font-normal leading-relaxed mb-6">
                            Repérez en un coup d'œil les points de fraîcheur et salles climatisées à moins de quelques minutes de chez vous.
                        </p>
                    </div>
                    <ul class="space-y-2.5 text-xs text-ln-body font-mono pt-6 border-t border-ln-border">
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Carte des refuges</li>
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Salles climatisées</li>
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Accès par quartier</li>
                    </ul>
                </div>
                <div class="liquid-glass-card p-8 lg:p-10 rounded-2xl flex flex-col justify-between group transition-all duration-300 min-h-[420px]">
                    <div>
                        <div class="flex items-center justify-between mb-8">
                            <span class="text-xs font-mono font-medium text-ln-accent tracking-wider">03 // ENTRAIDE</span>
                            <span class="text-xl font-light text-ln-muted group-hover:text-ln-accent group-hover:translate-x-1 transition-all">→</span>
                        </div>
                        <h3 class="font-display text-2xl lg:text-3xl font-medium tracking-tight text-ln-heading mb-4">Entraide</h3>
                        <p class="text-sm text-ln-muted font-normal leading-relaxed mb-6">
                            Rejoignez votre quartier, prenez des nouvelles de vos voisins et partagez les lieux frais autour de vous.
                        </p>
                    </div>
                    <ul class="space-y-2.5 text-xs text-ln-body font-mono pt-6 border-t border-ln-border">
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Veille des voisins</li>
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> Réseau par résidence</li>
                        <li class="flex items-center gap-2"><span class="text-ln-accent font-bold">+</span> <a href="{{ $lienConseils }}" class="hover:text-ln-accent transition-colors">Conseils à partager</a></li>
                    </ul>
                </div>
            </div>
        </div>
    </section>

    {{-- ==================== POINTS DE FRAÎCHEUR (DYNAMIQUE) ==================== --}}
    <section id="refuges" class="py-24 lg:py-32 px-6 md:px-12 lg:px-16 border-t border-ln-border-grid bg-ln-bg scroll-mt-28">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-2 h-2 rounded-full bg-ln-accent shadow-[0_0_8px_rgba(56,189,248,0.6)]"></span>
                        <span class="text-xs uppercase tracking-widest text-ln-accent font-semibold font-mono">Points de fraîcheur</span>
                    </div>
                    <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-normal tracking-tight text-ln-heading" style="letter-spacing: -0.03em;">
                        Des refuges près de chez vous.
                    </h2>
                </div>
                <p class="text-ln-muted text-sm max-w-xs font-normal">
                    {{ $nbRefuges }} {{ $nbRefuges > 1 ? 'lieux référencés' : 'lieu référencé' }} par la communauté ChillNet.
                </p>
            </div>
            <div class="divide-y divide-ln-border border-y border-ln-border">
                @forelse ($pointsFraicheur->take(4) as $i => $lieu)
                    <a href="{{ route('quartiers.show', $lieu->quartier_id ?? optional($lieu->quartier)->id) }}" class="py-8 flex flex-col md:flex-row md:items-center justify-between gap-6 group hover:px-4 hover:bg-ln-row-hover rounded-xl transition-all duration-300">
                        <div class="flex items-start md:items-center gap-6">
                            <span class="text-xs font-mono font-medium text-ln-faint">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                            <div>
                                <h4 class="font-display text-xl md:text-2xl font-medium text-ln-text group-hover:text-ln-accent transition-colors">{{ $lieu->nom }}</h4>
                                <p class="text-sm text-ln-muted font-normal mt-1">{{ $lieu->adresse }} · {{ $lieu->quartier->nom ?? 'Quartier' }} · {{ $lieu->nombre_logements }} logements</p>
                            </div>
                        </div>
                        <div class="flex items-center gap-6">
                            @if ($lieu->salle_climatisee)
                                <span class="text-xs font-mono text-ln-accent bg-ln-chip px-3 py-1 rounded-full border border-ln-border">Salle climatisée</span>
                            @else
                                <span class="text-xs font-mono text-ln-body bg-ln-chip px-3 py-1 rounded-full border border-ln-border">Point de fraîcheur</span>
                            @endif
                            <span class="text-xs font-mono font-medium text-ln-muted">Voir le quartier →</span>
                        </div>
                    </a>
                @empty
                    <p class="py-10 text-sm text-ln-muted">Aucun refuge référencé pour le moment. Rejoignez ChillNet pour cartographier les lieux frais de votre quartier.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ==================== QUARTIERS (DYNAMIQUE) ==================== --}}
    <section id="quartiers" class="py-24 lg:py-32 px-6 md:px-12 lg:px-16 border-t border-ln-border-grid bg-ln-bg-alt2 scroll-mt-28">
        <div class="max-w-7xl mx-auto">
            <div class="flex flex-col md:flex-row md:items-end justify-between mb-16 gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-3">
                        <span class="w-2 h-2 rounded-full bg-ln-accent shadow-[0_0_8px_rgba(56,189,248,0.6)]"></span>
                        <span class="text-xs uppercase tracking-widest text-ln-accent font-semibold font-mono">Quartiers couverts</span>
                    </div>
                    <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-normal tracking-tight text-ln-heading" style="letter-spacing: -0.03em;">
                        Votre quartier, votre oasis.
                    </h2>
                </div>
                <p class="text-ln-muted text-sm max-w-xs font-normal">
                    {{ $nbQuartiers }} {{ $nbQuartiers > 1 ? 'quartiers suivis' : 'quartier suivi' }} par le réseau ChillNet.
                </p>
            </div>
            <div class="grid md:grid-cols-3 gap-6 lg:gap-8">
                @forelse ($quartiers->take(3) as $q)
                    <a href="{{ route('quartiers.show', $q->id) }}" class="liquid-glass-card p-8 rounded-2xl group transition-all duration-300 flex flex-col justify-between min-h-[280px] hover:px-9">
                        <div class="flex items-center justify-between mb-8">
                            <span class="text-xs font-mono font-medium text-ln-accent tracking-wider">{{ $q->residences_count }} {{ $q->residences_count > 1 ? 'résidences' : 'résidence' }}</span>
                            <span class="text-xl font-light text-ln-muted group-hover:text-ln-accent group-hover:translate-x-1 transition-all">→</span>
                        </div>
                        <div>
                            <h3 class="font-display text-2xl font-medium tracking-tight text-ln-heading group-hover:text-ln-accent transition-colors mb-2">{{ $q->nom }}</h3>
                            <p class="text-sm text-ln-muted font-normal">Refuges, résidences et voisins mobilisés contre la chaleur.</p>
                        </div>
                    </a>
                @empty
                    <p class="text-sm text-ln-muted md:col-span-3">Les premiers quartiers arrivent bientôt. Créez un compte pour lancer la veille dans le vôtre.</p>
                @endforelse
            </div>
        </div>
    </section>

    {{-- ==================== CONSEILS EXPRESS (ancre landing) ==================== --}}
    <section id="conseils" class="py-24 lg:py-32 px-6 md:px-12 lg:px-16 border-t border-ln-border-grid bg-ln-bg scroll-mt-28">
        <div class="max-w-7xl mx-auto">
            <div class="flex items-center gap-3 mb-3">
                <span class="w-2 h-2 rounded-full bg-ln-accent shadow-[0_0_8px_rgba(56,189,248,0.6)]"></span>
                <span class="text-xs uppercase tracking-widest text-ln-accent font-semibold font-mono">Conseils express</span>
            </div>
            <h2 class="font-display text-3xl md:text-4xl lg:text-5xl font-normal tracking-tight text-ln-heading mb-12" style="letter-spacing: -0.03em;">
                Trois réflexes qui sauvent.
            </h2>
            <div class="grid md:grid-cols-3 gap-6 lg:gap-8">
                <div class="liquid-glass-card p-8 rounded-2xl transition-all duration-300">
                    <p class="text-xs font-mono text-ln-accent tracking-wider mb-4">01 // HYDRATATION</p>
                    <h3 class="font-display text-xl font-medium text-ln-heading mb-3">Boire avant d'avoir soif</h3>
                    <p class="text-sm text-ln-muted font-normal leading-relaxed">1,5 L d'eau par jour minimum. Évitez l'alcool, privilégiez les pièces fraîches.</p>
                </div>
                <div class="liquid-glass-card p-8 rounded-2xl transition-all duration-300">
                    <p class="text-xs font-mono text-ln-accent tracking-wider mb-4">02 // ÉNERGIE</p>
                    <h3 class="font-display text-xl font-medium text-ln-heading mb-3">Soulager le réseau</h3>
                    <p class="text-sm text-ln-muted font-normal leading-relaxed">Décalez fours et lave-linge après 22h pour éviter les coupures.</p>
                </div>
                <div class="liquid-glass-card p-8 rounded-2xl transition-all duration-300">
                    <p class="text-xs font-mono text-ln-accent tracking-wider mb-4">03 // VOISINS</p>
                    <h3 class="font-display text-xl font-medium text-ln-heading mb-3">Veiller sur les isolés</h3>
                    <p class="text-sm text-ln-muted font-normal leading-relaxed">Un appel matin et soir aux personnes âgées du palier.</p>
                </div>
            </div>
            <a href="{{ route('conseils') }}" class="inline-block mt-10 text-sm font-mono text-ln-accent hover:text-ln-accent-strong transition-colors">Tous les conseils →</a>
        </div>
    </section>

    {{-- ==================== CTA FINAL ==================== --}}
    <section id="contact" class="py-28 lg:py-36 px-6 md:px-12 lg:px-16 border-t border-ln-border-grid bg-ln-bg-cta relative scroll-mt-28">
        <div class="max-w-5xl mx-auto text-center">
            <span class="text-xs uppercase tracking-widest text-ln-accent font-mono mb-4 block font-semibold">Rejoindre ChillNet</span>
            <h2 class="font-display text-4xl md:text-5xl lg:text-6xl font-normal tracking-tight text-ln-heading mb-6 leading-tight" style="letter-spacing: -0.04em;">
                Prêt pour le prochain pic<br class="hidden sm:inline" /> de chaleur ? Restez au frais.
            </h2>
            <p class="text-base md:text-lg text-ln-muted max-w-xl mx-auto mb-10 font-normal">
                Créez votre compte, retrouvez votre résidence et accédez aux refuges, aux alertes et à l'entraide de votre quartier.
            </p>
            @guest
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('register') }}" class="bg-sky-500 hover:bg-ln-accent text-slate-950 font-semibold px-8 py-3 rounded-lg text-sm transition-colors whitespace-nowrap active:scale-95 shadow-md shadow-sky-500/20">
                        Créer mon compte
                    </a>
                    <a href="{{ route('login') }}" class="liquid-glass text-ln-text px-8 py-3 rounded-lg font-medium hover:bg-ln-glass-hover hover:text-ln-heading hover:border-ln-accent transition-all duration-300 active:scale-95 text-center text-sm">
                        Se connecter
                    </a>
                </div>
            @else
                <div class="flex flex-wrap items-center justify-center gap-4">
                    <a href="{{ route('dashboard') }}" class="bg-sky-500 hover:bg-ln-accent text-slate-950 font-semibold px-8 py-3 rounded-lg text-sm transition-colors whitespace-nowrap active:scale-95 shadow-md shadow-sky-500/20">
                        Accéder à mon espace
                    </a>
                    <a href="{{ $lienConseils }}" class="liquid-glass text-ln-text px-8 py-3 rounded-lg font-medium hover:bg-ln-glass-hover hover:text-ln-heading hover:border-ln-accent transition-all duration-300 active:scale-95 text-center text-sm">
                        Voir les conseils
                    </a>
                </div>
            @endauth
            <div class="mt-8 flex items-center justify-center gap-8 text-xs font-mono text-ln-faint">
                <span>SAMU : 15</span>
                <span>•</span>
                <span>Urgences : 112</span>
                <span>•</span>
                <span>Canicule : 0800 06 66 66</span>
            </div>
        </div>
    </section>

</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        const headingContainer = document.getElementById('animated-hero-heading');
        const headingText = "Restez au frais.\nRestez ensemble.";
        if (!headingContainer) return;
        const lines = headingText.split('\n');
        const charDelay = 30;
        const initialHeadingDelay = 200;
        let allCharSpans = [];
        lines.forEach((line, lineIndex) => {
            const lineDiv = document.createElement('div');
            lineDiv.className = 'block';
            const chars = Array.from(line);
            chars.forEach((char, charIndex) => {
                const charSpan = document.createElement('span');
                charSpan.className = 'hero-heading-char';
                charSpan.textContent = char === ' ' ? '\u00A0' : char;
                const calculatedDelay = initialHeadingDelay + (lineIndex * chars.length * charDelay) + (charIndex * charDelay);
                charSpan.style.transitionDelay = `${calculatedDelay}ms`;
                lineDiv.appendChild(charSpan);
                allCharSpans.push(charSpan);
            });
            headingContainer.appendChild(lineDiv);
        });
        requestAnimationFrame(() => {
            allCharSpans.forEach(span => { span.classList.add('revealed'); });
        });
        const subheading = document.getElementById('hero-subheading');
        if (subheading) {
            subheading.style.transitionDuration = '1000ms';
            setTimeout(() => { subheading.classList.add('revealed'); }, 800);
        }
        const buttons = document.getElementById('hero-buttons');
        if (buttons) {
            buttons.style.transitionDuration = '1000ms';
            setTimeout(() => { buttons.classList.add('revealed'); }, 1200);
        }
        const tag = document.getElementById('hero-tag');
        if (tag) {
            tag.style.transitionDuration = '1000ms';
            setTimeout(() => { tag.classList.add('revealed'); }, 1400);
        }
    });
</script>
</x-public-layout>
