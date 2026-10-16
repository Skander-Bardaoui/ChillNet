# Module Alerte Canicule + Module Lieux — Documentation complète (ChillNet)

> Périmètre : tout ce qui concerne les **alertes canicule (Module 1)** et les **lieux personnels de l'habitant**, leurs interactions, fichiers, routes, règles métier, IA/météo, vues et tests.
> Code audité le 13/10/2026. Projet Laravel (Blade + Eloquent). Langue applicative : français.

---

## 1. Vue d'ensemble

### 1.1. Rôles des deux modules

| Module | Objectif | Acteurs |
|---|---|---|
| **Alerte** | Créer, valider et diffuser des vigilances canicule (jaune / orange / rouge) géociblées, avec message IA personnalisé par profil de vulnérabilité | Habitant (lecture seule), Gestionnaire (sa zone), Admin (tout) |
| **Lieux** | Permettre à chaque foyer habitant de gérer ses propres points géolocalisés (`Domicile`, `Travail`, `Autre`) qui **remplacent le quartier dans toute l'interface habitant** | Habitant uniquement (CRUD strictement scopé à `user_id`) |

Principe structurant : le **quartier reste purement interne**. Il est auto-assigné au lieu (le plus proche du point, via `Lieu::booted`) pour que coupures / périmètre gestionnaire / pages publiques continuent de fonctionner, **sans jamais être montré à l'habitant**.

### 1.2. Interaction Alerte × Lieu (le cœur du système)

```
Lieu (lat,lng + quartier_id auto) ──► Alerte::filtrerPourLieu()
                                            │
                                            ├─► couvreQuartier(quartier_id) via pivot alerte_quartier (alertes héritées)
                                            └─► couvrePoint(lat,lng) via cercle (haversine, rayon 1 km par défaut)
```

Utilisé à 3 endroits :
1. `Front\AlerteController@index` — liste « mes lieux » filtrée sur le lieu consulté (`?lieu_id` ou lieu principal).
2. `Front\DashboardController@index` — bandeau vigilance = alerte active la plus grave couvrant le foyer.
3. `RecommandationFraicheurService` (ligne 280) — comptage d'alertes pour un lieu.

Une alerte **sans aucune cible** (ni quartier ni cercle) ne remonte **jamais** côté habitant.

---

## 2. Modèle de données

### 2.1. Table `alertes`

Migration : `database/migrations/2026_10_07_000001_create_alertes_table.php` + `2026_10_08_000001_add_geo_targeting_to_alertes_table.php`.
Modèle : `app/Models/Alerte.php`.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | PK | — |
| `titre` | string(150) | `required, min:3, max:150` |
| `niveau` | string(10), default `jaune`, indexé | Cast `NiveauAlerte` enum |
| `seuil_temperature` | decimal(4,1) | `required, 30–55` °C |
| `temperature_actuelle` | decimal(4,1) nullable | Mesure ayant servi à classer, `-10–60` |
| `temperature_ressentie` | decimal(4,1) nullable | `-10–70` |
| `humidite` | tinyInt nullable | `0–100` % |
| `source_meteo` | string(20), default `manuel` | `weatherapi` ou `manuel` |
| `debut` / `fin` | datetime | `fin > debut` (règle + message dédié) |
| `message` | text nullable, max 2000 | Rédigé par IA (Groq), modifiable |
| `latitude` / `longitude` | decimal(10,7) nullable | Centre du cercle de ciblage |
| `rayon_metres` | uint nullable | 100 m – 50 km, défaut 1 km (`RAYON_DEFAUT_M`) |
| `validee` | bool default `false` | **Gate d'affichage front** : seul `true` est visible habitant |
| `validee_le` | timestamp nullable | Horodatage validation |
| `validee_par` | FK `users` nullable | Manager validateur (`validateur()`) |
| `user_id` | FK `users` nullable | Créateur (`user()`) |
| `timestamps` + index `[validee, debut, fin]` | — | Perf requêtes front |

### 2.2. Table pivot `alerte_quartier`

