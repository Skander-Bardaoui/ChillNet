#!/usr/bin/env python
"""Évalue le pipeline de catégories par validation croisée stratifiée."""

from __future__ import annotations

import json
import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parents[1]
if str(BASE_DIR) not in sys.path:
    sys.path.insert(0, str(BASE_DIR))

from sklearn.model_selection import StratifiedKFold, cross_validate  # noqa: E402

from app.config import settings  # noqa: E402
from app.signalements.dataset import load_examples  # noqa: E402
from app.signalements.training import create_pipeline  # noqa: E402


def main() -> int:
    descriptions, labels = load_examples()
    minimum_class_size = min(labels.count(category) for category in set(labels))
    folds = min(5, minimum_class_size)
    if folds < 2:
        print("Chaque catégorie doit avoir au moins deux exemples.", file=sys.stderr)
        return 1

    scores = cross_validate(
        create_pipeline(),
        descriptions,
        labels,
        cv=StratifiedKFold(n_splits=folds, shuffle=True, random_state=42),
        scoring={"accuracy": "accuracy", "macro_f1": "f1_macro"},
        return_train_score=False,
    )
    report = {
        "n_samples": len(descriptions),
        "folds": folds,
        "accuracy_mean": round(float(scores["test_accuracy"].mean()), 4),
        "accuracy_std": round(float(scores["test_accuracy"].std()), 4),
        "macro_f1_mean": round(float(scores["test_macro_f1"].mean()), 4),
        "macro_f1_std": round(float(scores["test_macro_f1"].std()), 4),
    }

    settings.signalements_report_path.parent.mkdir(parents=True, exist_ok=True)
    evaluation_path = settings.signalements_report_path.parent / "evaluation.json"
    with evaluation_path.open("w", encoding="utf-8") as stream:
        json.dump(report, stream, ensure_ascii=False, indent=2)
    print(
        f"Validation croisée ({folds} plis) : accuracy {report['accuracy_mean']:.3f}, "
        f"macro F1 {report['macro_f1_mean']:.3f}."
    )
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
