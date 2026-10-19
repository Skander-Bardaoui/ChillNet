#!/usr/bin/env python
"""Entraîne le modèle de santé des points de fraîcheur (module 3) et écrit les artefacts.

- jeu de données : ``data/fraicheur_history.csv`` (simulateur ``sim``) +
  ``data/fraicheur_real.csv`` (snapshots RÉELS labellisés, exportés par Laravel
  ``ai:export-fraicheur``) ;
- cible : ``label_14j`` (au moins 2 avis négatifs dans les 14 jours suivants) ;
- classifieur ``HistGradientBoostingClassifier`` SOUMIS à des contraintes de
  MONOTONIE (plus d'avis négatifs / de plaintes / de canicule ne peut jamais
  faire baisser le risque ; une meilleure note ne peut jamais le faire monter) ;
- calibration isotonic/sigmoid choisie par log-loss sur le jeu de validation ;
- lignes réelles sur-pondérées (poids ``REEL_WEIGHT``) ;
- artefacts : ``artifacts/fraicheur/{model.joblib,metadata.json,report.json}``.

Usage : ``python scripts/train_fraicheur.py`` (depuis ai-service/).
"""

from __future__ import annotations

import json
import sys
from datetime import date
from pathlib import Path

RACINE = Path(__file__).resolve().parents[1]
if str(RACINE) not in sys.path:
    sys.path.insert(0, str(RACINE))

import joblib  # noqa: E402
import numpy as np  # noqa: E402
import pandas as pd  # noqa: E402
from sklearn.calibration import CalibratedClassifierCV  # noqa: E402
from sklearn.ensemble import HistGradientBoostingClassifier  # noqa: E402
from sklearn.metrics import brier_score_loss, log_loss, roc_auc_score  # noqa: E402

from app.config import settings  # noqa: E402
from app.fraicheur import rules as fraicheur_rules  # noqa: E402
from app.fraicheur.features import (  # noqa: E402
    MONOTONIC_FEATURES,
    SAFE_DEFAULTS,
    VECTOR_ORDER,
    build_point_features,
    monotonic_constraints,
    vector,
)

REEL_WEIGHT = 5.0          # sur-pondération des lignes réellement observées
GRAINE = 20261019
# Découpage temporel 50/50 : la 1re année entraîne, la 2e année (postérieure,
# aucun futur dans le passé) teste. Chaque moitié couvre un été complet.
SPLIT = 0.5
# Régularisation FORTE : les lignes d'un même point sont très corrélées (mêmes
# caractéristiques jour après jour) ; des arbres profonds « reconnaissent » le
# point et apprennent son historique par cœur (AUC train 0,99 / test 0,77).
# Arbres de profondeur 3 + feuilles d'au moins 200 exemples → test ~0,80.
MAX_ITER = 150
APPRENTISSAGE = 0.05
PROFONDEUR = 3
MIN_FEUILLE = 200


def _bump_version(existing: str | None) -> str:
    if not existing:
        return "1.0.0"
    try:
        major, minor, patch = (int(p) for p in existing.split("."))
    except (ValueError, TypeError):
        return "1.0.0"
    return f"{major}.{minor}.{patch + 1}"


def charge_donnees() -> pd.DataFrame:
    chemin = settings.fraicheur_history_csv
    if not chemin.exists():
        raise SystemExit(f"Historique absent : {chemin}. Lancez scripts/simulate_fraicheur.py.")

    df = pd.read_csv(chemin)

    if "label_14j" not in df.columns:
        raise SystemExit("Colonne de label manquante : label_14j")

    if "source" not in df.columns:
        df["source"] = "sim"

    # Fusion des exemples RÉELS exportés par Laravel (snapshots labellisés) :
    # fichier distinct, jamais écrasé par le simulateur.
    reel = settings.fraicheur_real_csv
    if reel.exists():
        df_reel = pd.read_csv(reel)
        if not df_reel.empty:
            df_reel["source"] = "reel"
            df = pd.concat([df, df_reel], ignore_index=True)

    df["date"] = pd.to_datetime(df["date"], errors="coerce")
    df = df.dropna(subset=["date", "label_14j"])

    return df.sort_values(["date", "point_id"]).reset_index(drop=True)