Migration : `2026_10_07_000002_create_alerte_quartier_table.php`. PK composite `(alerte_id, quartier_id)`, `cascadeOnDelete` des deux côtés. Relation N—N : une alerte couvre **un ou plusieurs quartiers**.

### 2.3. Table `lieux`

Migration : `2026_10_09_000001_create_lieux_table.php`. Modèle : `app/Models/Lieu.php`.

| Colonne | Type | Rôle |
|---|---|---|
| `id` | PK | — |
| `user_id` | FK `users` cascade | Propriétaire (scoping 403) |
| `nom` | string(120) | Libre, défaut `Domicile`, `min:2` |
| `type` | string(20), default `domicile` | Cast `LieuType` (`domicile/travail/autre`) |
| `adresse` | string(255) nullable | Via géocodage inverse (optionnel) |
| `latitude` / `longitude` | decimal(10,7) nullable en base, **mais `required` en validation** | Point Leaflet obligatoire |
| `quartier_id` | FK `quartiers` nullable, `nullOnDelete` | **Auto-assigné** (jamais saisi) |
| `est_principal` | bool default `false` | Un seul principal par foyer |
| Index `[user_id, est_principal]` | — | — |

Auto-assignation (`Lieu::booted`, `saving`) :
```php
// Si lat/lng présents ET (création OU point modifié) :
$lieu->quartier_id = Quartier::plusProche($lat, $lng)?->id;
```

Backfill : `2026_10_09_000002_backfill_lieux_from_residences.php` — chaque habitant avec résidence reçoit un lieu `Domicile` (coords résidence, sinon centroïde quartier, `est_principal=true`). Idempotent (skip si `Domicile` existe déjà). `down()` volontairement vide.

### 2.4. Relations

```
User 1──N Lieu N──1 Quartier (interne, auto)
User 1──N Alerte (user_id = créateur)
User 1──N Alerte (validee_par = validateur)
Alerte N──N Quartier (alerte_quartier)
Lieu N──1 User / N──1 Quartier
```

Helpers `User` (`app/Models/User.php:80-103`) :
- `lieux(): HasMany`
- `lieuPrincipal(): ?Lieu` — `est_principal=true`, sinon premier par `id`, sinon `null`.

---

## 3. Enums

### 3.1. `NiveauAlerte` (`app/Enums/NiveauAlerte.php`)

`Jaune=jaune < Orange=orange < Rouge=rouge`. Méthodes : `label()`, `badgeClasses()` (Tailwind), `couleurHex()` (`#f59e0b/#ea580c/#dc2626`, marqueurs Leaflet), `icone()` (Material Symbols : `thermostat/wb_sunny/warning`), `gravite(): 1/2/3`, `plusGrave(iterable): ?self`.

### 3.2. `StatutAlerte` (`app/Enums/StatutAlerte.php`)

**Dérivé, jamais stocké** : `Programmee` (debut futur) / `Active` (debut ≤ now ≤ fin) / `Terminee` (fin passée). Attribut Eloquent `Alerte::$statut` + `estActive()/estProgrammee()/estTerminee()`. Badges : sky / red / surface-variant.

### 3.3. `LieuType` (`app/Enums/LieuType.php`)

`Domicile/Travail/Autre` + `label()`, `icone()` (`home/work/place`), `valeurs(): list<string>`.

---

## 4. Logique métier du modèle `Alerte`

Fichier : `app/Models/Alerte.php`.

