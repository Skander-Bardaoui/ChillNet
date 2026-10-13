"""Chargement, inférence et explicabilité du modèle.

L'artefact ``artifacts/model.joblib`` (pipeline scikit-learn) est chargé une
fois au démarrage ; ``reload()`` le remplace à chaud après un ré-entraînement.
Toute absence/corruption d'artefact rend le modèle « indisponible » sans lever
d'exception : le service retombe alors sur les règles seules.
"""

from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Any

import joblib
import numpy as np

from .features import FEATURE_ORDER, SAFE_DEFAULTS, vector

LABELS_FR: dict[str, str] = {
    "temp": "Température",
    "ressentie": "Température ressentie",
    "humidite": "Humidité",
    "heat_index": "Indice de chaleur",
    "temp_minus_seuil": "Écart au seuil",
    "max_24h": "Max 24 h",
    "max_48h": "Max 48 h",
    "nuit_tropicale": "Nuit tropicale",
    "jours_chauds_consecutifs": "Jours chauds consécutifs",
    "mois": "Mois",
    "heure_pic": "Heure du pic",
}

MOIS_FR = {
    1: "janvier", 2: "février", 3: "mars", 4: "avril", 5: "mai", 6: "juin",
    7: "juillet", 8: "août", 9: "septembre", 10: "octobre", 11: "novembre", 12: "décembre",
}


def _afficher(feature: str, value: float) -> str:
    if feature == "humidite":
        return f"{value:.0f} %"
    if feature == "nuit_tropicale":
        return "oui" if value >= 0.5 else "non"
    if feature == "jours_chauds_consecutifs":
        return f"{value:.0f} j"
    if feature == "mois":
        return MOIS_FR.get(int(round(value)), str(int(round(value))))
    if feature == "heure_pic":
        return f"{value:.0f} h"
    if feature == "temp_minus_seuil":
        return f"{value:+.1f} °C"
    return f"{value:.1f} °C"


class VigilanceModel:
    """Enveloppe thread-safe autour de l'artefact scikit-learn."""

    def __init__(self, model_path: Path, metadata_path: Path) -> None:
        self._lock = threading.Lock()
        self._model_path = Path(model_path)
        self._metadata_path = Path(metadata_path)
        self._model: Any = None
        self._meta: dict[str, Any] = {}
        self._classes: list[str] = []
        self.load()

    # ------------------------------------------------------------------ #
    # Chargement
    # ------------------------------------------------------------------ #

    def load(self) -> bool:
        """(Re)charge l'artefact et ses métadonnées. Renvoie la disponibilité."""

        with self._lock:
            self._model = None
            self._meta = {}
            self._classes = []

            try:
                if not self._model_path.exists():
                    return False

                self._model = joblib.load(self._model_path)

                if self._metadata_path.exists():
                    with open(self._metadata_path, "r", encoding="utf-8") as fh:
                        self._meta = json.load(fh)

                classes = getattr(self._model, "classes_", None)
                if classes is None:
                    classes = self._meta.get("classes", [])
                self._classes = [str(c) for c in classes]

                return True
            except Exception:  # noqa: BLE001 — jamais bloquant
                self._model = None
                return False

    def reload(self) -> bool:
        """Recharge à chaud (après ré-entraînement)."""

        return self.load()

    @property
    def available(self) -> bool:
        return self._model is not None

    @property
    def classes(self) -> list[str]:
        return list(self._classes)

    @property
    def version(self) -> str | None:
        return self._meta.get("version")

    def metadata(self) -> dict[str, Any]:
        return dict(self._meta)

    def _medians(self) -> dict[str, float]:
        medians = self._meta.get("medians")
        if isinstance(medians, dict) and medians:
            return {k: float(medians.get(k, SAFE_DEFAULTS[k])) for k in FEATURE_ORDER}
        return dict(SAFE_DEFAULTS)

    # ------------------------------------------------------------------ #
    # Inférence
    # ------------------------------------------------------------------ #

    def probabilities(self, features: dict[str, float]) -> dict[str, float]:
        """Probabilités par classe (dans l'ordre stable des classes)."""

        if not self.available:
            return {}

        x = np.asarray(vector(features), dtype=float).reshape(1, -1)
        probas = self._model.predict_proba(x)[0]

        classes = self._classes or [str(c) for c in self._model.classes_]

        return {str(c): float(p) for c, p in zip(classes, probas)}

    def predict(self, features: dict[str, float]) -> tuple[str, dict[str, float], float]:
        """Renvoie (classe prédite, probabilités, confiance)."""

        probas = self.probabilities(features)

        if not probas:
            return "normal", {}, 0.0

        classe = max(probas, key=probas.get)

        return classe, probas, float(probas[classe])

    def facteurs(self, features: dict[str, float], top: int = 3) -> list[dict[str, Any]]:
        """Top facteurs LOCAUX : remplace chaque feature par la médiane
        d'entraînement et mesure la chute de probabilité de la classe prédite.
        """

        if not self.available:
            return []

        probas = self.probabilities(features)
        if not probas:
            return []

        classe = max(probas, key=probas.get)
        p_classe = probas[classe]
        medians = self._medians()

        facteurs: list[dict[str, Any]] = []

        for feature in FEATURE_ORDER:
            valeur = float(features.get(feature, SAFE_DEFAULTS[feature]))
            remplace = dict(features)
            remplace[feature] = medians[feature]

            p_remplace = self.probabilities(remplace).get(classe, p_classe)
            impact = p_classe - p_remplace

            facteurs.append(
                {
                    "feature": feature,
                    "label": LABELS_FR.get(feature, feature),
                    "valeur": round(valeur, 3),
                    "valeur_affichee": _afficher(feature, valeur),
                    "impact": round(float(impact), 4),
                }
            )

        facteurs.sort(key=lambda f: f["impact"], reverse=True)

        return facteurs[:top]
