"""Chargement, inférence et explicabilité du modèle de risque équipements.

L'artefact ``artifacts/conseils/model.joblib`` est un pipeline scikit-learn
(imputer + classifieur). Toute absence ou corruption rend le modèle
« indisponible » sans exception : le service retombe alors sur les règles
déterministes de ``rules.py`` (plancher de sécurité).

Pattern identique à ``app/coupures/model.py``.
"""

from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Any

import joblib
import numpy as np

from .features import FACTEUR_LABELS, SAFE_DEFAULTS, VECTOR_ORDER, format_valeur, vector

# Ordre de gravité des classes (utilisé pour le plancher et l'affichage)
ORDRE: dict[str, int] = {"faible": 0, "modere": 1, "eleve": 2, "critique": 3}


class ConseilRiskModel:
    """Enveloppe thread-safe autour de l'artefact scikit-learn du module 4."""

    def __init__(self, model_path: Path, metadata_path: Path) -> None:
        self._lock          = threading.Lock()
        self._model_path    = Path(model_path)
        self._metadata_path = Path(metadata_path)
        self._pipeline: Any = None
        self._meta: dict[str, Any] = {}
        self._classes: list[str]   = []
        self.load()

    # ------------------------------------------------------------------ #
    # Chargement
    # ------------------------------------------------------------------ #

    def load(self) -> bool:
        """(Re)charge l'artefact. Renvoie la disponibilité."""
        with self._lock:
            self._pipeline = None
            self._meta     = {}
            self._classes  = []

            try:
                if not self._model_path.exists():
                    return False

                self._pipeline = joblib.load(self._model_path)

                if self._metadata_path.exists():
                    with open(self._metadata_path, "r", encoding="utf-8") as fh:
                        self._meta = json.load(fh)

                classes = getattr(self._pipeline, "classes_", None)
                if classes is None:
                    classes = self._meta.get("classes", [])
                self._classes = [str(c) for c in classes]
                return True

            except Exception:  # noqa: BLE001 — jamais bloquant
                self._pipeline = None
                return False

    def reload(self) -> bool:
        return self.load()

    @property
    def available(self) -> bool:
        return self._pipeline is not None

    @property
    def version(self) -> str | None:
        return self._meta.get("version")

    @property
    def classes(self) -> list[str]:
        return list(self._classes)

    def metadata(self) -> dict[str, Any]:
        return dict(self._meta)

    def _medians(self) -> dict[str, float]:
        medians = self._meta.get("medians")
        if isinstance(medians, dict) and medians:
            return {k: float(medians.get(k, SAFE_DEFAULTS[k])) for k in VECTOR_ORDER}
        return dict(SAFE_DEFAULTS)

    # ------------------------------------------------------------------ #
    # Inférence
    # ------------------------------------------------------------------ #

    def predict(self, features: dict[str, float]) -> tuple[str, dict[str, float], float]:
        """Renvoie (classe prédite, probabilités par classe, confiance)."""
        if not self.available:
            return "faible", {}, 0.0

        x = np.asarray(vector(features), dtype=float).reshape(1, -1)
        probas_arr = self._pipeline.predict_proba(x)[0]
        classes    = self._classes or [str(c) for c in self._pipeline.classes_]
        probas     = {str(c): float(p) for c, p in zip(classes, probas_arr)}
        classe     = max(probas, key=probas.get)
        return classe, probas, float(probas[classe])

    def facteurs(self, features: dict[str, float], top: int = 3) -> list[str]:
        """Top facteurs LOCAUX : on remplace chaque feature par sa médiane
        d'entraînement et on mesure la baisse de proba de la classe prédite.
        Retourne des libellés FR prêts à l'affichage.
        """
        if not self.available:
            return []

        classe, probas, p_classe = self.predict(features)
        if not probas:
            return []

        medians = self._medians()
        contributions: list[tuple[float, str, float]] = []

        for nom in VECTOR_ORDER:
            valeur = float(features.get(nom, SAFE_DEFAULTS[nom]))
            remplace = dict(features)
            remplace[nom] = medians[nom]

            _, p_rem, _ = self.predict(remplace)
            impact = p_classe - p_rem.get(classe, p_classe)
            contributions.append((impact, nom, valeur))

        contributions.sort(key=lambda item: item[0], reverse=True)

        facteurs: list[str] = []
        for impact, nom, valeur in contributions[:max(1, top)]:
            if impact <= 0.001:
                continue
            facteurs.append(
                f"{FACTEUR_LABELS.get(nom, nom)} : {format_valeur(nom, valeur)}"
            )
        return facteurs
