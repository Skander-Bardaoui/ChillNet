# Module 2 — IA Coupures : spécification d'upgrade (rôle + brief)

> **Statut : SPÉCIFICATION (à implémenter).** Ce document décrit la cible : remplacer le `CoupureRiskAiService` déterministe actuel par un moteur **entraîné et explicable** tournant dans `ai-service/` (FastAPI + scikit-learn), en réutilisant les conventions du module canicule.
> Voir aussi : `docs/module-coupure-quartier.md` (état actuel du module), `docs/ai-module.md` (module IA canicule).

---

## ROLE

Tu es un **ingénieur senior Laravel 12 + Python**. Fais passer l'IA du **Module 2 (Coupures)** de ChillNet du `CoupureRiskAiService` déterministe actuel à un **moteur entraîné et explicable** tournant dans le Python `ai-service` (FastAPI + scikit-learn).

Si `ai-service/` existe déjà (module canicule) : **l'étendre et réutiliser ses conventions** (config, auth `X-AI-TOKEN`, Dockerfile, `health`/`model-info`/`reload`, artifacts, tests). Sinon : le créer avec la même structure.

**À lire d'abord :**
- `docs/module-coupure-quartier.md`
- `app/Services/CoupureRiskAiService.php`
- `app/Models/Coupure.php`, `Quartier.php`, `Residence.php`
- `app/Enums/NiveauRisque.php`
- `app/Http/Controllers/Back/CoupureController.php`, `Front/CoupureController.php`
- `tests/Unit/CoupureRiskAiServiceTest.php`, `tests/Feature/Front/CoupureRiskTest.php`

---

## STEP 0 — AUDIT

- Rapporter ce qui existe.
- Lancer la **suite de tests complète**, lister les échecs.
- **Corriger les bugs pré-existants d'abord.**

---

## CONTRATS (à conserver impérativement)

