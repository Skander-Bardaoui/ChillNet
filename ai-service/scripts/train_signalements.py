#!/usr/bin/env python
"""Entraîne et publie le modèle de catégories du module signalements."""

from __future__ import annotations

import json
import sys
from datetime import date
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[1]
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

import joblib  # noqa: E402
from sklearn.model_selection import StratifiedKFold, cross_validate  # noqa: E402

from app.config import settings  # noqa: E402
from app.signalements.dataset import CATEGORIES, data_hash, load_examples  # noqa: E402
from app.signalements.training import create_pipeline  # noqa: E402


def _bump_version(existing: str | None) -> str:
    if not existing:
        return "1.0.0"
    try:
        major, minor, patch = (int(part) for part in existing.split("."))
    except (ValueError, TypeError):
        return "1.0.0"
    return f"{major}.{minor}.{patch + 1}"


def main() -> int:
    descriptions, labels = load_examples()
    if len(descriptions) < 20 or set(labels) != set(CATEGORIES):
        print(
            "Données insuffisantes : il faut au moins 20 descriptions et des "
            "exemples pour chacune des catégories.",
            file=sys.stderr,
        )
        return 1

    minimum_class_size = min(labels.count(category) for category in set(labels))
    folds = min(5, minimum_class_size)
    if folds < 2:
        print("Chaque catégorie doit avoir au moins deux exemples.", file=sys.stderr)
        return 1

    validation = cross_validate(
        create_pipeline(),
        descriptions,
        labels,
        cv=StratifiedKFold(n_splits=folds, shuffle=True, random_state=42),
        scoring={"accuracy": "accuracy", "macro_f1": "f1_macro"},
        return_train_score=False,
    )
    metrics = {
        "folds": folds,
        "accuracy_mean": round(float(validation["test_accuracy"].mean()), 4),
        "accuracy_std": round(float(validation["test_accuracy"].std()), 4),
        "macro_f1_mean": round(float(validation["test_macro_f1"].mean()), 4),
        "macro_f1_std": round(float(validation["test_macro_f1"].std()), 4),
    }

    model = create_pipeline()
    model.fit(descriptions, labels)

    version = "1.0.0"
    if settings.signalements_metadata_path.exists():
        try:
            with settings.signalements_metadata_path.open("r", encoding="utf-8") as stream:
                version = _bump_version(json.load(stream).get("version"))
        except (OSError, json.JSONDecodeError):
            version = "1.0.0"

    settings.signalements_model_path.parent.mkdir(parents=True, exist_ok=True)
    settings.signalements_metadata_path.parent.mkdir(parents=True, exist_ok=True)
    settings.signalements_report_path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(model, settings.signalements_model_path)

    trained_at = date.today().isoformat()
    metadata = {
        "version": version,
        "trained_at": trained_at,
        "features": ["description"],
        "classes": [str(category) for category in model.classes_],
        "n_samples": len(descriptions),
        "data_hash": data_hash(),
        "metrics": metrics,
    }
    with settings.signalements_metadata_path.open("w", encoding="utf-8") as stream:
        json.dump(metadata, stream, ensure_ascii=False, indent=2)
    with settings.signalements_report_path.open("w", encoding="utf-8") as stream:
        json.dump(metadata, stream, ensure_ascii=False, indent=2)

    print(
        f"Modèle signalements {version} entraîné sur {len(descriptions)} exemples "
        f"(accuracy CV {metrics['accuracy_mean']:.3f}, "
        f"macro F1 CV {metrics['macro_f1_mean']:.3f})."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
