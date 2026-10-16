# Module Coupures + Module Quartiers/Résidences — Documentation complète (ChillNet)

> Périmètre : tout ce qui concerne les **coupures de courant (Module 2)** et le **référentiel géographique** (quartiers, résidences), leurs interactions, fichiers, routes, règles métier, IA de risque, vues et tests.
> Code audité le 15/10/2026. Projet Laravel (Blade + Eloquent). Langue applicative : français.

---

## 1. Vue d'ensemble

### 1.1. Rôles des modules

| Module | Objectif | Acteurs |
|---|---|---|
| **Coupures** (Module 2) | Recenser et afficher les coupures de courant par zone : carte + liste publiques des incidents en cours/prévus, signalement par l'habitant, confirmation « voisin », back-office CRUD + tableau de bord | Public (lecture carte/liste), Habitant (signaler + confirmer), Gestionnaire (sa zone), Admin (tout) |
| **Quartiers / Résidences** | Référentiel géographique : le **quartier** est la zone interne (coupures, alertes, périmètre gestionnaire) ; la **résidence** rattache le gestionnaire à sa zone et sert de refuge/point de fraîcheur | Admin (CRUD quartiers + résidences), Gestionnaire (sa résidence en lecture/édition) |

### 1.2. Principe structurant : la zone = le quartier

Il n'existe **pas de table `zones`** en double. Une coupure touche **une zone précise = un quartier** (`coupures.quartier_id`). Depuis `2026_10_10`, ce rattachement est **facultatif** : une coupure peut être ciblée **librement par un point** (latitude/longitude) posé sur la carte, sans quartier existant — les deux cohabitent (ciblage historique par quartier, ciblage moderne par point).

```
Quartier 1──N Coupure        (zone interne, quartier_id nullable)
Quartier 1──N Residence      (cascade)
Residence 1──N User          (users.residence_id = périmètre du gestionnaire)
Quartier N──N Alerte         (via alerte_quartier, voir module-alerte-lieux.md)
Residence 1──N Signalement   (via signalements.residence_id)
```

Le quartier est **auto-déduit** partout où c'est possible (`Quartier::plusProche`) : lieu habitant, résidence, signalement de coupure. L'habitant ne choisit **jamais** un quartier dans l'UI ; il raisonne en « mes lieux ».

---

## 2. Modèle de données

### 2.1. Table `quartiers`

Migrations : `2026_09_19_195406_create_quartiers_table.php` + `2026_10_04_000001_add_coordinates_to_quartiers_table.php`.
Modèle : `app/Models/Quartier.php`.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | PK | — |
| `nom` | string(120) | `required, min:2, max:120` |
| `ville` | string(120) | `required, min:2, max:120` |
| `code_postal` | string(10) | `required`, regex `^[0-9A-Za-z\- ]{3,10}$` |
| `latitude` / `longitude` | decimal(10,7) nullable | Centre du quartier (carte Leaflet). **Nullable** : quartier déclaré par un habitant sans position |
| `description` | text nullable | max 2000 |
| `timestamps` | — | (créés explicitement en `dateTime`, pas de `softDeletes`) |

### 2.2. Table `residences`

Migrations : `2026_09_19_195407_create_residences_table.php` + `2026_10_07_000004_add_coordinates_to_residences_table.php`.
Modèle : `app/Models/Residence.php`.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | PK | — |
| `nom` | string(150) | `required, min:2, max:150` |
| `adresse` | string(255) | `required, min:5, max:255` |
| `latitude` / `longitude` | decimal(10,7) nullable | Position (périmètre 800 m des refuges, `Geo::RAYON_REFUGES_M`) |
| `nombre_logements` | uint, default `0` | `required, integer, 0–5000` |
| `salle_climatisee` | bool, default `false` | Case à cocher (normalisée en booléen par le contrôleur) |
| `point_fraicheur` | bool, default `false` | Résidence « refuge » (scope `pointFraicheur`) |
| `quartier_id` | FK `quartiers` **cascade** | Zone de rattachement (obligatoire) |
| `timestamps` | — | — |