def matrice(df: pd.DataFrame) -> np.ndarray:
    """Convertit chaque ligne en vecteur de features (ordre strict)."""

    return np.asarray(
        [vector(build_point_features(r)) for r in df.to_dict(orient="records")],
        dtype=float,
    )


def regles_scores(df: pd.DataFrame) -> np.ndarray:
    """Score des règles (0–100) de chaque ligne — comparaison et plancher."""

    return np.asarray(
        [fraicheur_rules.score_regles(r)["score"] for r in df.to_dict(orient="records")],
        dtype=float,
    )


def _medians(X: np.ndarray) -> dict[str, float]:
    medians: dict[str, float] = {}
    for i, nom in enumerate(VECTOR_ORDER):
        valeur = float(np.median(X[:, i]))
        medians[nom] = round(valeur if np.isfinite(valeur) else float(SAFE_DEFAULTS[nom]), 4)
    return medians


def _entraine(
    X_train: np.ndarray,
    y_train: np.ndarray,
    w_train: np.ndarray,
    X_val: np.ndarray,
    y_val: np.ndarray,
) -> tuple[CalibratedClassifierCV, str, dict]:
    """Entraîne + calibre ; retient la méthode au meilleur log-loss."""

    meilleur: tuple[CalibratedClassifierCV, str, dict] | None = None

    for methode in ("isotonic", "sigmoid"):
        base = HistGradientBoostingClassifier(
            max_iter=MAX_ITER,
            learning_rate=APPRENTISSAGE,
            max_depth=PROFONDEUR,
            min_samples_leaf=MIN_FEUILLE,
            l2_regularization=1.0,
            monotonic_cst=monotonic_constraints(),
            random_state=GRAINE,
        )
        modele = CalibratedClassifierCV(base, method=methode, cv=3)
        modele.fit(X_train, y_train, sample_weight=w_train)

        probas = modele.predict_proba(X_val)[:, 1]
        metriques = {
            "log_loss": round(float(log_loss(y_val, probas)), 5),
            "brier": round(float(brier_score_loss(y_val, probas)), 5),
            "roc_auc": round(float(roc_auc_score(y_val, probas)), 5),
        }

        if meilleur is None or metriques["log_loss"] < meilleur[2]["log_loss"]:
            meilleur = (modele, methode, metriques)

    assert meilleur is not None
    return meilleur


def _importances(modele, X: np.ndarray, y: np.ndarray, echantillon: int = 5000) -> list[dict]:
    """Permutation importance : perte d'AUC quand on mélange une feature."""

    rng = np.random.default_rng(GRAINE)
    if X.shape[0] > echantillon:
        idx = rng.choice(X.shape[0], size=echantillon, replace=False)
        X, y = X[idx], y[idx]

    base = roc_auc_score(y, modele.predict_proba(X)[:, 1])
    resultats: list[dict] = []

    for i, nom in enumerate(VECTOR_ORDER):
        Xp = X.copy()
        rng.shuffle(Xp[:, i])
        chute = base - roc_auc_score(y, modele.predict_proba(Xp)[:, 1])
        resultats.append({"feature": nom, "importance": round(float(chute), 5)})

    resultats.sort(key=lambda item: item["importance"], reverse=True)
    return resultats


def _partial_dependence(modele, X: np.ndarray, feature: str, grille: list[float], echantillon: int = 3000) -> list[dict]:
    """Effet MOYEN d'une feature sur p14 : on impose la valeur à un échantillon
    réel de contextes (les autres features restent observées), puis on moyenne."""

    rng = np.random.default_rng(GRAINE)
    idx = rng.choice(X.shape[0], size=min(echantillon, X.shape[0]), replace=False)
    base = X[idx].copy()
    i = VECTOR_ORDER.index(feature)

    points: list[dict] = []
    for valeur in grille:
        Xp = base.copy()
        Xp[:, i] = float(valeur)
        points.append({"feature": feature, "valeur": float(valeur), "p14": round(float(modele.predict_proba(Xp)[:, 1].mean()), 4)})

    return points


