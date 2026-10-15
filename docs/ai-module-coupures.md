# Module IA « Coupures » (Module 2)

> Document de référence du module IA de risque de coupure. Décrit le **partage
> des responsabilités** entre Laravel (orchestrateur) et le microservice Python
> (toute la logique IA), les **contrats** à respecter, la **boucle
> d'entraînement**, et l'**audit** de la migration (conservé / retiré / ajouté).

## 1. Principe directeur

**Toute la logique d'intelligence artificielle vit dans le microservice Python
`ai-service/` (FastAPI + scikit-learn).** Laravel n'est qu'un **orchestrateur** :

- il collecte les **mesures brutes** (par zone : historique de coupures, météo,
  activité, canicule) ;
- il envoie un **contexte brut** au service — **aucun feature engineering en PHP** ;
- il expose le résultat en UI (barres de probabilité, facteurs, carte) ;
- il garde un **repli minimal** : règles déterministes + détection d'anomalie
  fixe, pour fonctionner service éteint.

Le modèle coupures vit dans un **dossier dédié**, isolé du module canicule :

- code : `ai-service/app/coupures/` ;
- artefacts : `ai-service/artifacts/coupures/` (le canicule garde `artifacts/`).

Ce découpage garantit que le modèle et son explicabilité évoluent en Python sans
toucher au framework, tout en préservant un fonctionnement dégradé sûr.

## 2. Architecture

```
Laravel (orchestrateur)                            ai-service (Python, port 8001)
  CoupureRiskAiService (règles, repli) ─┐
  Requêtes Eloquent (coupures, zones)  ─┤   POST /coupures/risk       → score, p24/p48, facteurs
  UI Blade + Leaflet + SVG             ─┼─  POST /coupures/anomalies  → incidents (DBSCAN + Poisson)
  CoupureAiClient (transport robuste)  ─┤   POST /coupures/etr        → quantiles de rétablissement
  Commandes artisan (snapshot/export…) ─┘   GET  /coupures/insights   → modèle, dépendance partielle
                                             GET  /health, /model-info
                                             POST /retrain-coupures    (X-AI-TOKEN)
```

