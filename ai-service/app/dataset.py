"""Préparation du jeu de données (partagée par l'entraînement et l'évaluation).

Charge l'historique météo (CSV long, une ligne par jour et par ville), calcule
les jours chauds consécutifs, dérive les features via ``features.build_features``
et étiquette chaque ligne via ``labeler.label``. Aucune fuite temporelle : le
découpage train/test est chronologique et réalisé par l'appelant.
"""

from __future__ import annotations

import csv
import hashlib
from datetime import date as _date
from pathlib import Path
from typing import Any

import numpy as np

from . import labeler
from .config import ARTIFACTS_DIR, settings
from .features import FEATURE_ORDER, build_features, vector

CLASSES = ["normal", "jaune", "orange", "rouge"]


def _history_path() -> Path:
    if settings.history_csv.exists():
        return settings.history_csv

    return settings.history_csv.parent / "weather_history_sample.csv"


def _f(value: Any) -> float | None:
    if value is None or value == "":
        return None
    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def _parse_date(value: str) -> _date | None:
    try:
        return _date.fromisoformat(value[:10])
    except (ValueError, TypeError):
        return None


def load_history_rows() -> list[dict[str, Any]]:
    path = _history_path()
    rows: list[dict[str, Any]] = []

    with open(path, "r", encoding="utf-8") as fh:
        for row in csv.DictReader(fh):
            rows.append(row)

    return rows


def _jours_chauds_consecutifs(rows: list[dict[str, Any]], seuil: float) -> dict[int, int]:
    """Compteur de jours chauds consécutifs, par ligne (clé = index)."""

    par_ville: dict[str, list[tuple[int, _date, float]]] = {}

    for index, row in enumerate(rows):
        jour = _parse_date(str(row.get("date", "")))
        temp_max = _f(row.get("temp_max"))
        if jour is None or temp_max is None:
            continue
        par_ville.setdefault(str(row.get("city", "?")), []).append((index, jour, temp_max))

    compteurs: dict[int, int] = {}
    seuil_effectif = seuil

    for lignes in par_ville.values():
        lignes.sort(key=lambda item: item[1])
        serie = 0
        precedent: _date | None = None
        for index, jour, temp_max in lignes:
            continu = precedent is not None and (jour - precedent).days == 1
            serie = (serie + 1) if (temp_max >= seuil_effectif and continu) else (1 if temp_max >= seuil_effectif else 0)
            precedent = jour
            compteurs[index] = serie

    return compteurs


def build_dataset(with_noise: bool = True) -> dict[str, Any]:
    """Construit (X, y, dates) depuis l'historique météo."""

    cfg = settings.labeler
    seuil = float(cfg["seuil"])
    rows = load_history_rows()
    jours = _jours_chauds_consecutifs(rows, seuil)

    X: list[list[float]] = []
    y: list[str] = []
    dates: list[_date] = []
    villes: list[str] = []

    for index, row in enumerate(rows):
        jour = _parse_date(str(row.get("date", "")))
        temp_max = _f(row.get("temp_max"))
        if jour is None or temp_max is None:
            continue

        row_map = {
            "temp_max": temp_max,
            "temp_min": _f(row.get("temp_min")),
            "humidite": _f(row.get("humidite")),
            "ressentie": _f(row.get("ressentie")),
            "jours_chauds_consecutifs": jours.get(index, 0),
        }

        contexte = {
            "temperature": temp_max,
            "humidite": row_map["humidite"],
            "ressentie": row_map["ressentie"],
            "seuil_temperature": seuil,
            "mois": jour.month,
            "historique": {"jours_surchauffe": row_map["jours_chauds_consecutifs"]},
            "previsions": [],
        }

        X.append(vector(build_features(contexte)))
        y.append(labeler.label(row_map, cfg, with_noise=with_noise, seed=index))
        dates.append(jour)
        villes.append(str(row.get("city", "?")))

    return {
        "X": np.asarray(X, dtype=float),
        "y": np.asarray(y, dtype=object),
        "dates": np.asarray(dates, dtype=object),
        "cities": villes,
        "hash": data_hash(),
        "n_rows": len(rows),
    }


def load_corrections() -> tuple[np.ndarray, np.ndarray]:
    """Corrections gestionnaire (contexte brut + niveau final) au format CSV.

    Colonnes attendues : date, temp_max, ressentie, humidite, seuil_temperature,
    jours_surchauffe, mois, niveau (cf. ``ai:export-corrections``).
    """

    path = settings.corrections_csv

    if not path.exists():
        return np.empty((0, len(FEATURE_ORDER))), np.asarray([], dtype=object)

    cfg = settings.labeler
    X: list[list[float]] = []
    y: list[str] = []

    with open(path, "r", encoding="utf-8") as fh:
        for row in csv.DictReader(fh):
            temp_max = _f(row.get("temp_max"))
            niveau = (row.get("niveau") or "").strip().lower()
            if temp_max is None or niveau not in CLASSES:
                continue

            contexte = {
                "temperature": temp_max,
                "humidite": _f(row.get("humidite")),
                "ressentie": _f(row.get("ressentie")),
                "seuil_temperature": _f(row.get("seuil_temperature")) or float(cfg["seuil"]),
                "mois": int(_f(row.get("mois")) or 7),
                "historique": {"jours_surchauffe": int(_f(row.get("jours_surchauffe")) or 0)},
                "previsions": [],
            }

            X.append(vector(build_features(contexte)))
            y.append(niveau)

    return np.asarray(X, dtype=float), np.asarray(y, dtype=object)


def data_hash() -> str:
    path = _history_path()

    if not path.exists():
        return ""

    digest = hashlib.sha256()

    with open(path, "rb") as fh:
        for chunk in iter(lambda: fh.read(8192), b""):
            digest.update(chunk)

    return digest.hexdigest()[:16]


def artifacts_dir() -> Path:
    ARTIFACTS_DIR.mkdir(parents=True, exist_ok=True)
    return ARTIFACTS_DIR
