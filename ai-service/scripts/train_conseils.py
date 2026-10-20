#!/usr/bin/env python
"""Entraîne le classifieur de risque équipements (Module 4) et sauvegarde les artefacts.

- Dataset    : ``data/equipements_risk_history.csv`` (simulate_conseils.py)
- Étiquette  : gravité SIMULÉE (variables cachées + bruit), indépendante de rules.py
- Découpage  : par foyer (80/20) — le test ne contient que des foyers inconnus
- Sélection  : validation croisée groupée (GroupKFold) sur le train UNIQUEMENT ;
               le jeu de test n'est utilisé qu'une fois, pour le rapport final
- Références : classe majoritaire et règles déterministes (rules.py) comme
               classifieur — le modèle doit les battre pour se justifier
- Artefacts  : ``artifacts/conseils/model.joblib``, ``metadata.json``, ``report.json``

Usage : ``python scripts/train_conseils.py`` (depuis ai-service/).
"""

from __future__ import annotations

import json
import sys
from datetime import date
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

import joblib                                                        # noqa: E402
import numpy as np                                                   # noqa: E402
from sklearn.ensemble import HistGradientBoostingClassifier, RandomForestClassifier  # noqa: E402
from sklearn.impute import SimpleImputer                             # noqa: E402
from sklearn.inspection import permutation_importance               # noqa: E402
from sklearn.linear_model import LogisticRegression                 # noqa: E402
from sklearn.model_selection import GroupKFold                      # noqa: E402
from sklearn.pipeline import Pipeline                               # noqa: E402
from sklearn.preprocessing import StandardScaler                    # noqa: E402
from sklearn.base import clone                                      # noqa: E402
from sklearn.metrics import f1_score                                # noqa: E402

from app.config import ARTIFACTS_DIR                                 # noqa: E402
from app.conseils.features import VECTOR_ORDER                       # noqa: E402
from app.conseils.training import (                                  # noqa: E402
    CLASSES,
    SEED,
    baseline_majoritaire,
    charger_dataset,
    decouper,
    metriques,
    plancher,
    rapport_texte,
)

CONSEILS_DIR = ARTIFACTS_DIR / "conseils"
DATA_CSV     = BASE_DIR / "data" / "equipements_risk_history.csv"


def construire_pipeline(clf) -> Pipeline:
    return Pipeline(steps=[
        ("imputer", SimpleImputer(strategy="median")),
        ("scaler",  StandardScaler()),
        ("clf",     clf),
    ])


def candidats() -> dict[str, Pipeline]:
    return {
        "Régression logistique": construire_pipeline(
            LogisticRegression(max_iter=2000, random_state=SEED)
        ),
        # Régularisé (feuilles >= 20) : pas de mémorisation du train.
        "Random Forest": construire_pipeline(
            RandomForestClassifier(
                n_estimators=300, max_depth=10, min_samples_leaf=20,
                random_state=SEED, n_jobs=-1,
            )
        ),
        "Gradient Boosting": construire_pipeline(
            HistGradientBoostingClassifier(
                max_depth=5, learning_rate=0.08, max_iter=200,
                early_stopping=True, random_state=SEED,
            )
        ),
    }


def cv_groupee(pipe: Pipeline, X, y, groupes, k: int = 5) -> tuple[float, float]:
    """Macro-F1 moyen et écart-type en validation croisée groupée par foyer."""
    scores = []
    for tr, va in GroupKFold(n_splits=k).split(X, y, groupes):
        p = clone(pipe).fit(X[tr], y[tr])
        scores.append(f1_score(y[va], p.predict(X[va]), labels=CLASSES, average="macro", zero_division=0))
    return float(np.mean(scores)), float(np.std(scores))


