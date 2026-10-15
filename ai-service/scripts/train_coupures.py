#!/usr/bin/env python
"""Entraîne le modèle de risque de coupure (module 2) et écrit les artefacts.

- jeu de données : ``data/coupures_history.csv`` (simulateur ``sim`` + lignes
  réelles exportées par Laravel ``reel`` + snapshots labellisés) ;
- deux cibles : ``label_24h`` et ``label_48h`` (coupure attendue dans la fenêtre) ;
- classifieur ``HistGradientBoostingClassifier`` SOUMIS à des contraintes de
  MONOTONIE (+1) sur les features thermiques : hausser la température ne peut
  jamais faire baisser la probabilité ;
- calibration isotonic/sigmoid choisie par log-loss sur un jeu de validation ;
- lignes réelles sur-pondérées (poids ``REEL_WEIGHT``) ;
- artefacts : ``artifacts/coupures/{model.joblib,metadata.json,report.json}``.

Usage : ``python scripts/train_coupures.py`` (depuis ai-service/).
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
from sklearn.ensemble import (  # noqa: E402
    HistGradientBoostingClassifier,
    HistGradientBoostingRegressor,
)
from sklearn.metrics import brier_score_loss, log_loss, roc_auc_score  # noqa: E402

from app.config import settings  # noqa: E402
from app.coupures import rules as coupure_rules  # noqa: E402
from app.coupures.etr import ETR_FEATURES, ETR_TYPES  # noqa: E402
from app.coupures.features import (  # noqa: E402
    MONOTONIC_FEATURES,
    SAFE_DEFAULTS,
    VECTOR_ORDER,
    build_zone_features,
    monotonic_constraints,
    vector,
)

REEL_WEIGHT = 5.0          # sur-pondération des lignes réellement observées
GRAINE = 20260101
# Découpage temporel 50/50 : la 1re année entraîne, la 2e année (postérieure,
# aucun futur dans le passé) teste. Chaque moitié couvre un cycle saisonnier
# complet → le signal thermique est présent des deux côtés.
SPLIT = 0.5
MAX_ITER = 300
APPRENTISSAGE = 0.06


def _bump_version(existing: str | None) -> str:
    if not existing:
        return "1.0.0"
    try:
        major, minor, patch = (int(p) for p in existing.split("."))
    except (ValueError, TypeError):
        return "1.0.0"
    return f"{major}.{minor}.{patch + 1}"


def _charge_donnees() -> pd.DataFrame:
    chemin = settings.coupures_history_csv
    if not chemin.exists():
        raise SystemExit(
            f"Historique absent : {chemin}. Lancez scripts/simulate_grid.py."
        )

    df = pd.read_csv(chemin)
    df["timestamp"] = pd.to_datetime(df["timestamp"], errors="coerce")

    for colonne in ("label_24h", "label_48h"):
        if colonne not in df.columns:
            raise SystemExit(f"Colonne de label manquante : {colonne}")

    if "source" not in df.columns:
        df["source"] = "sim"

    # Fusion des exemples RÉELS exportés par Laravel (snapshots labellisés) :
    # fichier distinct, jamais écrasé par le simulateur.
    reel = settings.coupures_real_csv
    if reel.exists():
        df_reel = pd.read_csv(reel)
        if not df_reel.empty:
            df_reel["timestamp"] = pd.to_datetime(df_reel["timestamp"], errors="coerce")
            df_reel["source"] = df_reel.get("source", "reel").fillna("reel")
            df = pd.concat([df, df_reel], ignore_index=True)

    return df


def _matrice(df: pd.DataFrame) -> np.ndarray:
    """Convertit chaque ligne en vecteur de features (ordre strict)."""

    lignes = []
    for enregistrement in df.to_dict(orient="records"):
        features = build_zone_features(enregistrement)
        lignes.append(vector(features))
    return np.asarray(lignes, dtype=float)


def _medians(df: pd.DataFrame) -> dict[str, float]:
    X = _matrice(df)
    medians: dict[str, float] = {}
    for i, nom in enumerate(VECTOR_ORDER):
        valeur = float(np.median(X[:, i]))
        medians[nom] = round(valeur if np.isfinite(valeur) else SAFE_DEFAULTS[nom], 4)
    return medians


def _entraine_cible(
    X_train: np.ndarray,
    y_train: np.ndarray,
    w_train: np.ndarray,
    X_val: np.ndarray,
    y_val: np.ndarray,
    contraintes: list[int],
) -> tuple[CalibratedClassifierCV, str, dict]:
    """Entraîne + calibre une cible ; retient la méthode au meilleur log-loss."""

    meilleur: tuple[CalibratedClassifierCV, str, dict] | None = None

    for methode in ("isotonic", "sigmoid"):
        base = HistGradientBoostingClassifier(
            max_iter=MAX_ITER,
            learning_rate=APPRENTISSAGE,
            max_depth=None,
            monotonic_cst=contraintes,
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


def _partial_dependence_temp(
    modele24, modele48, X: np.ndarray, echantillon: int = 3000
) -> list[dict]:
    """Effet de la température sur p24/p48 (partial dependence MOYENNÉ sur la
    population) : on impute une température cible à un échantillon réel de
    contextes (les autres features restent celles observées), puis on moyenne.
    """

    from app.coupures.features import canicule_niveau_depuis_temp

    rng = np.random.default_rng(GRAINE)
    n = X.shape[0]
    idx = rng.choice(n, size=min(echantillon, n), replace=False)
    base = X[idx].copy()

    i_temp = VECTOR_ORDER.index("temp")
    i_hi = VECTOR_ORDER.index("heat_index")
    i_res = VECTOR_ORDER.index("ressentie")
    i_m24 = VECTOR_ORDER.index("max_24h")
    i_m48 = VECTOR_ORDER.index("max_48h")
    i_can = VECTOR_ORDER.index("canicule_niveau")

    points: list[dict] = []
    grille = [20, 24, 28, 30, 32, 34, 35, 36, 37, 38, 39, 40, 42]

    for temp in grille:
        Xp = base.copy()
        Xp[:, i_temp] = float(temp)
        Xp[:, i_hi] = np.maximum(Xp[:, i_hi], float(temp))
        Xp[:, i_res] = np.maximum(Xp[:, i_res], float(temp))
        Xp[:, i_m24] = np.maximum(Xp[:, i_m24], float(temp))
        Xp[:, i_m48] = np.maximum(Xp[:, i_m48], float(temp))
        Xp[:, i_can] = np.maximum(Xp[:, i_can], float(canicule_niveau_depuis_temp(float(temp))))

        points.append(
            {
                "temp": float(temp),
                "p24": round(float(modele24.predict_proba(Xp)[:, 1].mean()), 4),
                "p48": round(float(modele48.predict_proba(Xp)[:, 1].mean()), 4),
            }
        )

    return points


def _importances(modele, X: np.ndarray, echantillon: int = 4000) -> list[dict]:
    """Permutation importance (baisse moyenne de p24 quand on permute une feature)."""

    rng = np.random.default_rng(GRAINE)
    n = X.shape[0]
    if n > echantillon:
        idx = rng.choice(n, size=echantillon, replace=False)
        Xe = X[idx]
    else:
        Xe = X

    base = modele.predict_proba(Xe)[:, 1].mean()
    resultats: list[dict] = []

    for i, nom in enumerate(VECTOR_ORDER):
        Xp = Xe.copy()
        rng.shuffle(Xp[:, i])
        chute = base - modele.predict_proba(Xp)[:, 1].mean()
        resultats.append({"feature": nom, "importance": round(float(chute), 5)})

    resultats.sort(key=lambda item: item["importance"], reverse=True)
    return resultats


def _rapport_moyenne(score_modele: np.ndarray, score_regles: np.ndarray, y: np.ndarray) -> dict:
    """Compare le pouvoir discriminant modèle vs règles sur le hold-out."""

    def _auc(score: np.ndarray) -> float:
        if len(np.unique(y)) < 2:
            return float("nan")
        return round(float(roc_auc_score(y, score)), 4)

    return {
        "modele": {
            "roc_auc": _auc(score_modele),
            "brier": round(float(brier_score_loss(y, np.clip(score_modele, 0, 1))), 5),
        },
        "regles": {
            "roc_auc": _auc(score_regles / 100.0),
            "brier": round(float(brier_score_loss(y, np.clip(score_regles / 100.0, 0, 1))), 5),
        },
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


def _entraine_etr(version: str) -> dict:
    """Entraîne les 3 régresseurs quantiles (ETR) sur les coupures résolues."""

    chemin = settings.coupures_etr_history_csv
    if not chemin.exists():
        print("ETR : historique absent, estimateur non entraîne.", file=sys.stderr)
        return {"available": False, "reason": "etr history absent"}

    df = pd.read_csv(chemin)
    if df.empty or "duration_min" not in df.columns:
        return {"available": False, "reason": "etr vide"}

    # Fusion des coupures résolues RÉELLES (export Laravel), distinctes de la sim.
    reel = settings.coupures_etr_real_csv
    if reel.exists():
        df_reel = pd.read_csv(reel)
        if not df_reel.empty and "duration_min" in df_reel.columns:
            df = pd.concat([df, df_reel], ignore_index=True)

    lignes = []
    for r in df.to_dict(orient="records"):
        type_code = ETR_TYPES.get(str(r.get("type", "")).lower(), 0)
        lignes.append(
            [
                float(type_code),
                float(r.get("heure", 12)),
                float(r.get("jour_semaine", 0)),
                float(r.get("temp", 25.0)),
                float(r.get("heat_index", r.get("temp", 25.0))),
                float(r.get("charge_zone", 0.0)),
                float(r.get("confirmations", 0)),
            ]
        )

    X = np.asarray(lignes, dtype=float)
    y = df["duration_min"].to_numpy(dtype=float)

    poids = np.ones(len(X), dtype=float)
    if "source" in df.columns:
        poids = np.where(df["source"].fillna("sim").to_numpy() == "reel", REEL_WEIGHT, 1.0)

    modeles: dict[str, object] = {}
    metriques: dict[str, dict] = {}

    for q in (0.1, 0.5, 0.9):
        regresseur = HistGradientBoostingRegressor(
            loss="quantile",
            quantile=q,
            max_iter=400,
            learning_rate=0.06,
            random_state=GRAINE,
        )
        regresseur.fit(X, y, sample_weight=poids)
        modeles[f"q{int(q * 100)}"] = regresseur

        pred = regresseur.predict(X)
        ecart = y - pred
        pinball = np.mean(np.where(ecart >= 0, q * ecart, (1 - q) * (-ecart)))
        metriques[f"q{int(q * 100)}"] = {
            "pinball": round(float(pinball), 3),
            "couverture": round(float(np.mean(y <= pred)), 4),
        }

    settings.coupures_etr_model_path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump(
        {"models": modeles, "features": ETR_FEATURES, "version": version},
        settings.coupures_etr_model_path,
    )

    return {"available": True, "n": int(len(X)), "metrics": metriques}


def main() -> int:
    df = _charge_donnees()
    df = df.sort_values("timestamp").reset_index(drop=True)

    n = len(df)
    if n < 200:
        print("Jeu d'entraînement trop petit.", file=sys.stderr)
        return 1

    X = _matrice(df)
    y24 = df["label_24h"].to_numpy(dtype=int)
    y48 = df["label_48h"].to_numpy(dtype=int)

    source = df.get("source", pd.Series(["sim"] * n)).fillna("sim").to_numpy()
    poids = np.where(source == "reel", REEL_WEIGHT, 1.0).astype(float)

    cut = int(n * SPLIT)
    X_train, X_test = X[:cut], X[cut:]
    y24_train, y24_test = y24[:cut], y24[cut:]
    y48_train, y48_test = y48[:cut], y48[cut:]
    w_train = poids[:cut]

    contraintes = monotonic_constraints()
    medians = _medians(df.iloc[:cut])

    model24, meth24, met24 = _entraine_cible(X_train, y24_train, w_train, X_test, y24_test, contraintes)
    model48, meth48, met48 = _entraine_cible(X_train, y48_train, w_train, X_test, y48_test, contraintes)

    # Score des règles sur le hold-out (comparaison d'explicabilité/sécurité).
    regles_ctx = np.asarray(
        [coupure_rules.score_regles(_regles_contexte(r))["score"] for r in df.iloc[cut:].to_dict("records")],
        dtype=float,
    )
    comparaison = _rapport_moyenne(model24.predict_proba(X_test)[:, 1], regles_ctx, y24_test)

    pd_temp = _partial_dependence_temp(model24, model48, X_test)
    importances = _importances(model24, X_test)

    # --- Version ---
    version = "1.0.0"
    if settings.coupures_metadata_path.exists():
        try:
            with open(settings.coupures_metadata_path, "r", encoding="utf-8") as fh:
                version = _bump_version(json.load(fh).get("version"))
        except (OSError, json.JSONDecodeError):
            version = "1.0.0"

    aujourd_hui = date.today().isoformat()

    settings.coupures_model_path.parent.mkdir(parents=True, exist_ok=True)
    joblib.dump({"model24": model24, "model48": model48}, settings.coupures_model_path)

    etr_info = _entraine_etr(version)

    n_reel = int((source == "reel").sum())

    metadata = {
        "version": version,
        "date": aujourd_hui,
        "trained_at": aujourd_hui,
        "features": VECTOR_ORDER,
        "monotonic_features": list(MONOTONIC_FEATURES),
        "medians": medians,
        "n_samples": int(n),
        "n_train": int(cut),
        "n_test": int(n - cut),
        "n_reel": n_reel,
        "reel_weight": REEL_WEIGHT,
        "classes": [0, 1],
        "calibration": {"model24": meth24, "model48": meth48},
        "metrics": {"model24": met24, "model48": met48},
        "partial_dependence": pd_temp,
        "importances": importances,
        "comparaison_regles": comparaison,
        "etr": etr_info,
    }

    with open(settings.coupures_metadata_path, "w", encoding="utf-8") as fh:
        json.dump(metadata, fh, ensure_ascii=False, indent=2)

    rapport = {
        "version": version,
        "n_train": int(cut),
        "n_test": int(n - cut),
        "n_reel": n_reel,
        "calibration": metadata["calibration"],
        "metrics": metadata["metrics"],
        "comparaison_regles": comparaison,
    }

    with open(settings.coupures_report_path, "w", encoding="utf-8") as fh:
        json.dump(rapport, fh, ensure_ascii=False, indent=2)

    print(f"Modele coupures {version} entraine sur {cut} exemples ({n_reel} reels).")
    print(f"  p24 : AUC {met24['roc_auc']} Brier {met24['brier']} (calibration {meth24})")
    print(f"  p48 : AUC {met48['roc_auc']} Brier {met48['brier']} (calibration {meth48})")
    print(f"  AUC regles (p24) : {comparaison['regles']['roc_auc']}")
    print(f"  Artefacts : {settings.coupures_model_path.parent}")

    return 0


if __name__ == "__main__":
    raise SystemExit(main())