def _comparaison(p_modele: np.ndarray, score_regles: np.ndarray, y: np.ndarray) -> dict:
    """Pouvoir discriminant modèle vs règles sur le MÊME hold-out."""

    def _auc(score: np.ndarray) -> float:
        if len(np.unique(y)) < 2:
            return float("nan")
        return round(float(roc_auc_score(y, score)), 4)

    final = np.maximum(np.round(p_modele * 100), score_regles) / 100.0

    return {
        "modele": {"roc_auc": _auc(p_modele), "brier": round(float(brier_score_loss(y, np.clip(p_modele, 0, 1))), 5)},
        "regles": {"roc_auc": _auc(score_regles / 100.0), "brier": round(float(brier_score_loss(y, np.clip(score_regles / 100.0, 0, 1))), 5)},
        "final": {"roc_auc": _auc(final), "brier": round(float(brier_score_loss(y, np.clip(final, 0, 1))), 5)},
    }


def main() -> int:
    df = charge_donnees()

    n = len(df)
    if n < 200:
        print("Jeu d'entraînement trop petit.", file=sys.stderr)
        return 1

    X = matrice(df)
    y = df["label_14j"].to_numpy(dtype=int)

    source = df["source"].fillna("sim").to_numpy()
    poids = np.where(source == "reel", REEL_WEIGHT, 1.0).astype(float)

    cut = int(n * SPLIT)
    X_train, X_test = X[:cut], X[cut:]
    y_train, y_test = y[:cut], y[cut:]

    modele, methode, metriques = _entraine(X_train, y_train, poids[:cut], X_test, y_test)

    p_test = modele.predict_proba(X_test)[:, 1]
    comparaison = _comparaison(p_test, regles_scores(df.iloc[cut:]), y_test)
    importances = _importances(modele, X_test, y_test)
    dependance = _partial_dependence(modele, X_test, "part_negatifs_30j", [0.0, 0.1, 0.2, 0.3, 0.4, 0.5, 0.6, 0.8, 1.0])

    # --- Version ---
    version = "1.0.0"
    if settings.fraicheur_metadata_path.exists():
        try:
            with open(settings.fraicheur_metadata_path, "r", encoding="utf-8") as fh:
                version = _bump_version(json.load(fh).get("version"))
        except (OSError, json.JSONDecodeError):
            version = "1.0.0"

    aujourd_hui = date.today().isoformat()
    n_reel = int((source == "reel").sum())

    settings.fraicheur_model_path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump({"model14": modele}, settings.fraicheur_model_path)

    metadata = {
        "version": version,
        "date": aujourd_hui,
        "trained_at": aujourd_hui,
        "cible": "au moins 2 avis négatifs dans les 14 jours suivants",
        "features": VECTOR_ORDER,
        "monotonic_features": MONOTONIC_FEATURES,
        "medians": _medians(X_train),
        "n_samples": int(n),
        "n_train": int(cut),
        "n_test": int(n - cut),
        "n_reel": n_reel,
        "reel_weight": REEL_WEIGHT,
        "taux_positifs": round(float(y.mean()), 4),
        "classes": [0, 1],
        "calibration": methode,
        "metrics": metriques,
        "comparaison_regles": comparaison,
        "importances": importances,
        "partial_dependence": dependance,
    }

    with open(settings.fraicheur_metadata_path, "w", encoding="utf-8") as fh:
        json.dump(metadata, fh, ensure_ascii=False, indent=2)

    rapport = {
        "version": version,
        "n_train": int(cut),
        "n_test": int(n - cut),
        "n_reel": n_reel,
        "calibration": methode,
        "metrics": metriques,
        "comparaison_regles": comparaison,
        "top_importances": importances[:8],
    }

    with open(settings.fraicheur_report_path, "w", encoding="utf-8") as fh:
        json.dump(rapport, fh, ensure_ascii=False, indent=2)

    print(f"Modele fraicheur {version} entraine sur {cut} exemples ({n_reel} reels).")
    print(f"  p14 : AUC {metriques['roc_auc']} Brier {metriques['brier']} (calibration {methode})")
    print(f"  AUC regles : {comparaison['regles']['roc_auc']} ; AUC final max(modele, regles) : {comparaison['final']['roc_auc']}")
    print("  Top features : " + ", ".join(f"{i['feature']} ({i['importance']})" for i in importances[:5]))
    print(f"  Artefacts : {settings.fraicheur_model_path.parent}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