### 2.3. Table `coupures`

Migrations : `2026_10_03_000001_create_coupures_table.php`, `2026_10_05_000001_add_confirmations_to_coupures_table.php`, `2026_10_06_000001_add_lieu_to_coupures_table.php`, `2026_10_10_000001_add_geo_targeting_to_coupures_table.php`.
Modèle : `app/Models/Coupure.php`.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | PK | — |
| `quartier_id` | FK `quartiers`, **nullable** (depuis `2026_10_10`), `cascadeOnDelete` | Zone interne. Peut être `null` si ciblage par point uniquement |
| `latitude` / `longitude` | decimal(10,7) nullable | Point posé librement sur la carte (ciblage moderne) |
| `lieu` | string(255) nullable | Rue / lieu précis → permet 2 coupures simultanées dans 2 rues du même quartier |
| `type` | string(20), default `panne` | Cast `TypeCoupure` |
| `statut` | string(20), default `en_cours` | Cast `StatutCoupure` |
| `debut` | datetime | Obligatoire |
| `fin` | datetime nullable | Facultative mais `after:debut` ; sans fin = coupure « ponctuelle » (+2 h au test de chevauchement) |
| `description` | text nullable | max 2000 |
| `confirmations` | uint, default `0` | Compteur « Je confirme » (voisins) |
| `user_id` | FK `users` nullable, `nullOnDelete` | Auteur (habitant signalant ou gestionnaire publiant) |
| `timestamps` | — | `created_at` sert à la **détection d'anomalie** (afflux) |

Casts (`Coupure::casts()` `:45`) : `type`/`statut` enums, `confirmations` int, `latitude`/`longitude` float, `debut`/`fin` datetime.

### 2.4. Relations

```
Quartier 1──N Residence / 1──N Coupure / N──N Alerte (pivot alerte_quartier)
Residence N──1 Quartier / 1──N User / 1──N Signalement
Coupure  N──1 Quartier (nullable) / N──1 User (auteur)
User     N──1 Residence (residence_id, gestionnaire uniquement)
```

Helpers `User` : `residence(): BelongsTo` (`app/Models/User.php:68`), `isAdmin()/isGestionnaire()/isHabitant()` (`:105-118`).

---

## 3. Enums

### 3.1. `TypeCoupure` (`app/Enums/TypeCoupure.php`)

`Delestage=delestage`, `Surcharge=surcharge`, `Panne=panne`, `Maintenance=maintenance` + `label()` (Délestage / Surcharge / Panne / Maintenance).

### 3.2. `StatutCoupure` (`app/Enums/StatutCoupure.php`)

`EnCours=en_cours` → « En cours », `Prevue=prevue` → « Prévue », `Resolue=resolue` → « Résolue ».

### 3.3. `NiveauRisque` (`app/Enums/NiveauRisque.php`)

Dérivé d'un **score IA 0–100**, ordre croissant `Faible < Modere < Eleve < Critique`. Méthodes : `label()`, `badgeClasses()` (green / amber / orange / red), `couleurHex()` (`#16a34a / #f59e0b / #ea580c / #dc2626`), `icone()` (Material Symbols : `shield / warning / error / crisis_alert`).

`fromScore(int)` : **paliers** `≥75 Critique`, `≥50 Eleve`, `≥25 Modere`, sinon `Faible`.

---

## 4. Logique métier du modèle `Coupure`

Fichier : `app/Models/Coupure.php`.