- **Zone = quartier** (interne, jamais choisi par l'habitant).
- **Ciblage obligatoire** (quartier **OU** point).
- **Anti-doublon** par (zone, rue, créneau).
- `quartier_id` **nullable**.
- **Périmètre gestionnaire strict**.
- Paliers `NiveauRisque` **inchangés** (`>=75 critique`, `>=50 eleve`, `>=25 modere`).
- La **forme de retour actuelle de `score()`** (`quartier, score, niveau, raison, facteurs, frequence, en_cours, temperature`) **reste**, avec **de nouvelles clés ajoutées**.
- Les pages ne renvoient **jamais 500** et n'attendent **jamais plus que le timeout** ; **repli sur les règles PHP** si le service est down.
- **Aucune API IA externe** au runtime.
- Les **tests existants continuent de passer**.

---

## SERVICE PYTHON (toute l'intelligence en Python)

### À ajouter

```
app/coupures/features.py
app/coupures/risk.py
app/coupures/anomalies.py
app/coupures/etr.py
app/coupures/rules.py       # portage du score PHP actuel
app/coupures/schemas.py
scripts/simulate_grid.py
scripts/train_coupures.py
scripts/evaluate_coupures.py
data/coupures_history.csv    # export depuis Laravel
tests/…
README (section)
```

### Endpoints (token requis)

| Endpoint | Rôle |
|---|---|
| `POST /coupures/risk` | Batch de zones avec contexte brut → par zone : `p24`, `p48`, `score 0-100`, `niveau_modele`, `niveau_regles`, `score_final = max(model, rules)`, `desaccord`, `facteurs` (top 3 facteurs locaux, libellés FR), `raison`, `model_version` |
| `POST /coupures/anomalies` | Signalements récents (`id, lat, lng, quartier_id nullable, created_at, statut, type, confirmations`) + résidences (`lat, lng, nombre_logements, quartier_id`) + centroïdes de zones → liste d'incidents |
| `POST /coupures/etr` | Contexte des coupures en cours → `p10/p50/p90` durée en minutes + libellé FR |
| `GET /coupures/insights` | Courbe de dépendance partielle température → risque, importances de features, métriques, version du modèle (pour le graphique back-office) |

---

## 1) Modèle de risque (`risk.py`, `train_coupures.py`)

- `HistGradientBoostingClassifier` avec `monotonic_cst=+1` sur `temp, heat_index, max_24h, max_48h, canicule_niveau`, **calibré** (isotonic **ou** sigmoid, choisi par log-loss).
- **Deux cibles** : démarrage de coupure sous **24 h** (`p24`) et **48 h** (`p48`).
- `random_state` **fixe**.
- **Features par (zone, timestamp)** :
  `coupures_7j`, `coupures_14j`, `part_chaleur_14j` (part surcharge+délestage), `heures_depuis_derniere`, `en_cours`, `confirmations_total`, `temp`, `ressentie`, `heat_index`, `max_24h`, `max_48h`, `canicule_niveau` (0..3 depuis alertes validées couvrant la zone), `jours_chauds_consecutifs`, `heure`, `jour_semaine`, `nb_logements_zone`, `nb_residences`.
  Valeurs manquantes imputées avec **défauts sûrs**.
- **Données d'entraînement** :
  - `scripts/simulate_grid.py` (graine fixe, **2 ans horaires**, plusieurs zones : charge croissante avec la température, intensité des coupures montant au-dessus d'un seuil de capacité, type `surcharge`/`délestage` plus probable quand il fait chaud, durées **lognormales** par type).
  - Fusionnées avec l'**historique réel** de `coupures_history.csv` et les **snapshots labellisés** (voir plus bas), **lignes réelles pondérées ×5**.
  - Colonne **`source`** (`sim|reel`) + **métriques rapportées séparément**.
- `score = round(100 * p24)` borné `0..100` ; **documenter le mapping** avec les paliers `NiveauRisque`.
- **Évaluation** : split **temporel**, ROC-AUC, PR-AUC, Brier score, courbe de calibration, recall au seuil **« eleve »**, matrice de confusion par niveau, et **comparaison contre le score des règles PHP** portées dans `rules.py` sur le même hold-out.

## 2) Détecteur d'anomalies (`anomalies.py`)

- **Spatial** : **DBSCAN** (haversine, `eps ≈ 500 m`, `min_samples 3`, `sample_weight = 1 + 0.5 * min(confirmations, 4)`) sur les signalements des **120 dernières minutes**, `statut != resolue`. Les signalements sans coordonnées utilisent le **centroïde de zone** (poids **0.5**). Les clusters peuvent **couvrir plusieurs quartiers**.
- **Temporel** : baseline **Poisson** par zone sur les **30 derniers jours** pour la même fenêtre de 2 h (lissée par le taux global) ; `p-value de surprise = P(X >= n)` ; flag si `p < 0.01` **ou** un cluster qualifie.
- **Sortie par incident** : `id, centroid, radius_m` (distance max au centroïde + 150), `n_reports, n_confirmations, quartier_ids, logements_estimes` (somme des `nombre_logements` des résidences dans le rayon), `confidence 0..1` (densité cluster + surprise Poisson + confirmations), `severity`, message FR :
  `"N signalements en 120 min près de X : possible incident majeur non déclaré (~M logements concernés)"`.
- **Fallback** : conserver l'ancienne règle fixe (`>=3` en 120 min par zone) quand le service est down.

## 3) Estimateur ETR (`etr.py`)

- `HistGradientBoostingRegressor(loss="quantile")` pour `q=0.1/0.5/0.9` sur la **durée des coupures résolues** (`debut` → `fin` ou moment de résolution), features : `type, heure, jour_semaine, temp, heat_index, proxy de charge de zone, confirmations`.
- Garantir `p10 <= p50 <= p90` (**tri**).
- Libellé du type `"retour estimé entre 1 h et 3 h"`.
- Entraîné sur **simulateur + coupures réelles résolues** (réelles ×5).
- **Affiché seulement** pour `statut en_cours`.

---

## CHANGEMENTS LARAVEL

- **`config/ai.php`** (réutiliser/étendre) : `service_url`, `token`, `timeout 2s`.
- **`App\Services\Ai\CoupureAiClient`** (`risk()`, `anomalies()`, `etr()`, `insights()`) : renvoie un **DTO ou `null`** à la moindre erreur, **ne lève jamais**.
- **`CoupureRiskAiService`** : garder les **règles PHP comme fallback et référence de sécurité** ; `scoresPourQuartiers()` et `detecterAnomalies()` appellent le **client d'abord** puis **repli PHP**. Ajouter aux tableaux retournés : `p24, p48, niveau_modele, niveau_regles, desaccord, source (modele|regles), model_version`. Anomalies : ajouter `centroid, radius_m, logements_estimes, confidence`.
- **Snapshots & labels automatiques** :
  - Migration `coupure_risk_snapshots` (`quartier_id, captured_at, features json, p24, p48, score, model_version, outcome_24h nullable bool, outcome_48h nullable bool, labeled_at nullable`).
  - Commandes : `ai:snapshot-risk` (planifiée horaire), `ai:label-snapshots` (horaire ; fixe les outcomes une fois 24 h/48 h écoulées en vérifiant les coupures dont `debut` tombe dans la fenêtre), `ai:export-coupures` (historique + snapshots labellisés → `ai-service/data`), `ai:retrain-coupures` (export → `POST /retrain` → reload).
- **UI back** (panneau risques + index/show coupures) : barres de probabilité (24 h/48 h), top 3 facteurs, badge **« IA et règles en désaccord »**, cartes d'incident avec **marqueur centroïde + cercle de rayon** sur la carte Leaflet et **« ~M logements concernés »**, graphique **« Corrélation chaleur ↔ coupures »** depuis `/coupures/insights` (admin/gestionnaire uniquement), et état discret **« mode règles »** quand le service est down.
- **UI front** (liste publique) : risque par zone (panneau existant, nouvelles données), **libellé ETR** sur les coupures en cours, panneau d'anomalies comme aujourd'hui. **Périmètre gestionnaire** appliqué aux risques, anomalies, insights.
- **Carte admin « Performance IA coupures »** : AUC, Brier, nombre de snapshots réels labellisés, version du modèle, date du dernier entraînement.

---

## TESTS

**Python :**
- features, **parité des règles** avec fixture partagée (`tests/fixtures/coupure_rules_cases.json`), **monotonie** (température croissante ne baisse jamais `p24`), scénario **DBSCAN** (3 signalements proches → 1 incident ; 3 éloignés → aucun), baseline **Poisson** (zone chargée non flaggée, zone calme flaggée), ordre **ETR** `p10<=p50<=p90`, contrats d'endpoints + **422** + token, smoke test d'entraînement sur simulateur.

**Laravel (offline, `Http::fake`) :**
- client succès/timeout/500/malformé → `null`, repli sur règles PHP, `score_final >= règles`, périmètre respecté pour le gestionnaire, labellisation des snapshots (outcome `true` quand une coupure démarre dans la fenêtre, `false` sinon), pages jamais 500, contrats **anti-doublon** et **ciblage** inchangés, tests existants verts. Test de **parité PHP/Python uniquement pour les règles**.

---

## DELIVERABLES

- Fichiers **complets** (pas de diffs) pour chaque fichier nouveau/modifié.
- `docs/ai-module-coupures.md` (architecture, comment simuler/entraîner/évaluer/ré-entraîner, stratégie de labels, limites du simulateur).
- À la fin : lancer `pytest`, la suite Laravel complète, `simulate_grid` + `train_coupures` + `evaluate_coupures`, et **rapporter** : résultats d'audit, kept/removed/added, la **table de métriques modèle vs règles** (AUC, Brier, recall à `eleve`), problèmes restants.

---

## ACCEPTANCE CRITERIA

- **Service arrêt :** les pages coupures (publique et back) fonctionnent en **mode règles**, **pas de 500**.
- **Test de monotonie** passe ; `score_final` **jamais inférieur** au score des règles.
- Une **rafale de 3 signalements proches** produit **un** incident avec **rayon** et **logements estimés** sur la carte.
- Le graphique **insights** montre un **risque croissant avec la température**.
- **Tous** les tests précédents et nouveaux passent.

---

## Rappel — état actuel (à remplacer)

- `app/Services/CoupureRiskAiService.php` : score déterministe 0–100 (fréquence 0–35 + type 0–20 + canicule 0–35 + actif 0–10), `detecterAnomalies()` (règle fixe ≥3 / 120 min), aucune dépendance réseau.
- `app/Enums/NiveauRisque.php` : paliers `fromScore` (≥75 / ≥50 / ≥25).
- Front `GET /coupures` (public) + signalement/confirmation habitant ; Back `/admin/coupures` CRUD + tableau de bord ; périmètre gestionnaire = `residence.quartier_id`.
- Tests existants : `tests/Unit/CoupureRiskAiServiceTest.php`, `tests/Feature/Front/CoupureRiskTest.php`, `tests/Feature/Front/CoupureSignalementTest.php`, `tests/Feature/Back/CoupureManagementTest.php`.
