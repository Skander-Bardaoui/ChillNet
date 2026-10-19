# ChillNet — microservice IA « Alerte canicule »

Microservice **FastAPI + scikit-learn** qui porte TOUTE la logique IA du module
canicule. Laravel n'est qu'un orchestrateur : il envoie un **contexte brut**
(températures, humidité, prévisions horaires, historique) et reçoit un niveau
final, une explication et un message.

## Rôle dans l'architecture

```
Laravel (orchestrateur)                    ai-service (Python)
  WeatherService (mesures + prévisions)  ─┐
  Historique (base de données)           ─┤  POST /analyze  → niveau + explicabilité
  UI / validation / planification        ─┼─ POST /message  → message FR personnalisé
  Repli règles PHP (VigilanceAiService)  ─┘  POST /analyze/batch
                                             GET  /health, /model-info
                                             POST /reload, /retrain   (X-AI-TOKEN)
```

- **Classification** : `RandomForestClassifier` (pipeline scikit-learn) sur des
  features dérivées des mesures.
- **Plancher de sécurité** : le niveau final = max(modèle, règles). Le modèle
  peut **escalader**, jamais descendre sous les règles déterministes.
- **Repli** : si le service est arrêté, Laravel retombe sur les règles PHP et un
  message de secours — jamais d'erreur 500, jamais d'attente au-delà du délai.

## Démarrage

```bash
cd ai-service
python -m venv .venv && . .venv/Scripts/activate     # Windows
pip install -r requirements.txt
uvicorn app.main:app --port 8001
```

Ou en conteneur :

```bash
docker compose up -d --build
```

Sécurité : définir `AI_SERVICE_TOKEN` (en-tête `X-AI-TOKEN` partagé avec
Laravel). Vide = accès libre (développement uniquement).

## Entraînement / évaluation / ré-entraînement

```bash
# 1. Historique météo (Open-Meteo Archive) → data/weather_history.csv
python scripts/fetch_history.py --start 2015-01-01 --end 2024-12-31

# 2. Entraînement (écrit artifacts/model.joblib + metadata.json + report.json)
python scripts/train.py

# 3. Évaluation (métriques, matrice de confusion, CV 5 plis, vs règles)
python scripts/evaluate.py
```

- Étiquettes : `app/labeler.py` + `config/labeler.yaml` (règles documentées,
  bruit gaussien déterministe pour une frontière de décision réaliste).
- Découpage **chronologique** (aucune fuite temporelle).
- **Corrections gestionnaire** (`data/corrections.csv`, exportées par
  `php artisan ai:export-corrections`) réinjectées avec un poids ×5.
- Ré-entraînement à chaud depuis Laravel : `php artisan ai:retrain`
  (export → `POST /retrain` token-protégé → rechargement).

> ⚠️ `artifacts/model.joblib` est un objet sérialisé : ne charger que des
> artefacts produits par `scripts/train.py` (provenance de confiance).

## Points d'API

| Méthode | Chemin            | Description |
|--------:|-------------------|-------------|
| POST    | `/analyze`        | Contexte brut → niveau final, probabilités, confiance, top-3 facteurs, raison |
| POST    | `/analyze/batch`  | Liste de contextes → liste de résultats |
| POST    | `/message`        | Contexte → message FR (3-4 phrases, tutoiement) |
| GET     | `/health`         | Disponibilité + présence du modèle |
| GET     | `/model-info`     | Version, date, métriques, nb corrections |
| POST    | `/reload`         | Recharge l'artefact à chaud (token) |
| POST    | `/retrain`        | Lance l'entraînement en tâche de fond (token) |

La validation est stricte : température hors `[-10, 60]` °C, humidité hors
`[0, 100]` %, ou plus de 72 prévisions déclenchent une réponse **422**.

## Module 3 — Santé des points de fraîcheur (points + avis)

Prédit si un point de fraîcheur recevra **au moins 2 avis négatifs dans les
14 prochains jours**, à partir de ses caractéristiques et de l'historique de ses
avis (code `app/fraicheur/`, artefacts `artifacts/fraicheur/`). Détails :
[`docs/ai-module-fraicheur.md`](../docs/ai-module-fraicheur.md).

```bash
# 1. Météo réelle des 24 gouvernorats (export Open-Meteo .xlsx) → data/meteo_gouvernorats.csv
python scripts/import_meteo.py ../open-meteo-36.87N10.18E9m.xlsx

# 2. Simulation points + avis sur cette météo → data/fraicheur_history.csv
python scripts/simulate_fraicheur.py

# 3. Entraînement puis évaluation (hold-out temporel, comparaison aux règles)
python scripts/train_fraicheur.py
python scripts/evaluate_fraicheur.py
```

| Méthode | Chemin                | Description |
|--------:|-----------------------|-------------|
| POST    | `/fraicheur/sante`    | Points → p14, score final (plancher des règles), niveau, 3 facteurs, actions |
| GET     | `/fraicheur/insights` | Importances, dépendance partielle, métriques, évaluation |
| POST    | `/retrain-fraicheur`  | Ré-entraîne (sim + réels `data/fraicheur_real.csv`) puis recharge (token) |

## Limites assumées

- Les étiquettes d'entraînement proviennent de **règles expertes**, pas de
  données terrain : le modèle apprend à reproduire/affiner la règle, pas une
  vérité médicale. Les corrections gestionnaire réduisent cet écart.
- Les features `max_24h`, `max_48h` et `heure_pic` dépendent des prévisions
  horaires ; à l'entraînement (historique journalier) elles sont approchées par
  la température du jour.

## Tests

```bash
pytest -q
```
