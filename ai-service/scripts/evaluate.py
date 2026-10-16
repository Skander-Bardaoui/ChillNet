#!/usr/bin/env python
"""Évalue le modèle entraîné : métriques, matrice de confusion, validation
croisée et comparaison aux règles déterministes sur le même jeu de test.

Usage : ``python scripts/evaluate.py`` (depuis ai-service/).
"""

from __future__ import annotations

import sys
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

import numpy as np  # noqa: E402
from sklearn.metrics import accuracy_score, f1_score, recall_score  # noqa: E402
from sklearn.model_selection import cross_val_score  # noqa: E402

from app import dataset, rules  # noqa: E402
from app.config import settings  # noqa: E402

CLASSES = dataset.CLASSES


def _charge_modele():
    import joblib

    if not settings.model_path.exists():
        print("Aucun modèle : lancez d'abord scripts/train.py.", file=sys.stderr)
        raise SystemExit(1)

    return joblib.load(settings.model_path)


def _tableau_metriques(titre: str, y_true: np.ndarray, y_pred: np.ndarray) -> dict:
    accuracy = accuracy_score(y_true, y_pred)
    macro_f1 = f1_score(y_true, y_pred, average="macro", zero_division=0)
    rappels = dict(
        zip(CLASSES, recall_score(y_true, y_pred, labels=CLASSES, average=None, zero_division=0))
    )

    print(f"\n{titre}")
    print(f"  accuracy : {accuracy:.3f}")
    print(f"  macro F1 : {macro_f1:.3f}")
    for classe in CLASSES:
        print(f"  recall {classe:<7}: {rappels[classe]:.3f}")

    return {"accuracy": float(accuracy), "macro_f1": float(macro_f1), "recall": rappels}


def main() -> int:
    modele = _charge_modele()
    data = dataset.build_dataset(with_noise=True)
    X, y, dates = data["X"], data["y"], data["dates"]

    ordre = np.argsort(dates)
    X, y = X[ordre], y[ordre]
    cut = int(len(X) * 0.8)
    X_test, y_test = X[cut:], y[cut:]

    pred_modele = modele.predict(X_test)

    seuil = float(settings.labeler["seuil"])
    pred_regles = np.asarray(
        [rules.niveau_regles(X_test[i][0], X_test[i][2], int(X_test[i][8]), seuil)
         for i in range(len(X_test))],
        dtype=object,
    )

    _tableau_metriques("Modèle (jeu de test chronologique)", y_test, pred_modele)
    _tableau_metriques("Règles déterministes (même jeu de test)", y_test, pred_regles)

    print("\nMatrice de confusion (modèle) — lignes = réel, colonnes = prédit")
    matrice = dataset_confusion(y_test, pred_modele)
    print(f"  {'':<8}" + "".join(f"{c:>8}" for c in CLASSES))
    for i, classe in enumerate(CLASSES):
        print(f"  {classe:<8}" + "".join(f"{v:>8}" for v in matrice[i]))

    print("\nValidation croisée 5 plis (macro F1, sur tout le jeu)")
    scores = cross_val_score(
        modele,
        X,
        y,
        cv=5,
        scoring="f1_macro",
        n_jobs=-1,
    )
    print(f"  macro F1 moyen : {scores.mean():.3f} (± {scores.std():.3f})")

    return 0


def dataset_confusion(y_true: np.ndarray, y_pred: np.ndarray) -> list[list[int]]:
    from sklearn.metrics import confusion_matrix

    return confusion_matrix(y_true, y_pred, labels=CLASSES).tolist()


if __name__ == "__main__":
    raise SystemExit(main())