| Méthode | Description |
|---|---|
| `quartiers(): BelongsToMany` | Via `alerte_quartier` |
| `quartier` (attribut) | Premier quartier (badges, marqueurs) |
| `statut` (attribut) | Dérivé du créneau via Carbon |
| `couvreQuartier(int)` | `contains` si relation chargée, sinon `exists()` |
| `hasCoordinates()` | lat ET lng non null |
| `rayonMetres(): int` | `rayon_metres` ou 1000 |
| `couvrePoint(lat,lng)` | `Geo::distanceKm*1000 <= rayon` ; `false` si pas de cercle |
| `filtrerPourLieu(alertes, quartierId, lat, lng): Collection` | `couvreQuartier OU couvrePoint`, en PHP (portable MySQL/SQLite, pas de fonction SQL distance) |
| `chevauche(quartierIds, debut, fin, ignoreId, niveau): bool` | Anti-doublon pivot : même niveau + non terminée (`fin >= now`) + chevauchement temporel + `whereHas` quartiers |
| `chevaucheGeo(lat,lng,rayon,debut,fin,ignoreId,niveau): bool` | Anti-doublon cercle : même filtre temporel, puis distance centres ≤ somme rayons (PHP) |
| Scopes | `validees()`, `actives()` (validée + créneau en cours), `programmees()`, `terminees()`, `parQuartier(ids)`, `byZone(villes)`, `niveau(NiveauAlerte)`, `recentes()` (order debut desc) |

Distance : `App\Support\Geo::distanceKm()` (Haversine, rayon terrestre 6371 km) — même formule que le JS de `coupures-create.blade.php`.

---

## 5. Service IA / Météo

### 5.1. `VigilanceAiService` (`app/Services/VigilanceAiService.php`) — approche HYBRIDE

**Classification déterministe** (`niveauPour`) :
1. `effective = T + pénalité humidité (si ≥60 % : +min(3, (H-60)*0.1)) + 2 si ≥3 jours de surchauffe`.
2. Seuils : `≥ seuil+6 → Rouge`, `≥ seuil+3 → Orange`, sinon `Jaune`.
3. Escalade d'un cran (plafond Rouge) si `H ≥ 70 %` ou `jours_surchauffe ≥ 3`.

`analyse()` ajoute `raison` lisible (ex. `Température 41,0°C, humidité 72% → vigilance rouge (seuil 35,0°C, seuils +3/+6).`).
`historiquePourQuartier(ids, jours=3)` : 100 % BDD — compte les jours distincts (`Y-m-d` du `debut`) où `temperature_actuelle ≥ seuil_temperature` sur alertes créées depuis N jours ; retourne aussi `temp_max`.
`seuilDefaut()` : `config('services.weather.seuil_defaut', 35)`.

**Rédaction LLM (Groq)** :
- `messagePersonnalise(Alerte, User)` → `messagePourContexte(contextePourAlerte(alerte), profils)`.
- `messagePourContexte(contexte, profils)` : `POST {base_url}/chat/completions`, model `openai/gpt-oss-120b`, `temperature 0.4`, `reasoning_effort low`, `max_tokens 700`. Prompt système : 3–4 phrases max, tutoiement, gestes adaptés au profil, numéros `190, 198, 0800 06 66 66`, sans titre/liste/formatage. **Fallback systématique** vers `messageDeSecours()` si clé absente, exception, HTTP fail ou contenu vide (log warning).
- `messageDeSecours()` : `Niveau sur quartiers — température mesurée X°C. Conseils profil. En cas de malaise, appelez le 190 ou le 198 ; plateforme canicule : 0800 06 66 66.` Catalogue profils : personne âgée / enfant / équipement médical, sinon message standard.

### 5.2. `WeatherService` (utilisé, non détaillé ici)

- `actuel(lat,lng): ?MeteoActuelle`, `actuelCache(lat,lng)` (cache court), `previsions(lat,lng, heures): MeteoHoraire[]`, `actuelPourQuartiers(Collection)`.
- `MeteoActuelle{temperature, humidite, ressentie, ...}`, `MeteoHoraire{heure, temperature, iconeMaterial(), ...}`.

---

## 6. Back office Alertes (gestionnaire / admin)

Contrôleur : `app/Http/Controllers/Back/AlerteController.php`. Préfixe `/admin`, middleware `auth, role:admin,gestionnaire`.

