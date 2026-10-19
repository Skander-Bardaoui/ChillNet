# Module IA « Santé des points de fraîcheur » (Module 3)

> Document de référence du modèle IA du module 3 (points de fraîcheur + avis).
> Décrit la **question posée au modèle**, les **données** (dont la météo réelle
> Open-Meteo), le **partage des responsabilités** Laravel / Python, les
> **contrats**, la **boucle d'entraînement** et les **résultats d'évaluation**.

## 1. La question posée au modèle

> **« Ce point de fraîcheur va-t-il recevoir au moins deux avis négatifs dans
> les 14 prochains jours ? »**

Le modèle **relie les deux tables du module** : les caractéristiques du
**point** (type, capacité, équipements, horaires, ancienneté) expliquent
pourquoi ses **avis** se dégradent (note en baisse, part de négatifs, plaintes
récurrentes, affluence ressentie), sous la pression de la **chaleur**.

Il complète l'analyse de sentiment existante :

| | Analyse de sentiment (`SentimentAvisService`) | Santé prédite (`SantePointService`) |
|---|---|---|
| Nature | **Constat** : le point est déjà mal noté | **Anticipation** : le point VA se dégrader |
| Méthode | Lexique + note, seuils | Classifieur entraîné + règles de sécurité |
| Résultat | Points mal notés récurrents | Risque 0–100, niveau, 3 raisons, actions |

Un avis est **négatif** si sa note est ≤ 2 **ou** si son commentaire est négatif.

## 2. Architecture

```
Laravel (orchestrateur)                              ai-service (Python, port 8001)
  SantePointService (contexte, règles, repli) ─┐
  Requêtes Eloquent (points, avis groupés)    ─┤   POST /fraicheur/sante     → p14, score, niveau, facteurs, actions
  FraicheurAiClient (transport robuste)       ─┼─  GET  /fraicheur/insights  → importances, métriques, évaluation
  Back-office Blade (liste + fiche)           ─┤   POST /retrain-fraicheur   (X-AI-TOKEN)
  Commandes artisan (snapshot/label/export)   ─┘
```

- Code Python : `ai-service/app/fraicheur/` (`features.py`, `rules.py`,
  `model.py`, `sante.py`, `schemas.py`).
- Artefacts dédiés : `ai-service/artifacts/fraicheur/` (`model.joblib`,
  `metadata.json`, `report.json`, `evaluation.json`).
- **Aucun LLM** : Groq a été retiré du module 3 (sentiment, synthèse et conseil
  sont désormais déterministes et locaux).

## 3. Les features (contexte brut envoyé par Laravel)

| Groupe | Features |
|---|---|
| Le point | `type_code`, `capacite`, `climatise`, `ombrage`, `eau_potable`, `accessible_pmr`, `heures_ouverture_jour`, `anciennete_jours`, `a_photo` |
| Ses avis | `note_moyenne` (bayésienne, prior 3,5 × 3), `nb_avis_30j`, `note_moyenne_30j`, `evolution_note_30j` (vs J-120…J-30), `part_negatifs_30j`, `jours_depuis_dernier_negatif` (≤ 90), `part_affluence_forte_30j`, `sentiment_moyen_30j`, `plaintes_proprete_30j`, `plaintes_chaleur_30j`, `plaintes_equipements_30j` |
| Le contexte | `temperature` (live, °C), `canicule_niveau` (0–3 = max(vigilance active, seuils 35/38/41 °C)), `mois` |

Les plaintes sont comptées sur les avis négatifs des 30 jours dont le
commentaire évoque le thème (thèmes de `SentimentAvisService::THEMES`).

Contraintes de **monotonie** du classifieur : plus d'avis négatifs, de plaintes
ou de canicule ne peut jamais **baisser** le risque ; une meilleure note ne peut
jamais l'**augmenter**.

## 4. Données d'entraînement

### 4.1 Météo réelle (Open-Meteo)

`scripts/import_meteo.py` convertit l'export Open-Meteo (`.xlsx`, sans
dépendance externe) en `data/meteo_gouvernorats.csv` : **météo journalière
réelle des 24 gouvernorats** (température max/min/moyenne, pluie, vent), du
01/01/2024 au 14/10/2026 (24 432 mesures).

```bash
python scripts/import_meteo.py ../open-meteo-36.87N10.18E9m.xlsx
```

### 4.2 Simulation points + avis

`scripts/simulate_fraicheur.py` (graine fixe) simule 120 points répartis dans
les gouvernorats (40 % Grand Tunis) qui vivent la **vraie météo** :

