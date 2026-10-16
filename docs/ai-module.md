# Module IA « Alerte canicule »

> Document de référence du module IA. Décrit le **partage des responsabilités**
> entre Laravel (orchestrateur) et le microservice Python (toute la logique IA),
> les **contrats** à respecter, et l'**audit** de la migration (ce qui a été
> conservé, retiré, ajouté).

## 1. Principe directeur

**Toute la logique d'intelligence artificielle vit dans le microservice Python
`ai-service/` (FastAPI + scikit-learn).** Laravel n'est qu'un **orchestrateur** :

- il collecte les **mesures brutes** (météo, historique, prévisions) ;
- il envoie un **contexte brut** au service — **aucun feature engineering en PHP** ;
- il expose le résultat en UI et trace la prédiction en base ;
- il garde un **repli minimal** : règles déterministes + message de secours.

Ce découpage garantit que le modèle et son explicabilité évoluent en Python sans
toucher au framework, tout en préservant un fonctionnement dégradé sûr.

## 2. Architecture

```
Laravel (orchestrateur)                         ai-service (Python, port 8001)
  WeatherService  (mesures + prévisions) ─┐
  Requêtes Eloquent (historique)         ─┤   POST /analyze         → niveau + explicabilité
  UI Bladewire/Alpine + validation       ─┼─  POST /analyze/batch   → liste de résultats
  VigilanceAiService (repli règles PHP)  ─┤   POST /message         → message FR
  Commandes artisan (export/retrain/…)   ─┘   GET  /health, /model-info
                                              POST /reload, /retrain  (X-AI-TOKEN)
```

