#!/usr/bin/env python
"""Évalue le modèle de santé des points de fraîcheur (module 3) sur un hold-out temporel.

- découpage temporel identique à l'entraînement (1re moitié / 2e moitié) ;
- ROC-AUC, PR-AUC, Brier, courbe de calibration de p14 ;
- recall au palier « degrade » (score >= 50) et table par niveau de santé ;
- comparaison explicite au score des RÈGLES et au score FINAL
  ``max(modèle, règles)`` sur le MÊME hold-out.

Écrit ``artifacts/fraicheur/evaluation.json`` (fusionné dans ``metadata.json``
sous la clé ``evaluation`` pour être exposé par ``/fraicheur/insights``).

Usage : ``python scripts/evaluate_fraicheur.py`` (depuis ai-service/).
"""

from __future__ import annotations

import json
import sys
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
if str(RACINE) not in sys.path:
    sys.path.insert(0, str(RACINE))

import joblib  # noqa: E402
import numpy as np  # noqa: E402
from sklearn.metrics import average_precision_score, brier_score_loss, roc_auc_score  # noqa: E402

from app.config import settings  # noqa: E402
from app.fraicheur import rules as fraicheur_rules  # noqa: E402
from scripts.train_fraicheur import SPLIT, charge_donnees, matrice, regles_scores  # noqa: E402

SEUIL_DEGRADE = 50  # palier « degrade » de SantePoint


def _charge_modele():
    if not settings.fraicheur_model_path.exists():
        print("Aucun modèle fraicheur : lancez scripts/train_fraicheur.py.", file=sys.stderr)
        raise SystemExit(1)
    return joblib.load(settings.fraicheur_model_path)["model14"]


def _courbe_calibration(y: np.ndarray, probas: np.ndarray, n_bins: int = 10) -> list[dict]:
    bornes = np.linspace(0.0, 1.0, n_bins + 1)
    courbe: list[dict] = []

    for i in range(n_bins):
        bas, haut = bornes[i], bornes[i + 1]
        masque = (probas >= bas) & (probas < haut if i < n_bins - 1 else probas <= haut)
        if not masque.any():
            courbe.append({"bin": round(float((bas + haut) / 2), 3), "n": 0, "predit": None, "observe": None})
            continue
        courbe.append(
            {
                "bin": round(float((bas + haut) / 2), 3),
                "n": int(masque.sum()),
                "predit": round(float(probas[masque].mean()), 4),
                "observe": round(float(y[masque].mean()), 4),
            }
        )

    return courbe


def _table_par_niveau(score: np.ndarray, y: np.ndarray) -> dict:
    niveaux = np.asarray([fraicheur_rules.niveau_sante(int(s)) for s in score])
    table: dict[str, dict] = {}

    for niveau in fraicheur_rules.NIVEAUX:
        masque = niveaux == niveau
        table[niveau] = {
            "n": int(masque.sum()),
            "positifs": int(y[masque].sum()) if masque.any() else 0,
            "taux": round(float(y[masque].mean()), 4) if masque.any() else None,
        }

    return table


def _recall_degrade(score: np.ndarray, y: np.ndarray) -> float | None:
    positifs = y == 1
    if not positifs.any():
        return None
    return round(float(((score >= SEUIL_DEGRADE) & positifs).sum() / positifs.sum()), 4)


def _metriques(y: np.ndarray, score: np.ndarray, titre: str) -> dict:
    """Métriques d'un score 0–100 (modèle, règles ou final)."""

    probas = np.clip(score / 100.0, 0.0, 1.0)
    metriques = {
        "roc_auc": round(float(roc_auc_score(y, probas)), 4),
        "pr_auc": round(float(average_precision_score(y, probas)), 4),
        "brier": round(float(brier_score_loss(y, probas)), 5),
        "recall_degrade": _recall_degrade(score, y),
        "par_niveau": _table_par_niveau(score, y),
    }

    print(f"\n{titre}")
    print(f"  ROC-AUC : {metriques['roc_auc']}  PR-AUC : {metriques['pr_auc']}  Brier : {metriques['brier']}")
    print(f"  recall (>= degrade) : {metriques['recall_degrade']}")
    for niveau, ligne in metriques["par_niveau"].items():
        print(f"    {niveau:13s} n={ligne['n']:6d}  taux observé={ligne['taux']}")

    return metriques


def main() -> int:
    modele = _charge_modele()

    df = charge_donnees()
    cut = int(len(df) * SPLIT)
    test = df.iloc[cut:]

    X_test = matrice(test)
    y_test = test["label_14j"].to_numpy(int)

    p14 = modele.predict_proba(X_test)[:, 1]
    score_modele = np.clip(np.round(p14 * 100), 0, 100)
    score_regles = regles_scores(test)
    score_final = np.maximum(score_modele, score_regles)

    evaluation = {
        "n_test": int(len(test)),
        "taux_positifs": round(float(y_test.mean()), 4),
        "calibration": _courbe_calibration(y_test, p14),
        "modele": _metriques(y_test, score_modele, "Modèle (hold-out, cible 14 j)"),
        "regles": _metriques(y_test, score_regles, "Règles (hold-out, cible 14 j)"),
        "final": _metriques(y_test, score_final, "Plancher max(modèle, règles)"),
    }

    print(
        f"\nComparaison ROC-AUC — modèle {evaluation['modele']['roc_auc']} "
        f"vs règles {evaluation['regles']['roc_auc']} (final {evaluation['final']['roc_auc']})"
    )

    settings.fraicheur_report_path.parent.mkdir(parents=True, exist_ok=True)
    with open(settings.fraicheur_report_path.parent / "evaluation.json", "w", encoding="utf-8") as fh:
        json.dump(evaluation, fh, ensure_ascii=False, indent=2)

    # Fusion dans metadata.json (exposé par /fraicheur/insights).
    if settings.fraicheur_metadata_path.exists():
        with open(settings.fraicheur_metadata_path, "r", encoding="utf-8") as fh:
            metadata = json.load(fh)
        metadata["evaluation"] = evaluation
        with open(settings.fraicheur_metadata_path, "w", encoding="utf-8") as fh:
            json.dump(metadata, fh, ensure_ascii=False, indent=2)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