- fréquentation croissante avec la chaleur (salles climatisées, fontaines),
  divisée par la pluie pour les espaces extérieurs → affluence ressentie ;
- **dégradations persistantes** (~12 jours) : saleté (foule), panne de
  climatisation / eau (chaleur), équipements cassés (âge, qualité) ;
- **avis** : note, sentiment, affluence, thème de la plainte.

Une ligne tous les 3 jours par point (limite l'autocorrélation) →
`data/fraicheur_history.csv` (33 857 lignes, taux de positifs 30,6 %).

### 4.3 Données réelles

Les snapshots labellisés de la production sont exportés dans
`data/fraicheur_real.csv` (jamais écrasé par la simulation) et **sur-pondérés
×5** à l'entraînement.

## 5. Modèle et évaluation

`scripts/train_fraicheur.py` : `HistGradientBoostingClassifier` **fortement
régularisé** (profondeur 3, feuilles ≥ 200 exemples, contraintes de monotonie),
calibré (isotonic / sigmoid choisi au log-loss), découpage **temporel** 50/50.

> Pourquoi cette régularisation ? Les lignes d'un même point sont très
> corrélées : des arbres profonds « reconnaissent » le point et apprennent son
> historique par cœur (AUC 0,99 en entraînement, 0,77 en test). Les variables
> non stationnaires (nombre total d'avis) ont été retirées pour la même raison.

`scripts/evaluate_fraicheur.py` (hold-out = 2e moitié de la période) :

| Score | ROC-AUC | PR-AUC | Brier | Rappel (≥ dégradé) |
|---|---|---|---|---|
| Modèle | **0,826** | 0,723 | 0,150 | 0,557 |
| Règles seules | 0,740 | 0,563 | 0,198 | 0,397 |
| **Final = max(modèle, règles)** | 0,804 | 0,667 | 0,166 | **0,629** |

Taux réellement observé par niveau prédit (modèle) : bon 12 % · à surveiller
35 % · dégradé 60 % · **critique 86 %**.

## 6. Contrats (à ne pas casser)

1. **Contexte brut uniquement** : Laravel calcule les agrégats d'avis avec les
   définitions ci-dessus ; la dérivation des features vit dans `features.py`.
2. **Plancher de sécurité** : `score_final = max(score_modele, score_regles)`,
   recalculé **côté Laravel** (on ne fait pas confiance au `score_final` reçu).
3. **Parité des règles** : `SantePointService::scoreDepuisContexte` (PHP) ≡
   `fraicheur.rules.score_regles` (Python), verrouillée par la fixture
   `tests/fixtures/fraicheur_rules_cases.json` (score, niveau, facteurs,
   actions, raison) — qui fige aussi l'ordre des colonnes d'export.
4. **Paliers `SantePoint`** : ≥ 75 critique, ≥ 50 dégradé, ≥ 25 à surveiller,
   sinon bon.
5. **Jamais d'erreur 500** : service éteint, timeout, 5xx ou JSON invalide →
   règles PHP (`source = regles`), affiché comme tel dans le back-office.

Formule des règles (0–100) : note récente (0–35) + part de négatifs (0–25) +
tendance (0–15) + plaintes (0–15) + pression canicule/affluence (0–10).

## 7. Boucle de retour d'expérience

| Commande | Planification | Rôle |
|---|---|---|
| `ai:snapshot-fraicheur` | tous les jours à 14 h | fige contexte + prédiction de chaque point validé |
| `ai:label-fraicheur` | tous les jours à 14 h 30 | après 14 j : ≥ 2 avis négatifs reçus ? |
| `ai:export-fraicheur` | à la demande | snapshots labellisés → `fraicheur_real.csv` |
| `ai:retrain-fraicheur` | à la demande | export + `POST /retrain-fraicheur` (entraîne, évalue, recharge) |

## 8. Où le voir

- **Back-office › Points de fraîcheur** : section « Points à risque dans les
  14 prochains jours » (niveau, raisons, action prioritaire) et colonne
  « Santé (14 j) » du tableau.
- **Fiche d'un point (back-office)** : jauge de risque, probabilité, « Pourquoi ? »
  (3 facteurs), « Actions conseillées », source (modèle vX ou règles).

## 9. Tests

- Python : `tests/test_fraicheur_rules.py` (parité), `test_fraicheur_api.py`
  (contrat, 422, jeton), `test_fraicheur_model.py` (monotonies, plancher,
  explicabilité).
- PHP : `tests/Unit/FraicheurRulesParityTest.php`,
  `tests/Feature/SantePointTest.php` (contexte, repli, modèle simulé, boucle
  snapshot → label → export, back-office).
