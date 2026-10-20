"""Chargement du dataset, découpage et métriques — partagés par
``scripts/train_conseils.py`` et ``scripts/evaluate_conseils.py``.

Le découpage est fait PAR FOYER (``GroupShuffleSplit``) : un foyer présent dans
le test n'apparaît jamais à l'entraînement, ce qui mesure la généralisation à
de nouveaux foyers et non la mémorisation de profils connus.
"""

from __future__ import annotations

import csv
from datetime import datetime
from pathlib import Path
from typing import Any

import numpy as np
from sklearn.metrics import (
    accuracy_score,
    classification_report,
    confusion_matrix,
    f1_score,
    log_loss,
    precision_score,
    recall_score,
)
from sklearn.model_selection import GroupShuffleSplit

from .features import VECTOR_ORDER
from .rules import label_risque

CLASSES: list[str] = ["faible", "modere", "eleve", "critique"]
RANG: dict[str, int] = {c: i for i, c in enumerate(CLASSES)}
SEED = 42


def charger_dataset(path: Path) -> dict[str, np.ndarray]:
    """Lit le CSV → X (VECTOR_ORDER), y, groupes (foyer), timestamps, niveau des règles."""
    X_rows: list[list[float]] = []
    y_rows: list[str] = []
    groupes: list[int] = []
    horodatages: list[datetime] = []
    regles: list[str] = []

    with open(path, "r", encoding="utf-8") as fh:
        for row in csv.DictReader(fh):
            label = row.get("label", "").strip().lower()
            if label not in CLASSES:
                continue
            try:
                ts = datetime.fromisoformat(row["timestamp"])
                foyer = int(row["foyer_id"])
            except (KeyError, ValueError):
                continue

            ligne: list[float] = []
            for nom in VECTOR_ORDER:
                brut = row.get(nom, "")
                try:
                    ligne.append(float(brut) if brut not in ("", None) else float("nan"))
                except ValueError:
                    ligne.append(float("nan"))

            X_rows.append(ligne)
            y_rows.append(label)
            groupes.append(foyer)
            horodatages.append(ts)
            ctx = {n: (0.0 if np.isnan(v) else v) for n, v in zip(VECTOR_ORDER, ligne)}
            regles.append(label_risque(ctx))

    return {
        "X":       np.asarray(X_rows, dtype=float),
        "y":       np.asarray(y_rows, dtype=object),
        "groupes": np.asarray(groupes, dtype=int),
        "t":       np.asarray(horodatages, dtype=object),
        "regles":  np.asarray(regles, dtype=object),
    }


def decouper(groupes: np.ndarray, test_size: float = 0.2) -> tuple[np.ndarray, np.ndarray]:
    """Indices (train, test) découpés par foyer."""
    gss = GroupShuffleSplit(n_splits=1, test_size=test_size, random_state=SEED)
    idx = np.arange(len(groupes))
    train, test = next(gss.split(idx, groups=groupes))
    return train, test


def plancher(modele: np.ndarray, regles: np.ndarray) -> np.ndarray:
    """Niveau final du système en production : max(modèle, règles)."""
    return np.asarray(
        [m if RANG[m] >= RANG[r] else r for m, r in zip(modele, regles)], dtype=object
    )


def metriques(
    y_true: np.ndarray,
    y_pred: np.ndarray,
    proba: np.ndarray | None = None,
    classes_proba: list[str] | None = None,
) -> dict[str, Any]:
    """Métriques de classification ordinale (4 niveaux)."""
    rang_t = np.asarray([RANG[v] for v in y_true])
    rang_p = np.asarray([RANG[v] for v in y_pred])
    ecart = rang_p - rang_t

    sortie: dict[str, Any] = {
        "accuracy":  round(float(accuracy_score(y_true, y_pred)), 4),
        "macro_f1":  round(float(f1_score(y_true, y_pred, labels=CLASSES, average="macro", zero_division=0)), 4),
        "precision_par_classe": {
            c: round(float(v), 4)
            for c, v in zip(CLASSES, precision_score(y_true, y_pred, labels=CLASSES, average=None, zero_division=0))
        },
        "recall_par_classe": {
            c: round(float(v), 4)
            for c, v in zip(CLASSES, recall_score(y_true, y_pred, labels=CLASSES, average=None, zero_division=0))
        },
        "ecart_moyen_niveaux": round(float(np.abs(ecart).mean()), 4),
        "a_un_niveau_pres":    round(float((np.abs(ecart) <= 1).mean()), 4),
        "sous_estimation":     round(float((ecart < 0).mean()), 4),
        "sur_estimation":      round(float((ecart > 0).mean()), 4),
        "confusion": confusion_matrix(y_true, y_pred, labels=CLASSES).tolist(),
        "n_test":    int(len(y_true)),
    }

    if proba is not None and classes_proba is not None:
        sortie["log_loss"] = round(float(log_loss(y_true, proba, labels=classes_proba)), 4)
        onehot = np.asarray([[1.0 if c == v else 0.0 for c in classes_proba] for v in y_true])
        sortie["brier"] = round(float(((proba - onehot) ** 2).sum(axis=1).mean()), 4)

    return sortie


def rapport_texte(y_true: np.ndarray, y_pred: np.ndarray) -> str:
    return classification_report(y_true, y_pred, labels=CLASSES, zero_division=0)


def baseline_majoritaire(y_train: np.ndarray, y_test: np.ndarray) -> dict[str, Any]:
    """Toujours prédire la classe la plus fréquente du train."""
    valeurs, comptes = np.unique(y_train, return_counts=True)
    majoritaire = valeurs[int(np.argmax(comptes))]
    return metriques(y_test, np.asarray([majoritaire] * len(y_test), dtype=object))
