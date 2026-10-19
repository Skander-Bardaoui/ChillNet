# Module IA Signalements

Le modèle de catégories des signalements utilise le microservice Python, sans
modifier le fonctionnement du module Coupures. Laravel orchestre l'appel et
conserve son classifieur local comme repli.

## Périmètre

- Code du modèle et préparation des exemples : `ai-service/app/signalements/`.
- Historique étiqueté : `ai-service/data/signalements_urgence.csv`.
- Exemples complémentaires pour la catégorie peu représentée : `ai-service/data/signalements_categories.csv`.
- Modèle et métadonnées : `ai-service/artifacts/signalements/`.
- Entraînement et évaluation : `ai-service/scripts/train_signalements.py` et `ai-service/scripts/evaluate_signalements.py`.
- Contrat d'inférence : `POST /signalements/classify`.
- Ré-entraînement : `php artisan ai:retrain-signalements` ou
  `POST /retrain-signalements`.
- Le modèle prédit les catégories métier `fuite`, `panne_locale`,
  `personne_vulnerable` et `autre`. Les valeurs historiques `panne locale` et
  `coupure` sont converties en `panne_locale`.
- L'urgence, ses indicateurs et le tri des signalements ne sont pas changés.

## Utilisation

Depuis `ai-service/` :

```bash
python scripts/train_signalements.py
python scripts/evaluate_signalements.py
```

L'inférence prend `{ "description": "..." }` et renvoie `categorie`,
`confiance` (0–100), les `probabilites` par classe et `model_version`.
L'endpoint est protégé par `X-AI-TOKEN` quand `AI_SERVICE_TOKEN` est configuré.
Laravel retourne le résultat local existant si le microservice est désactivé,
indisponible, ou si sa réponse ne contient pas une catégorie valide.

`POST /retrain-signalements` lance l'entraînement en tâche de fond et recharge
le modèle après succès. Les chemins peuvent être remplacés par
`AI_SIGNALEMENTS_MODEL_PATH`, `AI_SIGNALEMENTS_METADATA_PATH`,
`AI_SIGNALEMENTS_REPORT_PATH`, `AI_SIGNALEMENTS_HISTORY_CSV` et
`AI_SIGNALEMENTS_EXAMPLES_CSV`.