| Action | Route | Rôle |
|---|---|---|
| `index` | `GET /admin/alertes` (`back.alertes.index`) | Gestionnaire scopé à sa zone (`parQuartier(residence.quartier_id)`), admin tout |
| `create` | `GET /admin/alertes/create` | Formulaire + météo générale + `prefill` |
| `store` | `POST /admin/alertes` | `donnees()` + `user_id`, `quartiers()->sync(quartierIds())` |
| `show` | `GET /admin/alertes/{alerte}` | `authorizeAlerte()` + with `quartiers,user,validateur` |
| `edit` | `GET /admin/alertes/{alerte}/edit` | idem |
| `update` | `PUT/PATCH /admin/alertes/{alerte}` | idem |
| `destroy` | `DELETE /admin/alertes/{alerte}` | idem |
| `valider` | `PATCH /admin/alertes/{alerte}/valider` (`back.alertes.valider`) | `validee=true, validee_le=now, validee_par=user` → **publie côté habitant** |
| `prefill` | `POST /admin/alertes/prefill` (`back.alertes.prefill`) | JSON météo+IA, jamais 500 (`ok:false` si météo indispo) |

Détails `index` :
- Stats calculées sur le **périmètre seul** (indépendantes des filtres) : total, actives, programmées, terminées, brouillons, rouge/orange/jaune.
- Filtres d'affichage : `?quartier_id`, `?niveau`, tri `recent/ancien/niveau/statut/zone` (tri zone = sous-requête nom premier quartier). Pagination 10 + `withQueryString()`.
- Carte : un cercle par alerte géolocalisée (`lat,lng,rayon,niveau,couleur,titre,detail,edit`), sinon une entrée par (alerte × quartier géolocalisé) avec `rayon=null`.
- Centre carte : zone du gestionnaire si géolocalisée, sinon barycentre quartiers, sinon Tunis `[36.8065, 10.1815]`.

Sécurité gestionnaire :
- `quartiersAccessibles()` : que son quartier dans le formulaire ; `quartierIds()` **force** son quartier même si formulaire trafiqué ; `authorizeAlerte()` → 403 hors zone. `StoreAlerteRequest::prepareForValidation` impose aussi son quartier + centre le cercle sur son quartier si aucun point fourni.
- Admin : tous les quartiers.

`prefill` flow :
1. Quartiers résolus via `quartierIds()` (périmètre respecté).
2. Seuil = input ou `seuilDefaut()`.
3. Météo : override manuel (`temperature_actuelle` présente → `MeteoActuelle(..., source='manuel')`, **aucun appel WeatherAPI**) ; sinon point exact si lat/lng ; sinon `actuelPourQuartiers`.
4. Historique sur **tous** les quartiers visés + `analyse()` → niveau + raison.
5. Réponse `{ok, niveau, niveau_label, badge_classes, temperature, ressentie, humidite, source, historique, raison, message:null}` ; si `avec_message` ou `profil` → `messagePourContexte()` avec 0–1 profil.

Vues back : `resources/views/back/alertes/{index,create,edit,show}.blade.php` + `_form.blade.php` (quartiers checkboxes + carte cercle Leaflet + bouton pré-remplir) + `_meteo.blade.php` (panneau météo générale).

---

## 7. Front office Alertes (habitant)

Contrôleur : `app/Http/Controllers/Front/AlerteController.php`. Middleware `auth, role:habitant`. Routes : `GET /alertes` (`alertes.index`), `GET /alertes/{alerte}` (`alertes.show`).

### 7.1. `index` — deux vues, une page

`?vue=general` → vue générale, sinon (défaut) vue « mes lieux ».

**Vue « mes lieux » :**
1. `$lieux` du foyer (`orderByDesc est_principal, nom`), lieu consulté = `?lieu_id` s'il appartient au foyer, sinon `lieuPrincipal()`.
2. Origine : lat/lng du lieu → sinon résidence (compat) → sinon centroïde quartier interne.
3. `$visibles = filtrerPourLieu(validees + fin>=now + with quartiers, quartierId, lat, lng)` — **jamais les terminées, jamais les brouillons**.
4. `$actives` (tri gravité desc), `$aVenir` (tri debut asc), `$stats{actives,rouge,orange,jaune}`.
5. Message personnalisé de l'alerte la plus grave, **caché 30 min** par clé `alerte:{id}:profil:{profils|standard}` (≤1 appel LLM / profil / 30 min).
6. Météo actuelle + courbe 48 h (sparkline SVG 720×240, lissage Catmull-Rom→Bézier, grille 4 bandes, repères 6 h, séparateurs jours FR `dim./lun./...`, ligne seuil, infobulle Alpine.js).

