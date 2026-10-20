"""Chargement, inférence et explicabilité du modèle de risque de coupure.

L'artefact ``artifacts/coupures/model.joblib`` (dossier DÉDIÉ au module 2) est un
dictionnaire ``{"model24": …, "model48": …}`` de classifieurs calibrés. Toute
absence/corruption rend le modèle « indisponible » sans lever d'exception : le
service retombe alors sur les règles seules (plancher de sécurité).
"""

from __future__ import annotations

from pathlib import Path
from typing import Any

import numpy as np

from ..model_bundle import JoblibBundle
from .features import FACTEUR_LABELS, SAFE_DEFAULTS, VECTOR_ORDER, build_zone_features, format_valeur, vector


class CoupureRiskModel(JoblibBundle):
    """Enveloppe thread-safe autour des artefacts scikit-learn du module 2."""

    def __init__(self, model_path: Path, metadata_path: Path) -> None:
        super().__init__(model_path, metadata_path, "model24")

    # ------------------------------------------------------------------ #
    # Inférence
    # ------------------------------------------------------------------ #

    def _probabilite(self, cle: str, features: dict[str, float]) -> float:
        if not self.available or self._bundle is None:
            return 0.0

        model = self._bundle.get(cle)

        if model is None:
            return 0.0

        x = np.asarray(vector(features), dtype=float).reshape(1, -1)
        probas = model.predict_proba(x)[0]

        classes = [int(c) for c in getattr(model, "classes_", [0, 1])]

        if 1 in classes:
            return float(probas[classes.index(1)])

        return float(probas[-1])

    def predict_zone(self, zone: Any) -> dict[str, Any]:
        """Prédiction d'une zone (objet pydantic ``ZoneContexte``)."""

        contexte = zone.model_dump() if hasattr(zone, "model_dump") else dict(zone)
        features = build_zone_features(contexte)

        return {
            "p24": self._probabilite("model24", features),
            "p48": self._probabilite("model48", features),
            "facteurs": self.facteurs(features),
            "model_version": self.version,
        }

    def facteurs(self, features: dict[str, float], top: int = 3) -> list[str]:
        """Top facteurs LOCAUX (libellés FR) : on remplace chaque feature par sa
        médiane d'entraînement et on mesure la hausse de la probabilité à 24 h.
        """

        if not self.available:
            return []

        base = self._probabilite("model24", features)
        medians = self.medians(SAFE_DEFAULTS, VECTOR_ORDER)

        contributions: list[tuple[float, str, float]] = []

        for nom in VECTOR_ORDER:
            valeur = float(features.get(nom, SAFE_DEFAULTS[nom]))
            remplace = dict(features)
            remplace[nom] = medians[nom]
            impact = base - self._probabilite("model24", remplace)
            contributions.append((impact, nom, valeur))

        contributions.sort(key=lambda item: item[0], reverse=True)

        facteurs: list[str] = []
        for impact, nom, valeur in contributions[: max(1, top)]:
            if impact <= 0.001:
                continue
            facteurs.append(f"{FACTEUR_LABELS.get(nom, nom)} : {format_valeur(nom, valeur)}")

        return facteurs