| Méthode | Description |
|---|---|
| `quartier(): BelongsTo` | Zone (`quartier_id` nullable) |
| `user(): BelongsTo` | Auteur du signalement/publication |
| `hasCoordinates(): bool` | `latitude` ET `longitude` non null |
| `scopeActives` / `scopePrevues` | Filtre par statut (`en_cours` / `prevue`) |
| `scopeParZone(int)` | `where quartier_id = ?` |
| `chevauche(quartierId, debut, fin, ignoreId, lieu): bool` (static) | **Anti-doublon**. Voir ci-dessous |

### 4.1. Anti-chevauchement `Coupure::chevauche()` (`:123`)

Y a-t-il déjà une coupure **non résolue** qui occupe le **même endroit** (même zone **ET** même rue) sur le créneau `[debut, fin]` ?

- Candidats SQL : même `quartier_id`, `statut != resolue`, `id != ignoreId`, `debut < finEffective`.
- **Fin effective** : `fin` fournie, sinon `debut + 2 h` (coupure ponctuelle sans fin).
- **Rue** : comparaison insensible à la casse/espaces. Si les deux rues sont renseignées et **différentes** → incidents distincts, on **continue** (pas de blocage). Si l'une est inconnue → on reste prudent (blocage si le créneau se chevauche).
- Le test `finExistante > debut` est fait **en PHP** (pas de fonction SQL distance/date) pour rester portable **MySQL / SQLite**.

Utilisé par `StoreCoupureRequest::withValidator` (voir §10.1).

---

## 5. Logique métier du modèle `Quartier`

Fichier : `app/Models/Quartier.php`.

| Méthode | Description |
|---|---|
| `hasCoordinates(): bool` | lat/lng non null |
| `plusProche(?float lat, ?float lng): ?self` (static) | Quartier géolocalisé le plus proche d'un point, via **Haversine PHP** (`Geo::distanceKm`). `null` si coordonnées absentes. **Cœur de l'auto-rattachement** (lieu → quartier, coupure/point → quartier) |
| `residences(): HasMany` | Résidences du quartier |
| `coupures(): HasMany` | Coupures de la zone |
| `alertes(): BelongsToMany` | Alertes via table `alerte_quartier` |
| `libelle(): string` | `nom (ville · code_postal)` — code postal facultatif (quartier déclaré) |

Distance : `App\Support\Geo::distanceKm()` (Haversine, rayon 6371 km) — **même formule** que le JS des cartes Leaflet (`coupures-create.blade.php`). `Geo::RAYON_REFUGES_M = 800` (périmètre refuges, hors périmètre de ce doc).

Le tri « zone » des listes (back & front) s'appuie sur une sous-requête `Quartier::select('nom')->whereColumn('quartiers.id', 'coupures.quartier_id')`.

---

## 6. IA du module 2 — `CoupureRiskAiService`

Fichier : `app/Services/CoupureRiskAiService.php`. Approche **déterministe et explicable** (aucun appel réseau, tout est calculé depuis la base, portable MySQL/SQLite), dans le même esprit que la vigilance canicule.

### 6.1. Constantes

| Constante | Valeur | Sens |
|---|---|---|
| `FENETRE_HISTORIQUE_JOURS` | `14` | Fenêtre d'observation des coupures pour le score |
| `FENETRE_ANOMALIE_MINUTES` | `120` | Fenêtre glissante de détection d'afflux de signalements |
| `SEUIL_ANOMALIE` | `3` | Nombre de signalements à partir duquel on alerte |

### 6.2. `score(Quartier, ?float temperature): array` — score de risque 0–100

Le score = somme de 4 sous-scores, borné à `[0, 100]` :

| # | Sous-score | Plage | Calcul |
|---|---|---|---|
| 1 | **Fréquence récente** | 0–35 | `min(35, fréquence * 7)` coupures des 14 derniers jours |
| 2 | **Part « liée à la chaleur »** | 0–20 | part des coupures `surcharge`/`délestage` × 20 |
| 3 | **Canicule** | 0–35 | Alerte **validée** couvrant la zone et `fin ≥ now` : `Rouge → 35`, `Orange → 22`, `Jaune → 10`, sinon pic ≥ 35 °C `→ 12`, sinon `0` |
| 4 | **Activité + confirmations** | 0–10 | `min(10, enCours * 4 + (confirmations ≥ 3 ? 2 : 0))` |