**Vue « générales »** (`vueGenerale()`, privée) : toutes validées avec `fin >= now-7j` (actives tri gravité, programmées tri debut, terminées tri fin desc). **Aucun appel météo/IA**. Variables mode lieux neutralisées (collections vides).

### 7.2. `show(int id)`

`validees()->findOrFail(id)` — toute alerte validée consultable (même hors lieux, cohérent avec vue générale) ; brouillon → 404. Message personnalisé (cache profil), météo `actuelCache` au centre (cercle, sinon barycentre quartiers géolocalisés), marqueurs carte (cercle unique ou un point par quartier), `centre`.

Vues : `resources/views/front/alertes.blade.php` (bascule lieux/général, stats, bandeau principale, courbe, listes), `alertes-show.blade.php` (créneau, mesure, météo live, carte, message).

---

## 8. Module Lieux (habitant)

Contrôleur : `app/Http/Controllers/Front/LieuController.php`. Routes `/mes-lieux` (`lieux.index/store/update/destroy/principal`), middleware `auth, role:habitant`.

| Action | Route | Comportement |
|---|---|---|
| `index` | `GET /mes-lieux` | Lieux triés (principal d'abord), `principal` (ou premier), `apercus[id]` (météo+IA, `null` si pas de coords), `lieuxJson` pour Leaflet (id,nom,type,typeLabel,icone,adresse,lat,lng,temp,principal,updateUrl,deleteUrl,principalUrl), `types=LieuType::cases()`, `config{lieux,form(old),urls{store,geocodage.inverse},defaut:[36.8065,10.1815]}` |
| `store` | `POST /mes-lieux` | `lieux()->create(validated + est_principal=(count===0))` → premier lieu d'office principal |
| `update` | `PATCH /mes-lieux/{lieu}` | `autoriser()` puis `update` (quartier ré-évalué si point bougé) |
| `destroy` | `DELETE /mes-lieux/{lieu}` | `autoriser()` ; **refus si dernier lieu** (`Vous devez conserver au moins un lieu.`) ; transaction : si principal supprimé → plus petit `id` devient principal |
| `principal` | `PATCH /mes-lieux/{lieu}/principal` | Transaction : tous à `false`, cible à `true` |
| `autoriser()` | — | `abort_unless(lieu.user_id === auth.id, 403)` |

`apercu(Lieu, profils)` (privé) : `actuelCache` → `historiquePourQuartier(quartier_id)` → `analyse()` → conseil LLM **caché 30 min** par `lieu:{id}:conseil:{niveau}:{tempArrondie}:profil:{key}` → `{temperature,ressentie,humidite,vent,condition,ville,niveau,niveauLabel,badgeClasses,couleur,icone,conseil}`. Fallback secours si Groq indispo (via service).

Inscription : `Auth\RegisteredUserController@enregistrerLieu()` crée le premier lieu (`nom=lieu_nom|Domicile`, `type=Domicile`, adresse/lat/lng du formulaire, `est_principal=true`, quartier auto). Seul le gestionnaire reçoit `residence_id` ; l'habitant décrit son foyer **uniquement** par lieu.

Vue : `resources/views/front/lieux/index.blade.php` (liste + carte Leaflet + formulaire create/update, géocodage inverse).

Autres usages des lieux : `Front\CoupureController` (`lieuxJson` pour signalement), `StoreCoupureRequest::viaLieu()` (`lieu_id` appartenant au foyer), `Back\DashboardController` (compte `Lieu::count()` / par quartier).

---

## 9. Validation (FormRequests)

### 9.1. `StoreAlerteRequest` (`app/Http/Requests/StoreAlerteRequest.php`)

- `prepareForValidation` : gestionnaire → force son quartier + centre cercle si absent ; sinon normalise `quartier_ids` (tableau OU string `1,2` → `int[]`), `source_meteo` défaut `manuel`.
- Règles : titre 3–150, niveau in jaune/orange/rouge, `quartier_ids` nullable array + `exists:quartiers`, lat `-90/90`, lng `-180/180`, rayon 100–50000, debut `date` requis, fin `date+after:debut`, seuil 30–55, T −10–60, ressentie −10–70, humidité 0–100, source in weatherapi/manuel, message max 2000.
- `withValidator` : fin>début (message dédié), point complet (lat ET lng, sinon erreur sur le champ manquant), **ciblage obligatoire** (quartier OU point), anti-doublon (quartier via `chevauche` + geo via `chevaucheGeo`, même niveau, `ignoreId` depuis route `alerte|id` pour l'update) → `Une alerte de même niveau couvre déjà cette zone sur ce créneau.` Skip anti-doublon si erreurs préalables sur ciblage/dates/niveau/coords.

### 9.2. `PredictAlerteRequest`

Même normalisation + `avec_message` booléen, `temperature_actuelle` = flag override manuel, `profil` in `ProfilVulnerabilite::persistables()`. Vérifie point complet + cible obligatoire. Pas d'anti-doublon (pré-remplissage seul).

### 9.3. `StoreLieuRequest` (`app/Http/Requests/StoreLieuRequest.php`)

Défauts : `type=domicile`, `nom=Domicile`. Règles : nom 2–120 requis, type in `LieuType::valeurs()`, adresse nullable 3–255, **lat/lng `required`** (`Posez le point de ce lieu sur la carte.`), bornes −90/90 et −180/180. Quartier jamais validé (auto).

---

## 10. Routes récapitulatives

```
# Habitant (auth + role:habitant)
GET  /alertes            → Front\AlerteController@index  (alertes.index)
GET  /alertes/{alerte}   → Front\AlerteController@show   (alertes.show)
GET  /mes-lieux          → Front\LieuController@index    (lieux.index)
POST /mes-lieux          → Front\LieuController@store    (lieux.store)
PATCH /mes-lieux/{lieu}/principal → Front\LieuController@principal (lieux.principal)
PATCH /mes-lieux/{lieu}  → Front\LieuController@update   (lieux.update)
DELETE /mes-lieux/{lieu} → Front\LieuController@destroy  (lieux.destroy)

# Back (auth + role:admin,gestionnaire, préfixe /admin, nom back.)
GET   /admin/alertes              → Back\AlerteController@index
GET   /admin/alertes/create       → Back\AlerteController@create
POST  /admin/alertes              → Back\AlerteController@store
POST  /admin/alertes/prefill      → Back\AlerteController@prefill (back.alertes.prefill)
GET   /admin/alertes/{alerte}     → Back\AlerteController@show
GET   /admin/alertes/{alerte}/edit→ Back\AlerteController@edit
PUT   /admin/alertes/{alerte}     → Back\AlerteController@update
DELETE /admin/alertes/{alerte}    → Back\AlerteController@destroy
PATCH /admin/alertes/{alerte}/valider → Back\AlerteController@valider (back.alertes.valider)

# Partagé
GET /geocodage/inverse → GeocodingController@reverse (geocodage.inverse, auth)
GET /dashboard         → Front\DashboardController@index (bandeau alerte + origine lieu)
```

---

## 11. Seeders, factories, tests

- Factory : `database/factories/AlerteFactory.php` — `definition()` (titre, niveau aléatoire, seuil 34–38, T 30–44, ressentie 32–47, H 30–85, source manuel, debut −1j/+2j, fin +6–48h, **sans ciblage par défaut**, `validee=false`) + states : `geo(lat,lng,rayon=1000)`, `cible(...Quartier)` (`hasAttached`), `sansCible()`, `seuil()`, `creeePar()`, `valideePar()`, `validee()`, `brouillon()`, `active()`, `programmee()`, `terminee()`, `jaune()/orange()/rouge()`.
- Seeder : `database/seeders/AlerteSeeder.php` — 8 alertes idempotentes (`updateOrCreate` par titre) couvrant rouge active cercle 1,5 km Centre-Ville, orange programmée Berges, jaune terminée, orange active multi-quartiers sans cercle, rouge brouillon (back seul), jaune active API, orange active cercle sans quartier (proximité pure), rouge terminée historique. Sans appel réseau.
- Tests :
  - `tests/Unit/NiveauAlerteTest.php` — règles de classification (seuils, humidité, historique, escalade, `plusGrave`).
  - `tests/Unit/AlerteGeoTest.php` — `couvrePoint`, `filtrerPourLieu`, `chevaucheGeo`, rayon défaut.
  - `tests/Feature/Front/AlerteFrontTest.php` — visibilité habitant (validée vs brouillon, filtrage lieu, vue générale).
  - `tests/Feature/Back/AlerteManagementTest.php` — CRUD, périmètre gestionnaire, validation, anti-doublon.
  - `tests/Feature/TmpAlertesRender2Test.php` — rendu vues.

---

## 12. Cache,FallBack & cas limites

- Cache LLM 30 min : `alerte:{id}:profil:{key}` (front alertes + dashboard) et `lieu:{id}:conseil:{niveau}:{temp}:{profil}` (lieux). Clé inclut le profil du foyer → pas de fuite inter-profils.
- `prefill` ne renvoie jamais 500 ; `meteoGenerale()` ne lève jamais (renvoie `[null,null]`).
- Gestionnaire sans résidence/quartier : `quartierIds()` retombe sur les ids du formulaire (pas de crash) ; carte centrée sur barycentre/Tunis.
- Habitant sans lieu : `lieuPrincipal()` → `null` → origine via résidence/quartier → sinon météo/carte masquées, listes vides mais page OK.
- Alerte sans cible : invisible habitant (filtre), éditable en back (erreur seulement à la sauvegarde si toujours sans cible).
- Fuseaux : statuts calculés via `Carbon::now()` (config app) ; comparaisons temporelles `chevauche` en PHP/Carbon (portable SQLite/MySQL).

---

## 13. Index des fichiers

```
app/Models/Alerte.php                              # modèle, scopes, geo, anti-doublon
app/Models/Lieu.php                                # modèle, auto-quartier, hasCoordinates
app/Models/User.php                                # lieux(), lieuPrincipal(), profils
app/Enums/NiveauAlerte.php | StatutAlerte.php | LieuType.php
app/Http/Controllers/Back/AlerteController.php     # CRUD + valider + prefill + périmètre
app/Http/Controllers/Front/AlerteController.php    # index (lieux/général) + show + courbe SVG
app/Http/Controllers/Front/LieuController.php      # CRUD lieux + apercus météo/IA
app/Http/Controllers/Front/DashboardController.php # bandeau alerte + origine()
app/Http/Controllers/Auth/RegisteredUserController.php # enregistrerLieu()
app/Http/Requests/StoreAlerteRequest.php | PredictAlerteRequest.php | StoreLieuRequest.php
app/Services/VigilanceAiService.php                # seuils + Groq + secours
app/Services/WeatherService.php                    # actuel/cache/prévisions
app/Support/Geo.php                                # haversine
database/migrations/2026_10_07_000001_create_alertes_table.php
database/migrations/2026_10_07_000002_create_alerte_quartier_table.php
database/migrations/2026_10_08_000001_add_geo_targeting_to_alertes_table.php
database/migrations/2026_10_09_000001_create_lieux_table.php
database/migrations/2026_10_09_000002_backfill_lieux_from_residences.php
database/factories/AlerteFactory.php | database/seeders/AlerteSeeder.php
resources/views/back/alertes/{index,create,edit,show,_form,_meteo}.blade.php
resources/views/front/alertes.blade.php | alertes-show.blade.php
resources/views/front/lieux/index.blade.php | front/dashboard.blade.php
routes/web.php                                     # §10
tests/Unit/{NiveauAlerteTest,AlerteGeoTest}.php
tests/Feature/{Front/AlerteFrontTest,Back/AlerteManagementTest,TmpAlertesRender2Test}.php
```

> Pour toute modification : garder le contrat « quartier invisible habitant », le gate `validee`, le ciblage obligatoire, l'anti-doublon par niveau, et le cache LLM par profil.
