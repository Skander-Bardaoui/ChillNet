"""Chargement, inférence et explicabilité du modèle de santé des points (module 3).

L'artefact ``artifacts/fraicheur/model.joblib`` (dossier DÉDIÉ au module 3) est
un dictionnaire ``{"model14": …}`` contenant un classifieur calibré. Toute
absence/corruption rend le modèle « indisponible » sans lever d'exception : le
service retombe alors sur les règles seules (plancher de sécurité).
"""

from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Any

import joblib
import numpy as np

from .features import FACTEUR_LABELS, SAFE_DEFAULTS, VECTOR_ORDER, build_point_features, format_valeur, vector


class FraicheurSanteModel:
    """Enveloppe thread-safe autour de l'artefact scikit-learn du module 3."""

    def __init__(self, model_path: Path, metadata_path: Path) -> None:
        self._lock = threading.Lock()
        self._model_path = Path(model_path)
        self._metadata_path = Path(metadata_path)
        self._bundle: dict[str, Any] | None = None
        self._meta: dict[str, Any] = {}
        self.load()

    # ------------------------------------------------------------------ #
    # Chargement
    # ------------------------------------------------------------------ #

    def load(self) -> bool:
        with self._lock:
            self._bundle = None
            self._meta = {}

            try:
                if not self._model_path.exists():
                    return False

                bundle = joblib.load(self._model_path)

                if not isinstance(bundle, dict) or "model14" not in bundle:
                    return False

                self._bundle = bundle

                if self._metadata_path.exists():
                    with open(self._metadata_path, "r", encoding="utf-8") as fh:
                        self._meta = json.load(fh)

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
        return self._meta.get("version")

    def metadata(self) -> dict[str, Any]:
        return dict(self._meta)

    def _medians(self) -> dict[str, float]:
        medians = self._meta.get("medians")

        if isinstance(medians, dict) and medians:
            return {k: float(medians.get(k, SAFE_DEFAULTS[k])) for k in VECTOR_ORDER}

        return {k: float(v) for k, v in SAFE_DEFAULTS.items()}

    # ------------------------------------------------------------------ #
    # Inférence
    # ------------------------------------------------------------------ #

    def _probabilites(self, lignes: list[dict[str, float]]) -> np.ndarray:
        if not self.available or self._bundle is None or not lignes:
            return np.zeros(len(lignes))

        model = self._bundle["model14"]
        x = np.asarray([vector(f) for f in lignes], dtype=float)
        probas = model.predict_proba(x)

        classes = [int(c) for c in getattr(model, "classes_", [0, 1])]
        colonne = classes.index(1) if 1 in classes else probas.shape[1] - 1

        return probas[:, colonne].astype(float)

    def probabilite(self, features: dict[str, float]) -> float:
        return float(self._probabilites([features])[0])

    def predict_point(self, point: Any) -> dict[str, Any]:
        """Prédiction d'un point (objet pydantic ``PointContexte`` ou dict)."""

        contexte = point.model_dump() if hasattr(point, "model_dump") else dict(point)
        features = build_point_features(contexte)

        return {
            "p14": self.probabilite(features),
            "facteurs": self.facteurs(features),
            "model_version": self.version,
        }

    def facteurs(self, features: dict[str, float], top: int = 3) -> list[str]:
        """Top facteurs LOCAUX (libellés FR) : on remplace chaque feature par sa
        médiane d'entraînement et on mesure la BAISSE de la probabilité qui en
        résulte. Les features qui poussent le plus le risque vers le haut sont
        retenues. Un seul appel ``predict_proba`` pour toutes les variantes.
        """

        if not self.available:
            return []

        medians = self._medians()
        variantes = [features]
        for nom in VECTOR_ORDER:
            remplace = dict(features)
            remplace[nom] = medians[nom]
            variantes.append(remplace)

        probas = self._probabilites(variantes)
        base = float(probas[0])

        contributions: list[tuple[float, str, float]] = []
        for i, nom in enumerate(VECTOR_ORDER, start=1):
            valeur = float(features.get(nom, SAFE_DEFAULTS[nom]))
            contributions.append((base - float(probas[i]), nom, valeur))

        contributions.sort(key=lambda item: item[0], reverse=True)

        facteurs: list[str] = []
        for impact, nom, valeur in contributions[: max(1, top)]:
            if impact <= 0.005:
                continue
            facteurs.append(f"{FACTEUR_LABELS.get(nom, nom)} : {format_valeur(nom, valeur)}")

        return facteurs
