#!/usr/bin/env python
"""Entraîne le classifieur de vigilance canicule et écrit les artefacts.

- jeu de données : historique météo (``data/weather_history.csv`` ou le
  ``weather_history_sample.csv`` de repli) + corrections gestionnaire ;
- étiquettes : ``app/labeler.py`` (règles documentées) ;
- découpage chronologique (aucune fuite) ;
- corrections sur-pondérées (``corrections_weight``) à l'entraînement ;
- artefacts : ``artifacts/model.joblib``, ``artifacts/metadata.json``,
  ``artifacts/report.json``.

Usage : ``python scripts/train.py`` (depuis ai-service/).
"""

from __future__ import annotations

import json
import sys
from datetime import date
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

import joblib  # noqa: E402
import numpy as np  # noqa: E402
from sklearn.ensemble import RandomForestClassifier  # noqa: E402
from sklearn.impute import SimpleImputer  # noqa: E402
from sklearn.metrics import (  # noqa: E402
    accuracy_score,
    confusion_matrix,
    f1_score,
    recall_score,
)
from sklearn.pipeline import Pipeline  # noqa: E402

from app import dataset, rules  # noqa: E402
from app.config import settings  # noqa: E402
from app.features import FEATURE_ORDER  # noqa: E402

CLASSES = dataset.CLASSES


def _bump_version(existing: str | None) -> str:
    if not existing:
        return "1.0.0"

    try:
        major, minor, patch = (int(part) for part in existing.split("."))
    except (ValueError, TypeError):
        return "1.0.0"

    return f"{major}.{minor}.{patch + 1}"


def _metriques(y_true: np.ndarray, y_pred: np.ndarray) -> dict:
    return {
        "accuracy": round(float(accuracy_score(y_true, y_pred)), 4),
        "macro_f1": round(float(f1_score(y_true, y_pred, average="macro", zero_division=0)), 4),
        "recall_par_classe": {
            classe: round(float(v), 4)
            for classe, v in zip(
                CLASSES,
                recall_score(y_true, y_pred, labels=CLASSES, average=None, zero_division=0),
            )
        },
        "confusion": confusion_matrix(y_true, y_pred, labels=CLASSES).tolist(),
        "n_test": int(len(y_true)),
    }


def _metriques_regles(X: np.ndarray, y_true: np.ndarray) -> dict:
    seuil = float(settings.labeler["seuil"])
    predictions = [
        rules.niveau_regles(X[i][0], X[i][2], int(X[i][8]), seuil)
        for i in range(len(X))
    ]

    return _metriques(y_true, np.asarray(predictions, dtype=object))


def main() -> int:
    cfg = settings.labeler
    data = dataset.build_dataset(with_noise=True)
    X, y, dates = data["X"], data["y"], data["dates"]

    if len(X) < 20:
        print("Jeu d'entraînement trop petit (échantillon manquant ?).", file=sys.stderr)
        return 1

    # Découpage chronologique : 80 % passé (entraînement), 20 % récent (test).
    ordre = np.argsort(dates)
    X, y, dates = X[ordre], y[ordre], dates[ordre]
    cut = int(len(X) * 0.8)
    X_train, y_train = X[:cut], y[:cut]
    X_test, y_test = X[cut:], y[cut:]

    poids = np.ones(len(X_train), dtype=float)

    # Corrections gestionnaire : ajoutées à l'entraînement, fortement pondérées.
    X_corr, y_corr = dataset.load_corrections()
    if len(X_corr) > 0:
        X_train = np.vstack([X_train, X_corr])
        y_train = np.concatenate([y_train, y_corr])
        poids = np.concatenate([poids, np.full(len(X_corr), float(cfg["corrections_weight"]))])

    pipeline = Pipeline(
        steps=[
            ("imputer", SimpleImputer(strategy="median")),
            (
                "clf",
                RandomForestClassifier(
                    n_estimators=300,
                    max_depth=12,
                    class_weight="balanced",
                    random_state=42,
                    n_jobs=-1,
                ),
            ),
        ]
    )

    pipeline.fit(X_train, y_train, clf__sample_weight=poids)

    y_pred = pipeline.predict(X_test)

    medians = {
        feature: round(float(np.median(X_train[:, i])), 4)
        for i, feature in enumerate(FEATURE_ORDER)
    }

    metrics = _metriques(y_test, y_pred)
    metrics_regles = _metriques_regles(X_test, y_test)

    metadata_path = settings.metadata_path
    version = "1.0.0"
    if metadata_path.exists():
        try:
            with open(metadata_path, "r", encoding="utf-8") as fh:
                version = _bump_version(json.load(fh).get("version"))
        except (OSError, json.JSONDecodeError):
            version = "1.0.0"

    aujourd_hui = date.today().isoformat()

    dataset.artifacts_dir()
    joblib.dump(pipeline, settings.model_path)

    metadata = {
        "version": version,
        "date": aujourd_hui,
        "trained_at": aujourd_hui,
        "features": FEATURE_ORDER,
        "classes": [str(c) for c in pipeline.classes_],
        "n_samples": int(len(X_train)),
        "n_corrections": int(len(X_corr)),
        "data_hash": data["hash"],
        "medians": medians,
        "metrics": metrics,
        "metrics_regles": metrics_regles,
        "seuil": float(cfg["seuil"]),
    }

    with open(metadata_path, "w", encoding="utf-8") as fh:
        json.dump(metadata, fh, ensure_ascii=False, indent=2)

    rapport = {
        "version": version,
        "n_train": int(len(X_train)),
        "n_test": int(len(X_test)),
        "n_corrections": int(len(X_corr)),
        "classes": metadata["classes"],
        "model": metrics,
        "rules": metrics_regles,
    }

    with open(settings.metadata_path.parent / "report.json", "w", encoding="utf-8") as fh:
        json.dump(rapport, fh, ensure_ascii=False, indent=2)

    print(f"Modèle {version} entraîné sur {len(X_train)} exemples "
          f"(+{len(X_corr)} corrections), testé sur {len(X_test)}.")
    print(f"  Modèle : accuracy {metrics['accuracy']:.3f} — macro F1 {metrics['macro_f1']:.3f} "
          f"— recall rouge {metrics['recall_par_classe'].get('rouge')}")
    print(f"  Règles : accuracy {metrics_regles['accuracy']:.3f} — macro F1 {metrics_regles['macro_f1']:.3f}")
    print(f"  Rappel par classe (modèle) : {metrics['recall_par_classe']}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
