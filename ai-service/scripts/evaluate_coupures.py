#!/usr/bin/env python
"""Évalue le modèle de risque de coupure (module 2) sur un hold-out temporel.

- découpage temporel identique à l'entraînement (1re année / 2e année) ;
- ROC-AUC, PR-AUC, Brier, courbe de calibration pour p24 et p48 ;
- recall au palier « eleve » (score ≥ 50) et matrice de confusion par niveau ;
- comparaison explicite au score des RÈGLES sur le MÊME hold-out.

Écrit ``artifacts/coupures/evaluation.json`` (fusionné dans ``metadata.json``
sous la clé ``evaluation`` pour être exposé par ``/coupures/insights``).

Usage : ``python scripts/evaluate_coupures.py`` (depuis ai-service/).
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
import pandas as pd  # noqa: E402
from sklearn.metrics import (  # noqa: E402
    average_precision_score,
    brier_score_loss,
    roc_auc_score,
)

from app.config import settings  # noqa: E402
from app.coupures import rules as coupure_rules  # noqa: E402
from app.coupures.features import VECTOR_ORDER, build_zone_features, vector  # noqa: E402

SPLIT = 0.5
SEUIL_ELEVE = 50  # palier « eleve » du NiveauRisque


def _charge_modele():
    if not settings.coupures_model_path.exists():
        print("Aucun modèle coupures : lancez scripts/train_coupures.py.", file=sys.stderr)
        raise SystemExit(1)
    return joblib.load(settings.coupures_model_path)


def _courbe_calibration(y: np.ndarray, probes: np.ndarray, n_bins: int = 10) -> list[dict]:
    bornes = np.linspace(0.0, 1.0, n_bins + 1)
    courbe: list[dict] = []

    for i in range(n_bins):
        bas, haut = bornes[i], bornes[i + 1]
        masque = (probes >= bas) & (probes < haut if i < n_bins - 1 else probes <= haut)
        if not masque.any():
            courbe.append({"bin": round((bas + haut) / 2, 3), "n": 0, "predit": None, "observe": None})
            continue
        courbe.append(
            {
                "bin": round(float((bas + haut) / 2), 3),
                "n": int(masque.sum()),
                "predit": round(float(probes[masque].mean()), 4),
                "observe": round(float(y[masque].mean()), 4),
            }
        )

    return courbe


def _confusion_par_niveau(score: np.ndarray, y: np.ndarray) -> dict:
    niveaux = [coupure_rules.niveau_risque(int(s)) for s in score]
    table: dict[str, dict] = {}

    for niveau in ("faible", "modere", "eleve", "critique"):
        masque = np.asarray([n == niveau for n in niveaux])
        if not masque.any():
            table[niveau] = {"n": 0, "positifs": 0, "taux": None}
            continue
        table[niveau] = {
            "n": int(masque.sum()),
            "positifs": int(y[masque].sum()),
            "taux": round(float(y[masque].mean()), 4),
        }

    return table


def _recall_eleve(score: np.ndarray, y: np.ndarray) -> float:
    predits = score >= SEUIL_ELEVE
    positifs = y == 1
    denom = int(positifs.sum())
    if denom == 0:
        return float("nan")
    return round(float((predits & positifs).sum() / denom), 4)


def _metriques(y: np.ndarray, probes: np.ndarray) -> dict:
    return {
        "roc_auc": round(float(roc_auc_score(y, probes)), 4),
        "pr_auc": round(float(average_precision_score(y, probes)), 4),
        "brier": round(float(brier_score_loss(y, probes)), 5),
        "recall_eleve": _recall_eleve((probes * 100).round(), y),
        "calibration": _courbe_calibration(y, probes),
    }


def _regles_contexte(enregistrement: dict) -> dict:
    frequence = int(enregistrement.get("coupures_14j", 0))
    chaudes = int(round(float(enregistrement.get("part_chaleur_14j", 0.0)) * frequence))
    return {
        "frequence": frequence,
        "en_cours": int(enregistrement.get("en_cours", 0)),
        "chaudes": chaudes,
        "confirmations": int(enregistrement.get("confirmations_total", 0)),
        "niveau_chaleur": {0: None, 1: "jaune", 2: "orange", 3: "rouge"}.get(
            int(enregistrement.get("canicule_niveau", 0))
        ),
        "temperature": float(enregistrement.get("temp", 25.0)),
    }


def _table_depuis_scores(score_final: np.ndarray, y: np.ndarray, titre: str) -> dict:
    probes = np.clip(score_final / 100.0, 0, 1)
    metriques = _metriques(y, probes)
    metriques["confusion_par_niveau"] = _confusion_par_niveau(score_final, y)

    print(f"\n{titre}")
    print(f"  ROC-AUC : {metriques['roc_auc']}")
    print(f"  PR-AUC  : {metriques['pr_auc']}")
    print(f"  Brier   : {metriques['brier']}")
    print(f"  recall (>= eleve) : {metriques['recall_eleve']}")

    return metriques


def main() -> int:
    bundle = _charge_modele()
    model24, model48 = bundle["model24"], bundle["model48"]

    df = pd.read_csv(settings.coupures_history_csv)
    df = df.sort_values("timestamp").reset_index(drop=True)

    X = np.asarray(
        [vector(build_zone_features(r)) for r in df.to_dict(orient="records")], dtype=float
    )
    y24 = df["label_24h"].to_numpy(int)
    y48 = df["label_48h"].to_numpy(int)

    cut = int(len(X) * SPLIT)
    X_test = X[cut:]
    y24_test, y48_test = y24[cut:], y48[cut:]

    p24 = model24.predict_proba(X_test)[:, 1]
    p48 = model48.predict_proba(X_test)[:, 1]
    score_modele = (p24 * 100).round().clip(0, 100)

    regles_score = np.asarray(
        [coupure_rules.score_regles(_regles_contexte(r))["score"] for r in df.iloc[cut:].to_dict("records")],
        dtype=float,
    )
    score_final = np.maximum(score_modele, regles_score)

    evaluation = {
        "n_test": int(len(X_test)),
        "modele_p24": _metriques(y24_test, p24),
        "modele_p48": _metriques(y48_test, p48),
        "regles": _table_depuis_scores(regles_score, y24_test, "Règles (hold-out, cible 24 h)"),
        "modele_niveaux": _table_depuis_scores(
            score_modele, y24_test, "Modèle (hold-out, cible 24 h)"
        ),
        "final_niveaux": _table_depuis_scores(
            score_final, y24_test, "Plancher max(modèle, règles)"
        ),
    }

    print(f"\nComparaison ROC-AUC (cible 24 h) — modèle {evaluation['modele_p24']['roc_auc']} "
          f"vs règles {evaluation['regles']['roc_auc']}")

    settings.coupures_report_path.parent.mkdir(parents=True, exist_ok=True)
    with open(settings.coupures_report_path.parent / "evaluation.json", "w", encoding="utf-8") as fh:
        json.dump(evaluation, fh, ensure_ascii=False, indent=2)

    # Fusion dans metadata.json (exposé par /coupures/insights).
    if settings.coupures_metadata_path.exists():
        with open(settings.coupures_metadata_path, "r", encoding="utf-8") as fh:
            metadata = json.load(fh)
        metadata["evaluation"] = evaluation
        with open(settings.coupures_metadata_path, "w", encoding="utf-8") as fh:
            json.dump(metadata, fh, ensure_ascii=False, indent=2)

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