Retourne `{quartier, score, niveau (NiveauRisque::fromScore), raison, facteurs[], frequence, en_cours, temperature}`. La `raison` est une phrase du type `3 coupure(s) en 14 j · alerte orange · 1 en cours`. Les `facteurs` listent les causes retenues (fréquence, coupures chaudes, vigilance, pic de température, en cours).

Le **pic de température** provient des alertes couvrant la zone (`temperature_actuelle` max), sinon du paramètre `$temperature`.

### 6.3. `scoresPourQuartiers(iterable): Collection`

Applique `score()` à chaque quartier puis **trie du plus risqué au moins risqué** (`sortByDesc('score')->values()`).

### 6.4. `detecterAnomalies(?int fenetre, ?int seuil): Collection`

Un **afflux de signalements** sur une même zone (≥ `SEUIL_ANOMALIE` en `FENETRE_ANOMALIE_MINUTES`) peut trahir un incident majeur non déclaré. Basé sur `coupures.created_at` (et non `debut`), statut ≠ `resolue`, `quartier_id` non null, regroupé par zone. Retourne `[{quartier, nombre, fenetre_minutes, message}]`, `message` = `« N signalements en 120 min sur X — possible incident majeur non déclaré. »`.

---

## 7. Back office Coupures (gestionnaire / admin)

Contrôleur : `app/Http/Controllers/Back/CoupureController.php`. Préfixe `/admin`, middleware `auth, role:admin,gestionnaire`.

| Action | Route | Comportement |
|---|---|---|
| `index` | `GET /admin/coupures` (`back.coupures.index`) | Gestionnaire **scopé à sa zone** (`where quartier_id = residence.quartier_id`), admin tout |
| `create` | `GET /admin/coupures/create` | Formulaire + quartiers accessibles + centre carte |
| `store` | `POST /admin/coupures` | `attributes()` (garde-fous) + `user_id` = auteur |
| `show` | `GET /admin/coupures/{coupure}` | `authorizeCoupure()` + with `quartier,user` |
| `edit` | `GET /admin/coupures/{coupure}/edit` | idem + quartiers accessibles |
| `update` | `PUT/PATCH /admin/coupures/{coupure}` | idem |
| `destroy` | `DELETE /admin/coupures/{coupure}` | idem |

**`index` détails :**
- **Stats** (même périmètre) : `total`, `en_cours`, `prevues`, `resolues`, `confirmations` (somme).
- **Tri** `?tri=` : `recent` (défaut), `ancien`, `zone`, `statut` (ordre en cours→prévue→résolue via `CASE`), `type`. Valeur inconnue → `recent`.
- **Carte** : marqueurs pour **toutes** les coupures du périmètre (pas seulement la page). Coordonnées = point libre si présent, sinon centre géolocalisé du quartier, sinon marqueur ignoré. `titre` = `type — zone`, `detail` = rue · statut · créneau, + lien `edit`.
- **Pagination** : 10/page, `withQueryString()`.
- **Brique IA** : `risques` = `scoresPourQuartiers(quartiersAccessibles())`, `anomalies` = `detecterAnomalies()` (filtrées sur la zone du gestionnaire).

**Garde-fous gestionnaire :**
- `quartiersAccessibles()` (`:176`) : le gestionnaire ne voit que **son** quartier ; l'admin les voit tous.
- `authorizeCoupure($id)` (`:190`) : 403 si la coupure n'est pas dans sa zone.
- `attributes()` (`:233`) : le gestionnaire est **toujours ramené à sa zone** (même si le formulaire est trafiqué) ; un point posé sans quartier est rattaché au **quartier le plus proche** (`plusProche`), sans jamais dépendre de son existence (nullable). Le `statut` est saisi en back (contrairement au front, forcé à `en_cours`).
- `centreCarte()` (`:209`) : zone du gestionnaire si géolocalisée, sinon barycentre des quartiers, sinon Tunis `[36.8065, 10.1815]`.

