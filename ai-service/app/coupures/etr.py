"""Estimateur de temps de rétablissement (ETR) des coupures en cours.

Régression quantile ``HistGradientBoostingRegressor(loss="quantile")`` à trois
quantiles (0.1 / 0.5 / 0.9) sur la DURÉE des coupures résolues. À l'inférence on
GARANTIT ``p10 ≤ p50 ≤ p90`` (tri), et on fournit un libellé humain FR.

Le modèle est affiché uniquement pour les coupures ``en_cours`` (``available``).
Artefact dédié : ``artifacts/coupures/etr.joblib``.
"""

from __future__ import annotations

import threading
from pathlib import Path
from typing import Any

import joblib
import numpy as np

from .schemas import CoupureEnCours, EtrRequest

# Encodage ORDINAL du type de coupure (HGB gère les features numériques).
ETR_TYPES: dict[str, int] = {"panne": 0, "maintenance": 1, "surcharge": 2, "delestage": 3}

ETR_FEATURES: list[str] = [
    "type_code",
    "heure",
    "jour_semaine",
    "temp",
    "heat_index",
    "charge_zone",
    "confirmations",
]

ETR_DEFAUTS: dict[str, float] = {
    "type_code": 0.0,
    "heure": 12.0,
    "jour_semaine": 0.0,
    "temp": 25.0,
    "heat_index": 25.0,
    "charge_zone": 0.0,
    "confirmations": 0.0,
}

QUANTILES = (0.1, 0.5, 0.9)


def _num(value: Any, default: float) -> float:
    if value is None or isinstance(value, bool):
        return default
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def vecteur(coupure: CoupureEnCours) -> list[float]:
    """Vecteur de features d'une coupure (jamais d'exception)."""

    type_code = ETR_TYPES.get((coupure.type or "").lower(), ETR_DEFAUTS["type_code"])

    features = {
        "type_code": float(type_code),
        "heure": _num(coupure.heure, ETR_DEFAUTS["heure"]),
        "jour_semaine": _num(coupure.jour_semaine, ETR_DEFAUTS["jour_semaine"]),
        "temp": _num(coupure.temperature, ETR_DEFAUTS["temp"]),
        "heat_index": _num(coupure.heat_index, ETR_DEFAUTS["temp"]),
        "charge_zone": _num(coupure.charge_zone, ETR_DEFAUTS["charge_zone"]),
        "confirmations": _num(coupure.confirmations, ETR_DEFAUTS["confirmations"]),
    }

    return [float(features[nom]) for nom in ETR_FEATURES]


def _heures(minutes: float) -> int:
    return max(1, int(round(minutes / 60.0)))


def libelle(p10: float, p90: float) -> str:
    """Libellé FR : « retour estimé entre 1 h et 3 h » (ou « autour de N h »)."""

    bas, haut = _heures(p10), _heures(p90)

    if bas == haut:
        return f"retour estimé autour de {bas} h"

    return f"retour estimé entre {bas} h et {haut} h"


class CoupureEtrModel:
    """Enveloppe thread-safe autour des trois régresseurs quantiles."""

    def __init__(self, model_path: Path) -> None:
        self._lock = threading.Lock()
        self._model_path = Path(model_path)
        self._bundle: dict[str, Any] | None = None
        self.load()

    def load(self) -> bool:
        with self._lock:
            self._bundle = None
            try:
                if not self._model_path.exists():
                    return False
                bundle = joblib.load(self._model_path)
                if not isinstance(bundle, dict) or "models" not in bundle:
                    return False
                self._bundle = bundle
                return True
            except Exception:  # noqa: BLE001 — jamais bloquant
                self._bundle = None
                return False

    def reload(self) -> bool:
        return self.load()

    @property
    def available(self) -> bool:
        return self._bundle is not None

    @property
    def version(self) -> str | None:
        if not self._bundle:
            return None
        return self._bundle.get("version")

    def predict(self, coupure: CoupureEnCours) -> dict[str, Any]:
        if not self.available or self._bundle is None:
            return {"id": coupure.id, "p10_min": None, "p50_min": None, "p90_min": None,
                    "label": None, "available": False, "model_version": None}

        x = np.asarray(vecteur(coupure), dtype=float).reshape(1, -1)
        modeles = self._bundle["models"]

        brut = [float(modeles[f"q{int(q * 100)}"].predict(x)[0]) for q in QUANTILES]
        p10, p50, p90 = sorted(max(1.0, v) for v in brut)

        return {
            "id": coupure.id,
            "p10_min": round(p10, 1),
            "p50_min": round(p50, 1),
            "p90_min": round(p90, 1),
            "label": libelle(p10, p90),
            "available": True,
            "model_version": self.version,
        }


def estimate_etr(payload: EtrRequest, model: CoupureEtrModel | None = None) -> list[dict[str, Any]]:
    """Estime l'ETR de chaque coupure en cours (jamais d'exception)."""

    if model is None or not model.available:
        return [
            {
                "id": coupure.id,
                "p10_min": None,
                "p50_min": None,
                "p90_min": None,
                "label": None,
                "available": False,
                "model_version": None,
            }
            for coupure in payload.coupures
        ]

    return [model.predict(coupure) for coupure in payload.coupures]