Le transport est le trait `App\Services\Ai\AiServiceClient` (jamais d'exception,
jamais d'attente au-delà du timeout). En cas d'indisponibilité, tout retombe sur
`VigilanceAiService::niveauPour()` (règles) et `messageDeSecours()`.

## 3. Contrats (à ne pas casser)

1. **Contexte brut uniquement** : Laravel envoie `temperature`, `ressentie`,
   `humidite`, `previsions[]` (`heure`, `temperature`), `historique`
   (`jours_surchauffe`, `temp_max`), `seuil_temperature`, `mois`, `profils[]`,
   `lieu_type`, `quartier`, `alerte_id`. Aucune feature calculée côté PHP.
2. **Plancher de sécurité** : `niveau_final = max(modèle, règles)`. Le modèle peut
   **escalader**, jamais descendre sous les règles PHP — vérifié côté Python *et*
   côté Laravel (défense en profondeur).
3. **Jamais d'erreur 500 / jamais d'attente infinie** : `prefill`, `aperçu`,
   dashboard et listes fonctionnent service éteint (repli « règles »).
4. **Aucune API IA externe au runtime** avec la config par défaut. Groq reste une
   option explicite (`AI_DRIVER=groq`, désactivée par défaut).
5. **Cloisonnement habitant** : le quartier reste invisible aux habitants ; seules
   les alertes `validee=true` sont visibles. (Comportement inchangé par ce module.)
6. **Token partagé** : en-tête `X-AI-TOKEN` commun Laravel ↔ Python ; `/retrain`
   et `/reload` sont protégés.

## 4. Configuration

| Variable (`.env`)        | Défaut                  | Rôle |
|--------------------------|-------------------------|------|
| `AI_SERVICE_ENABLED`     | `true`                  | Active l'appel au microservice (mettre `false` en test/CI). |
| `AI_SERVICE_URL`         | `http://127.0.0.1:8001` | URL du service Python. |
| `AI_SERVICE_TOKEN`       | *(vide)*                | Clé partagée (`X-AI-TOKEN`). Vide = accès ouvert (dev). |
| `AI_SERVICE_TIMEOUT`     | `2.0`                   | Délai max (s) d'un appel unitaire. |
| `AI_DRAFT_HOUR`          | `6`                     | Heure de la commande `ai:predict-daily`. |

Côté Python : `app/config.py` (`AI_SERVICE_TOKEN`, chemins modèle/data),
`config/labeler.yaml` (seuils de labellisation).

## 5. Points d'API du microservice

| Méthode | Chemin            | Description |
|--------:|-------------------|-------------|
| POST    | `/analyze`        | Contexte brut → niveau final, probabilités, confiance, top-3 facteurs, raison (message si demandé). |
| POST    | `/analyze/batch`  | Liste de contextes → liste de résultats. |
| POST    | `/message`        | Contexte → message français (3-4 phrases, tutoiement, sans markdown). |
| GET     | `/health`         | Disponibilité + présence du modèle (ouvert). |
| GET     | `/model-info`      | Version, date, métriques, nb de corrections (token). |
| POST    | `/reload`         | Recharge à chaud l'artefact (token). |
| POST    | `/retrain`        | Lance l'entraînement en tâche de fond puis recharge (token). |

Validation stricte : température ∈ [-10, 60] °C, humidité ∈ [0, 100] %,
prévisions ≤ 72, `jours_surchauffe` ≤ 30 → sinon **422**.

## 6. Boucle de retour d'expérience

1. Le gestionnaire crée une alerte ; `AiPrediction` trace contexte, niveau
   modèle, niveau règles, niveau final et source.
2. `php artisan ai:export-corrections` exporte les niveaux **corrigés** vers
   `ai-service/data/corrections.csv`.
3. `php artisan ai:retrain` → export puis `POST /retrain` (token) → le service
   réentraîne en réinjectant les corrections (poids ×5) et recharge à chaud.
4. Le bloc « Performance IA » du dashboard (admin) lit `GET /model-info`.

`ai:predict-daily` (06:00) prépare des **brouillons** pour les quartiers dont le
pic 24 h dépasse le seuil — **jamais de publication automatique**.

## 7. Audit de la migration

### Retiré du PHP (logique IA déplacée côté Python)

- Dépendance `rubix/ml` (composer.json + lock).
- `App\Services\Ai\{VigilanceModel, FeatureBuilder, ModelEvaluator, MessageGenerator,
  ExpertLabeler, DatasetBuilder}` — classification, feature engineering,
  labellisation, évaluation et rédaction sont désormais en Python.
- Commandes `ai:train`, `ai:evaluate`, `ai:fetch-history` (→ `scripts/train.py`,
  `scripts/evaluate.py`, `scripts/fetch_history.py`).
- Tests Rubix (`tests/Unit/Ai/*`, `tests/Feature/Ai/AiTrainCommandTest.php`) et
  artefacts `storage/app/ai/*`, `database/ai/weather_history.csv`.

### Conservé en PHP (repli minimal et référence de sécurité)

- `VigilanceAiService::niveauPour()` — règles déterministes : **plancher de
  sécurité** et **repli** service indisponible. Portées à l'identique en Python
  (`app/rules.py`), parité garantie par la fixture partagée.
- `VigilanceAiService::messageDeSecours()` — message texte déterministe de secours.
- `WeatherService` — mesures et prévisions (données brutes).
- Groq — option explicite `AI_DRIVER=groq` (désactivée par défaut).

### Ajouté

- `ai-service/` : service FastAPI complet (features, règles, labellisation,
  modèle, NLG, entraînement/évaluation, artefacts, Docker).
- `App\Services\Ai\AiServiceClient` + `config/ai.php` : transport robuste.
- Migration `ai_predictions` (contexte JSON, niveaux modèle/règles/final, source).
- Commandes `ai:export-corrections`, `ai:retrain`, `ai:predict-daily`.
- Fixture de parité `tests/fixtures/rules_cases.json` (source de vérité PHP/Python).
- Tests : `ai-service/tests/*` (pytest) et `tests/Unit/Ai/*` (PHPUnit).

## 8. Métriques — modèle vs règles

Découpage **chronologique** (aucune fuite), jeu d'échantillon (Tunisie, mai-sept).

| Métrique          | Modèle (RF) | Règles | Écart |
|-------------------|:-----------:|:------:|:-----:|
| Exactitude        | **0.954**   | 0.879  | +0.075 |
| F1 macro          | **0.789**   | 0.483  | +0.306 |
| Rappel `rouge`    | **0.975**   | 0.910  | +0.065 |
| Rappel `orange`   | 0.556       | 0.111  | +0.444 |
| Rappel `jaune`    | 0.800       | 1.000  | −0.200 |

Le gain principal porte sur l'**orange** (sous-détecté par les règles) et la
robustesse globale, sans sacrifier le rappel `rouge` (sécurité).

## 9. Limites assumées

- Les étiquettes d'entraînement proviennent de **règles expertes**, pas de
  données médicales : le modèle apprend à reproduire/affiner la règle. Les
  corrections gestionnaire réduisent cet écart.
- La classe `normal` est **absente du jeu de test** (fin d'été) → son rappel est
  nul statistiquement ; elle n'existe pas côté règles (`jaune` est le plancher).
- `max_24h`, `max_48h`, `heure_pic` sont approchées par la température du jour à
  l'entraînement (historique journalier, prévisions horaires absentes).
- `artifacts/model.joblib` est un objet sérialisé (pickle) : **ne charger que des
  artefacts produits par `scripts/train.py`** (provenance de confiance).

## 10. Exécution locale

```bash
# Service Python
cd ai-service && pip install -r requirements.txt
uvicorn app.main:app --port 8001          # ou: docker compose up -d --build

# Laravel (.env)
AI_SERVICE_URL=http://127.0.0.1:8001
AI_SERVICE_ENABLED=true
AI_SERVICE_TOKEN=une-cle-partagee          # + côté Python AI_SERVICE_TOKEN

php artisan serve
```

Tests : `cd ai-service && pytest -q` puis `php artisan test` (depuis la racine).