def main() -> int:
    print("=== Entraînement Module 4 — Risque équipements ===")

    if not DATA_CSV.exists():
        print(f"ERREUR : dataset introuvable ({DATA_CSV}).\n"
              "Lancez d'abord : python scripts/simulate_conseils.py", file=sys.stderr)
        return 1

    d = charger_dataset(DATA_CSV)
    X, y, groupes, regles = d["X"], d["y"], d["groupes"], d["regles"]
    print(f"Dataset chargé : {len(X)} lignes, {X.shape[1]} features, {len(set(groupes))} foyers")

    if len(X) < 50:
        print("Jeu d'entraînement trop petit.", file=sys.stderr)
        return 1

    tr, te = decouper(groupes)
    X_train, y_train, g_train = X[tr], y[tr], groupes[tr]
    X_test,  y_test = X[te], y[te]
    print(f"Split par foyer : {len(tr)} train ({len(set(g_train))} foyers) / "
          f"{len(te)} test ({len(set(groupes[te]))} foyers)")

    # --- Sélection par validation croisée (train uniquement) ---
    print("\n--- Validation croisée groupée (5 plis, train uniquement) ---")
    pipes = candidats()
    cv: dict[str, tuple[float, float]] = {}
    for nom, pipe in pipes.items():
        cv[nom] = cv_groupee(pipe, X_train, y_train, g_train)
        print(f"  {nom:24s} macro-F1 = {cv[nom][0]:.3f} ± {cv[nom][1]:.3f}")

    meilleur_nom = max(cv, key=lambda n: cv[n][0])
    print(f"\nModèle retenu (meilleur macro-F1 en CV) : {meilleur_nom}")

    # --- Évaluation finale, UNE fois, sur les foyers inconnus ---
    resultats: dict[str, dict] = {}
    pipe_finale = None
    for nom, pipe in pipes.items():
        pipe.fit(X_train, y_train)
        proba = pipe.predict_proba(X_test)
        classes = [str(c) for c in pipe.classes_]
        pred = pipe.predict(X_test)
        m = metriques(y_test, pred, proba, classes)
        m["macro_f1_cv"] = {"moyenne": round(cv[nom][0], 4), "ecart_type": round(cv[nom][1], 4)}
        m["systeme_final"] = metriques(y_test, plancher(pred, regles[te]))
        resultats[nom] = m
        if nom == meilleur_nom:
            pipe_finale = pipe

    references = {
        "Classe majoritaire": baseline_majoritaire(y_train, y_test),
        "Règles seules (rules.py)": metriques(y_test, regles[te]),
    }

    print("\n--- Jeu de test (foyers jamais vus) ---")
    print(f"  {'':26s} accuracy  macro-F1  recall critique")
    for nom, m in {**references, **resultats}.items():
        print(f"  {nom:26s} {m['accuracy']:.3f}     {m['macro_f1']:.3f}     "
              f"{m['recall_par_classe']['critique']:.3f}")

    meilleur_m = resultats[meilleur_nom]
    pred = pipe_finale.predict(X_test)
    print(f"\n{rapport_texte(y_test, pred)}")
    sf = meilleur_m["systeme_final"]
    print(f"Système final = max(modèle, règles) : accuracy {sf['accuracy']:.3f}, "
          f"recall critique {sf['recall_par_classe']['critique']:.3f}, "
          f"sous-estimation {sf['sous_estimation']:.3f}")

    # --- Importances par permutation (sur le test) ---
    rng = np.random.default_rng(SEED)
    sous = rng.choice(len(X_test), size=min(5000, len(X_test)), replace=False)
    perm = permutation_importance(
        pipe_finale, X_test[sous], y_test[sous], n_repeats=3,
        random_state=SEED, scoring="f1_macro", n_jobs=-1,
    )
    importances = [
        {"feature": nom, "importance": round(float(imp), 5)}
        for nom, imp in sorted(zip(VECTOR_ORDER, perm.importances_mean), key=lambda x: x[1], reverse=True)
    ]

    medians = {f: round(float(np.nanmedian(X_train[:, i])), 4) for i, f in enumerate(VECTOR_ORDER)}

    # --- Version ---
    CONSEILS_DIR.mkdir(parents=True, exist_ok=True)
    meta_path = CONSEILS_DIR / "metadata.json"
    version = "2.0.0"
    if meta_path.exists():
        try:
            with open(meta_path, "r", encoding="utf-8") as fh:
                parts = [int(p) for p in json.load(fh).get("version", "1.0.0").split(".")]
            version = f"{parts[0] + 1}.0.0" if parts[0] < 2 else f"{parts[0]}.{parts[1]}.{parts[2] + 1}"
        except Exception:  # noqa: BLE001
            version = "2.0.0"

    aujourd_hui = date.today().isoformat()
    joblib.dump(pipe_finale, CONSEILS_DIR / "model.joblib")

    metadata = {
        "version":    version,
        "trained_at": aujourd_hui,
        "date":       aujourd_hui,
        "features":   VECTOR_ORDER,
        "classes":    [str(c) for c in pipe_finale.classes_],
        "n_samples":  int(len(X_train)),
        "modele":     meilleur_nom,
        "medians":    medians,
        "metrics":    meilleur_m,
        "references": references,
        "importances": importances,
        "evaluation": "foyers jamais vus (GroupShuffleSplit), sélection par CV groupée sur le train",
        "note":       "Données 100 % synthétiques ; étiquette = gravité simulée (variables cachées + bruit).",
    }
    with open(meta_path, "w", encoding="utf-8") as fh:
        json.dump(metadata, fh, ensure_ascii=False, indent=2)

    rapport = {
        "version":  version,
        "n_train":  int(len(X_train)),
        "n_test":   int(len(X_test)),
        "classes":  metadata["classes"],
        "modele_selectionne": meilleur_nom,
        "metrics":  meilleur_m,
        "references": references,
        "comparaison": resultats,
    }
    with open(CONSEILS_DIR / "report.json", "w", encoding="utf-8") as fh:
        json.dump(rapport, fh, ensure_ascii=False, indent=2)

    print(f"\nArtefacts sauvegardés dans {CONSEILS_DIR} (version {version})")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