Vues : `resources/views/back/coupures/{index,create,edit,show}.blade.php` + `_form.blade.php`.

---

## 8. Front office Coupures (public + habitant)

Contrôleur : `app/Http/Controllers/Front/CoupureController.php`.

### 8.1. `index` — public

Carte + liste des coupures **en cours et prévues** (l'historique résolu reste au back). Public (route hors groupe `auth`).

1. Base : `statut IN (en_cours, prevue)` + `with('quartier')` (évite le N+1), filtre `?quartier_id`.
2. Compteurs d'en-tête : `nbActives` (scope `actives`), `nbPrevues` (scope `prevues`), `nbZones` (`distinct quartier_id`).
3. IA : `risques` (score par zone sur **tous** les quartiers) + `anomalies`.
4. Tri `?tri=` : `recent` / `ancien` / `zone` / `statut` / `type` (mêmes règles que le back).
5. **Carte** : toutes les coupures filtrées (pas seulement la page). Coordonnées = point libre, sinon centre du quartier. `quartiersCoords` = coords des quartiers géolocalisés (pour la carte).
6. **Centre** : zone filtrée si géolocalisée, sinon barycentre des quartiers géo, sinon Tunis.
7. Pagination 10/page avec `withQueryString()`.

Vue : `resources/views/front/coupures.blade.php`.

### 8.2. `create` — habitant

Formulaire de signalement : l'habitant choisit **l'un de ses lieux** (Domicile, Travail…), jamais un quartier. `lieuxJson` (id, nom, typeLabel, lat, lng, principal) alimente la carte Leaflet ; centre = lieu principal, sinon barycentre des lieux géo, sinon Tunis. `lieuIdParDefaut` = lieu principal (ou `old()`). Vue : `resources/views/front/coupures-create.blade.php`.

### 8.3. `store` — habitant

`StoreCoupureRequest::validated()`, puis :
1. Résolution du lieu : `$user->lieux()->findOrFail(lieu_id)` (appartenance au foyer).
2. `quartier_id` = quartier du lieu, sinon `plusProche` du point du lieu, sinon `null` (un signalement reste valide sans quartier — la carte s'appuie alors sur les coordonnées exactes).
3. Mémorisation du **point exact du lieu** (lat/lng) pour une carte plus précise.
4. `statut` **forcé à `en_cours`**, `user_id` = habitant.
5. Redirection `coupures.index` avec message de succès.

### 8.4. `confirm` — habitant (« Je confirme »)

Crédibilise un signalement (+1 `confirmations`). Règles :
- Uniquement une coupure **en cours**.
- Jamais sur son **propre** signalement.
- **Une fois par session** (`session()->get('coupure_confirmee_{id}')`) → anti double-clic **sans table pivot**.

---

## 9. Back office Quartiers & Résidences

### 9.1. `Back\QuartierController` — admin uniquement

Route `Route::resource('quartiers')->except(['show'])->middleware('role:admin')`. Actions `index` (with `residences_count`, tri par nom), `create/store/edit/update/destroy` (via `StoreQuartierRequest`).
**Suppression bloquée** si le quartier contient des résidences (`Impossible de supprimer un quartier qui contient des résidences.`).
Vues : `resources/views/back/quartiers/{index,create,edit}.blade.php` + `_form.blade.php`.

### 9.2. `Back\ResidenceController` — admin + gestionnaire

Route `Route::resource('residences')->except(['show'])`.
- **Admin** : CRUD global.
- **Gestionnaire** : voit/édite **uniquement sa résidence** (`authorizeResidence`), **pas** de création ni suppression (`abort_if(..., 403)`), liste vide + consigne si aucune résidence rattachée.
- Cases à cocher normalisées en booléens (`attributes()` : `salle_climatisee`, `point_fraicheur`) pour que la décoche soit bien prise en compte.
Vues : `resources/views/back/residences/{index,create,edit}.blade.php` + `_form.blade.php`.

### 9.3. Page publique d'un quartier — `Front\HomeController@quartier`

Route `GET /quartiers/{id}` (`quartiers.show`). Affiche `front.quartier` avec le quartier et ses résidences (triées par nom). Vue : `resources/views/front/quartier.blade.php`.

---

## 10. Validation (FormRequests)

### 10.1. `StoreCoupureRequest` (`app/Http/Requests/StoreCoupureRequest.php`)

**Deux parcours dans le même formulaire :**

- **Habitant (front)** — `viaLieu()` = `user()->isHabitant()`. `prepareForValidation` neutralise `quartier_id/latitude/longitude`. Règles : `lieu_id` **`required` + `exists('lieux','id')->where('user_id', user)`** (pas de fuite vers un autre foyer), `lieu` nullable, `type` in enum, `debut` requis, `fin` nullable `after:debut`, `description` max 2000.
- **Gestionnaire / admin (back)** — Règles : `quartier_id` nullable `exists`, `latitude` `-90/90`, `longitude` `-180/180`, `lieu` nullable, `type` requis, `statut` requis, `debut` requis, `fin` nullable `after:debut`, `description` max 2000.

**`withValidator` :**
- Habitant : chevauchement jugé sur le **quartier interne du lieu** (via `Coupure::chevauche`), skip si erreurs sur `lieu_id/debut/fin`.
- Back : **ciblage obligatoire** (`quartier_id` OU point complet lat+lng), sinon `Ciblez la coupure…`. Anti-chevauchement : zone = quartier choisi sinon `plusProche` du point ; **skip** si le statut est `resolue` (l'historique peut chevaucher) ; `ignoreId` depuis la route (`coupure|id`) en update. Message : `Une autre coupure (en cours ou prévue) occupe déjà cet endroit (même zone, même rue) sur ce créneau.`

Messages français fournis (`messages()`).

### 10.2. `StoreQuartierRequest` (`app/Http/Requests/StoreQuartierRequest.php`)

`nom` 2–120 requis, `ville` 2–120 requis, `code_postal` requis regex `^[0-9A-Za-z\- ]{3,10}$`, `latitude/longitude` **nullable** (quartier déclaré) mais bornés, `description` max 2000.

### 10.3. `StoreResidenceRequest` (`app/Http/Requests/StoreResidenceRequest.php`)

`nom` 2–150, `adresse` 5–255, `latitude/longitude` nullable bornés, `quartier_id` requis `exists`, `nombre_logements` requis int 0–5000, `salle_climatisee`/`point_fraicheur` `sometimes|boolean`.

---

## 11. Routes récapitulatives

```
# Public (sans auth)
GET  /quartiers/{id}              → Front\HomeController@quartier        (quartiers.show)
GET  /coupures                    → Front\CoupureController@index        (coupures.index)

# Habitant (auth + role:habitant)
GET  /coupures/signaler           → Front\CoupureController@create       (coupures.create)
POST /coupures                    → Front\CoupureController@store        (coupures.store)
POST /coupures/{coupure}/confirmer→ Front\CoupureController@confirm      (coupures.confirm)

# Back (auth + role:admin,gestionnaire, préfixe /admin, nom back.)
GET   /admin/quartiers*           → Back\QuartierController   (resource, sans show)  [role:admin uniquement]
GET   /admin/residences*          → Back\ResidenceController (resource, sans show)
GET   /admin/coupures             → Back\CoupureController@index   (back.coupures.index)
GET   /admin/coupures/create      → Back\CoupureController@create
POST  /admin/coupures             → Back\CoupureController@store
GET   /admin/coupures/{coupure}   → Back\CoupureController@show
GET   /admin/coupures/{coupure}/edit → Back\CoupureController@edit
PUT   /admin/coupures/{coupure}   → Back\CoupureController@update
DELETE /admin/coupures/{coupure}  → Back\CoupureController@destroy
```

---

## 12. Seeders, factories, tests

### 12.1. Référentiel géographique (`DatabaseSeeder`)

Amorce : **2 quartiers** (`Centre-Ville`, `Les Berges du Lac`, géolocalisés), **3 résidences** (`Résidence Les Oliviers`, `El Manar`, `Lac View`), des points de fraîcheur (résidences), et **3 comptes** : `admin@chillnet.test` (Admin, sans résidence), `gestionnaire@chillnet.test` (Gestionnaire, Oliviers), `gestionnaire.berges@chillnet.test` (Gestionnaire, Lac View), `habitant@chillnet.test` (Habitant, profil `personne_agee`). Idempotent (`updateOrCreate`). Appelle ensuite `CoupureSeeder`, puis alertes et points de fraîcheur.

### 12.2. Coupures

- **Factory** `database/factories/CoupureFactory.php` : `definition()` (quartier existant ou créé, type/statut aléatoires, debut −3j/+3j, fin +1–5 h, description, rue optionnelle, confirmations 0–5) + states : `enCours()`, `prevue()`, `resolue()`, `delestage()/surcharge()/panne()/maintenance()`, `geo(lat,lng)`, `sansQuartier()`, `dansQuartier(Quartier|int)`, `rue(string)`, `confirmee(int)`, `signaleePar(?User)`.
- **Seeder** `database/seeders/CoupureSeeder.php` : 8 coupures idempotentes (clé = `description`) couvrant **les 3 statuts** et **les 4 types**, ciblage **par quartier ET par point**, confirmations, historique résolu, et un **AFFLUX de 3 signalements** sur Centre-Ville (`created_at` repositionné à chaque run) qui **déclenche la détection d'anomalie** du module IA. Sans appel réseau.

### 12.3. Tests

- `tests/Unit/CoupureRiskAiServiceTest.php` — score sans historique = 0 ; l'historique / une alerte canicule augmentent le score ; tri décroissant ; afflux = anomalie ; sous le seuil = rien ; coupures résolues n'anomalisent pas ; paliers `NiveauRisque`.
- `tests/Feature/Front/CoupureRiskTest.php` — panneau IA affiché (public + back) ; afflux signalé.
- `tests/Feature/Front/CoupureSignalementTest.php` — le formulaire liste les lieux du foyer ; signalement depuis un lieu (avec/sans zone) ; refus depuis le lieu d'un autre foyer ; invitation à créer un lieu si aucun.
- `tests/Feature/Back/CoupureManagementTest.php` — création depuis un point sans quartier ; création sans aucun quartier si non géolocalisé ; ciblage obligatoire ; zone forcée pour le gestionnaire ; rendu create/edit (quartier ou point seul)/show ; 403 hors zone.
- `tests/Feature/Back/QuartierResidenceManagementTest.php` — CRUD quartier ; suppression bloquée si résidences ; création résidence (cases décochées = false) ; gestionnaire ne peut créer/supprimer une résidence ; gestionnaire ne voit/édite que la sienne ; gestionnaire sans accès aux quartiers ; quartier existant requis ; habitant sans accès au back ; rendu des écrans ; seeder rejouable ; les coupures seedées couvrent tous les statuts + anomalie.

---

## 13. Points d'attention & cas limites

- **Zone interne invisible** : l'habitant ne saisit jamais un quartier ; il est toujours déduit (`plusProche` ou lieu). Garde-fou gestionnaire : `quartier_id` **forcé** à sa zone à l'enregistrement.
- **Ciblage obligatoire (back)** : une coupure doit avoir un quartier **ou** un point. Côté habitant, la cible est le `lieu_id` (validé par appartenance).
- **Anti-doublon par (zone, rue, créneau)** : deux rues différentes du même quartier autorisent deux coupures simultanées ; une rue inconnue d'un côté bloque prudemment. Les coupures **résolues** ne bloquent jamais.
- **Nullabilité de `quartier_id`** : un signalement sans quartier géolocalisé à proximité reste valide ; la carte s'appuie sur les coordonnées exactes.
- **Portabilité SQL** : tests de dates et distances faits **en PHP** (Carbon / Haversine), aucun `datetime()` SQL (MySQL vs SQLite).
- **Anomalie** basée sur `created_at` (moment du signalement), pas `debut` : le seeder repositionne `created_at` pour garder la démo parlante.
- **Périmètre gestionnaire** : stats, carte, risques et anomalies sont tous restreints à sa zone ; le front public, lui, voit toutes les zones.
- **Confirmations = session** : pas de table pivot ; un voisin ne confirme qu'une fois par session, jamais son propre signalement.

---

## 14. Index des fichiers

```
app/Models/Coupure.php                              # modèle, scopes, chevauche anti-doublon
app/Models/Quartier.php                             # modèle, plusProche (haversine), libelle
app/Models/Residence.php                            # modèle, scopePointFraicheur
app/Models/User.php                                 # residence(), isAdmin/Gestionnaire/Habitant
app/Enums/TypeCoupure.php | StatutCoupure.php | NiveauRisque.php | Role.php
app/Support/Geo.php                                 # haversine distanceKm, RAYON_REFUGES_M
app/Services/CoupureRiskAiService.php               # score risque par zone + anomalies
app/Http/Controllers/Back/CoupureController.php     # CRUD + stats + carte + IA + périmètre
app/Http/Controllers/Front/CoupureController.php    # index public + create/store/confirm habitant
app/Http/Controllers/Back/QuartierController.php    # CRUD quartiers (admin)
app/Http/Controllers/Back/ResidenceController.php   # CRUD résidences (admin) / sa résidence (gestionnaire)
app/Http/Controllers/Front/HomeController.php       # quartier() → page publique d'un quartier
app/Http/Requests/StoreCoupureRequest.php | StoreQuartierRequest.php | StoreResidenceRequest.php
database/migrations/2026_09_19_195406_create_quartiers_table.php
database/migrations/2026_10_04_000001_add_coordinates_to_quartiers_table.php
database/migrations/2026_09_19_195407_create_residences_table.php
database/migrations/2026_10_07_000004_add_coordinates_to_residences_table.php
database/migrations/2026_10_03_000001_create_coupures_table.php
database/migrations/2026_10_05_000001_add_confirmations_to_coupures_table.php
database/migrations/2026_10_06_000001_add_lieu_to_coupures_table.php
database/migrations/2026_10_10_000001_add_geo_targeting_to_coupures_table.php
database/factories/CoupureFactory.php   | database/seeders/CoupureSeeder.php   | DatabaseSeeder.php
resources/views/back/coupures/{index,create,edit,show,_form}.blade.php
resources/views/back/quartiers/{index,create,edit,_form}.blade.php
resources/views/back/residences/{index,create,edit,_form}.blade.php
resources/views/front/coupures.blade.php | front/coupures-create.blade.php | front/quartier.blade.php
routes/web.php                                      # §11
tests/Unit/CoupureRiskAiServiceTest.php
tests/Feature/Front/{CoupureRiskTest,CoupureSignalementTest}.php
tests/Feature/Back/{CoupureManagementTest,QuartierResidenceManagementTest}.php
```

> Pour toute modification : préserver le contrat « zone = quartier (interne, invisible habitant) », le ciblage obligatoire (quartier OU point), l'anti-doublon par (zone, rue, créneau), la nullabilité de `quartier_id`, et le périmètre strict du gestionnaire.