Le transport est `App\Services\Ai\CoupureAiClient` (jamais d'exception, jamais
d'attente au-delà du timeout, `null` sur toute erreur). En cas
d'indisponibilité, tout retombe sur `CoupureRiskAiService` (règles PHP) et la
détection d'anomalie fixe (≥ 3 signalements en 120 min).

## 3. Contrats (à ne pas casser)

1. **Contexte brut uniquement** : Laravel envoie les mesures par zone
   (`coupures_7j`, `coupures_14j`, `part_chaleur_14j`, `heures_depuis_derniere`,
   `en_cours`, `confirmations_total`, `temp`, `ressentie`, `heat_index`,
   `max_24h`, `max_48h`, `canicule_niveau`, `jours_chauds_consecutifs`, `heure`,
   `jour_semaine`, `nb_logements_zone`, `nb_residences`). Aucune feature
   calculée côté PHP.
2. **Plancher de sécurité** : `score_final = max(score_modele, score_regles)`. Le
   modèle peut **escalader**, jamais descendre sous les règles PHP — le calcul du
   `max()` est refait **côté Laravel** (défense en profondeur : on ne fait pas
   confiance au `score_final` renvoyé par Python).
3. **Paliers `NiveauRisque` inchangés** : `≥ 75` critique, `≥ 50` eleve,
   `≥ 25` modere, sinon faible. Ces seuils ne dépendent pas du modèle.
4. **Jamais d'erreur 500 / jamais d'attente infinie** : pages publique et back,
   risque par zone, anomalies et ETR fonctionnent service éteint (mode « règles »).
5. **Zone = quartier interne** ; `quartier_id` **nullable** (une coupure peut
   cibler un point sans quartier) ; ciblage obligatoire (quartier **ou** point) ;
   anti-doublon (zone, rue, créneau) ; **périmètre gestionnaire strict**
   (sa zone uniquement).
6. **Aucune API IA externe au runtime** avec la config par défaut.
7. **Token partagé** : en-tête `X-AI-TOKEN` commun Laravel ↔ Python ;
   `/retrain-coupures` et `/reload` sont protégés.
8. **Forme de retour de `score()` conservée** : les clés historiques
   (`quartier, score, niveau, raison, facteurs, frequence, en_cours, temperature`)
   restent présentes ; on **ajoute** (`p24, p48, score_modele, score_regles,
   niveau_modele, niveau_regles, desaccord, source, model_version`).

## 4. Configuration

| Variable (`.env`)              | Défaut                     | Rôle |
|--------------------------------|----------------------------|------|
| `AI_SERVICE_ENABLED`           | `true`                     | Active l'appel au microservice (mettre `false` en test/CI). |
| `AI_SERVICE_URL`               | `http://127.0.0.1:8001`    | URL du service Python. |
| `AI_SERVICE_TOKEN`             | *(vide)*                   | Clé partagée (`X-AI-TOKEN`). |
| `AI_SERVICE_TIMEOUT`           | `2.0`                      | Délai max (s) d'un appel unitaire. |

Côté Python (`app/config.py`), les chemins sont isolés par module :

| Variable (`AI_*`)                    | Défaut                          | Rôle |
|--------------------------------------|---------------------------------|------|
| `AI_COUPURES_MODEL_PATH`             | `artifacts/coupures/model.joblib` | Artefacts du modèle coupures. |
| `AI_COUPURES_HISTORY_CSV`            | `data/coupures_history.csv`     | Historique d'entraînement (simulateur + réel). |
| `AI_COUPURES_REAL_CSV`               | `data/coupures_real.csv`        | Snapshots labellisés exportés par Laravel (réel, pondéré ×5). |
| `AI_COUPURES_ETR_REAL_CSV`           | `data/coupures_etr_real.csv`    | Coupures réelles résolues (durées) pour l'ETR. |

`phpunit.xml` force `AI_SERVICE_ENABLED=false` → la suite PHP s'exécute en mode
règles, sans dépendance au service.

## 5. Points d'API du microservice

| Méthode | Chemin                    | Description |
|--------:|---------------------------|-------------|
| POST    | `/coupures/risk`          | Liste de zones → score final 0–100, p24/p48, niveau modèle/règles, facteurs (top-3), désaccord. |
| POST    | `/coupures/anomalies`     | Signalements récents → incidents (centroïde, rayon, logements, confiance, sévérité, message FR). Repli règle fixe si cluster impossible. |
| POST    | `/coupures/etr`           | Contexte de coupure en cours → quantiles `p10/p50/p90` de temps de rétablissement + libellé FR. |
| GET     | `/coupures/insights`      | Version, date, métriques modèle vs règles, dépendance partielle `temp → p24/p48`. |
| POST    | `/retrain-coupures`       | Régénère le simulateur puis ré-entraîne **les artefacts coupures** en tâche de fond, recharge à chaud (token). |

Validation stricte (Pydantic v2, `extra="ignore"`) : bornes sur les températures,
les compteurs, `heure ∈ [0,23]`, `jour_semaine ∈ [0,6]` → sinon **422**. Sans
`X-AI-TOKEN` valide → **401**.

## 6. Le modèle de risque

- **Algorithme** : `HistGradientBoostingClassifier` calibré (isotonic ou sigmoid,
  choisi par log-loss), **monotonie imposée** (`monotonic_cst=+1`) sur `temp`,
  `heat_index`, `max_24h`, `max_48h`, `canicule_niveau` → hausse de température
  ne peut pas faire baisser la probabilité.
- **Deux cibles** : probabilité de coupure à **24 h** (`p24`) et à **48 h** (`p48`).
- **Score** : `score = round(100 * p24)` borné 0–100 ; `niveau` = palier
  `NiveauRisque` appliqué au `score_final`.
- **Facteurs** : top-3 contributions locales libellées FR (technique de
  permutation, même approche que le module canicule).
- **Fallback** : si l'artefact est absent/illisible, `risk.py` renvoie
  `available=false` et le service **retombe sur les règles** ; Laravel fait de
  même si le service est injoignable.

## 7. Boucle d'entraînement et de labellisation

```
ai:snapshot-risk  (horaire) ──► coupure_risk_snapshots (features, p24, p48, score, version)
ai:label-snapshots (horaire) ─► outcome_24h / outcome_48h (fenêtre demi-ouverte)
ai:export-coupures ───────────► ai-service/data/coupures_real.csv          (+ coupures_etr_real.csv)
scripts/simulate_grid.py ─────► ai-service/data/coupures_history.csv
scripts/train_coupures.py ────► artifacts/coupures/{model.joblib, metadata.json, report.json}
scripts/evaluate_coupures.py ─► artifacts/coupures/evaluation.json  (+ fusion dans metadata.json)
POST /retrain-coupures ───────► simulate + train + reload      (déclenché par ai:retrain-coupures)
```

**Stratégie de labels** : un snapshot est labellisé `outcome_XXh = true` si **au
moins une coupure** de la zone démarre dans la fenêtre **demi-ouverte**
`(captured_at, captured_at + XXh]`. La borne gauche est **exclue** pour ne pas
compter une coupure déjà déclarée au moment exact du snapshot (une panne connue
au présent ne doit pas être « prédite »).

**Pondération** : les lignes issues de **snapshots réels** (`source=reel`) sont
pondérées **×5** à l'entraînement ; les lignes du simulateur (`source=sim`)
gardent un poids 1. Les métriques sont rapportées séparément.

## 8. Détecteur d'anomalies (DBSCAN + Poisson)

- **Spatial** : DBSCAN avec distance **haversine** (`eps ≈ 500 m`,
  `min_samples = 3`) sur les signalements des **120 dernières minutes**
  (`statut ≠ resolue`), `sample_weight = 1 + 0.5·min(confirmations, 4)`. Les
  signalements sans coordonnées sont placés au **centroïde de leur zone** avec un
  poids réduit. Les clusters multi-quartiers sont autorisés.
- **Temporel** : baseline **Poisson** par zone sur 30 jours, même fenêtre 2 h.
  `P(X ≥ n)` est calculé en **pur `math` + `lgamma`** (aucune dépendance SciPy).
  Flag si `p < 0.01` **ou** si un cluster qualifie.
- **Sortie par incident** : `id, centroid, radius_m` (distance max au centroïde +
  150 m), `n_reports, n_confirmations, quartier_ids, logements_estimes`,
  `confidence 0..1`, `severity`, message FR
  `"N signalements en 120 min près de X : possible incident majeur non déclaré
  (~M logements concernés)"`.
- **Fallback** : règle fixe (≥ 3 en 120 min) si la géolocalisation ne permet pas
  de clusteriser.

## 9. Estimateur de temps de rétablissement (ETR)

- `HistGradientBoostingRegressor(loss="quantile")` pour `q = 0.1 / 0.5 / 0.9`
  sur la **durée des coupures résolues** ; features `type, heure, jour_semaine,
  temp, heat_index, proxy de charge de zone, confirmations`.
- **Tri garanti** `p10 ≤ p50 ≤ p90`. Libellé FR type `"retour estimé entre 1 h et
  3 h"`.
- Affiché **uniquement** pour les coupures au statut `en_cours`.

## 10. Audit de la migration

### Retiré du PHP

- Le calcul de score vivant **uniquement** en PHP : la formule est désormais
  portée à l'identique en Python (`app/coupures/rules.py`) et reproduite dans
  `CoupureRiskAiService::scoreDepuisContexte()` (référence + repli).
- La détection d'anomalie « règle fixe » comme **seule** implémentation : elle
  devient le **repli** du détecteur DBSCAN + Poisson.

### Conservé en PHP (repli minimal et référence de sécurité)

- `CoupureRiskAiService` — règles déterministes (fréquence, part chaleur,
  canicule, activité) : **plancher de sécurité** et **repli** service
  indisponible. Formule isolée dans `scoreDepuisContexte(array $contexte)` :
  **pure, sans base**, testable et réutilisable.
- Détection d'anomalie fixe (≥ 3 / 120 min) comme fallback.
- Tous les contrats métier (ciblage, anti-doublon, paliers, périmètre
  gestionnaire) : **inchangés**.

### Ajouté

- `ai-service/app/coupures/` : `rules`, `schemas`, `features`, `risk`,
  `anomalies`, `etr` (logique IA complète côté Python).
- `ai-service/scripts/{simulate_grid,train_coupures,evaluate_coupures}.py` +
  artefacts `ai-service/artifacts/coupures/{model.joblib, metadata.json,
  report.json, evaluation.json}`.
- `App\Services\Ai\CoupureAiClient` + endpoints `/coupures/*` + `/retrain-coupures`.
- Migration `coupure_risk_snapshots` + modèle `CoupureRiskSnapshot`.
- Commandes `ai:snapshot-risk`, `ai:label-snapshots`, `ai:export-coupures`,
  `ai:retrain-coupures` (les deux premières planifiées à l'heure).
- Fixture de parité `tests/fixtures/coupure_rules_cases.json` (source de vérité
  PHP/Python pour les **règles** uniquement — le modèle n'est pas répliqué en PHP).
- UI explicable : barres p24/p48, top-3 facteurs, badge « IA et règles en
  désaccord », mode « règles », carte des incidents (centroïde + rayon +
  logements), carte « Performance IA — Coupures », graphique SVG
  « Corrélation chaleur ↔ coupures ».

## 11. Métriques — modèle vs règles

Cible **24 h**, split **temporel** (aucune fuite), hold-out = 52 416 lignes,
jeu simulateur 2 ans (Tunisie, plusieurs zones). Comparaison faite sur le **même
hold-out**.

| Métrique            | Règles | Modèle | Plancher `max(modèle, règles)` |
|---------------------|:------:|:------:|:------------------------------:|
| ROC-AUC             | 0.6871 | 0.6873 | **0.6877** |
| PR-AUC              | 0.2179 | 0.1660 | **0.2187** |
| Brier (↓ mieux)     | 0.06185| **0.05602** | 0.06129 |
| Rappel `≥ eleve`    | 0.3049 | 0.0000 | **0.3049** |

**Lecture** : le modèle seul discrimine légèrement mieux et est **mieux calibré**
(Brier plus bas) que les règles, mais — la base de positifs étant faible (~6.6 %)
— il atteint rarement le seuil `eleve` (score ≥ 50) seul (rappel `≥ eleve` = 0).
Le **plancher `max(modèle, règles)`** est donc **indispensable** : il conserve le
rappel des règles (0.3049) tout en bénéficiant de la meilleure discrimination du
modèle (ROC-AUC et PR-AUC légèrement supérieurs).

Matrices par niveau (plancher, cible 24 h) — taux de positifs réels croissant
avec le palier :

| Niveau   | n      | positifs | taux |
|----------|:------:|:--------:|:----:|
| faible   | 45 648 | 1 735    | 0.038 |
| modere   | 3 686  | 547      | 0.148 |
| eleve    | 3 038  | 975      | 0.321 |
| critique | 44     | 26       | 0.591 |

ETR (n = 327 coupures résolues) : couverture `p10 = 0.086`, `p50 = 0.514`,
`p90 = 0.875` (une bande relativement serrée en bas, plus large vers le haut).

## 12. Exécution locale

```bash
# Modèle (depuis ai-service/)
python scripts/simulate_grid.py
python scripts/train_coupures.py
python scripts/evaluate_coupures.py
pytest -q

# Service
uvicorn app.main:app --port 8001        # ou: docker compose up -d --build

# Laravel (racine)
php artisan ai:snapshot-risk --dry-run  # vérif sans écrire
php artisan ai:export-coupures --dir=ai-service/data
php artisan ai:retrain-coupures         # export → POST /retrain-coupures → reload
php artisan test
```

Parcours de validation (service **arrêté**) : la page publique et le back
affichent le risque en **mode règles**, sans 500 ; **3 signalements proches** →
**un** incident avec rayon + logements sur la carte ; le graphique insights
montre un risque **croissant** avec la température.

## 13. Limites assumées

- Les étiquettes d'entraînement proviennent du **simulateur** (règles
  physiques) tant que les snapshots réels labellisés sont rares ; le modèle
  apprend d'abord à reproduire/affiner la règle. La pondération ×5 des lignes
  réelles corrige progressivement cet écart.
- Les snapshots réels étant « présents » (2026), ils tombent dans la moitié
  **test** du split temporel : c'est assumé et documenté (ils servent surtout à
  la calibration/au ré-entraînement futur).
- `artifacts/coupures/model.joblib` est un objet sérialisé (pickle) : **ne
  charger que des artefacts produits par `scripts/train_coupures.py`**
  (provenance de confiance).
- Le simulateur ne remplace pas des données d'exploitation réelles : il fournit
  un socle de démarrage à froid, à compléter par l'export réel.
