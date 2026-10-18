"""Chargement thread-safe et inférence du modèle de catégories."""

from __future__ import annotations

import json
import logging
import threading
from pathlib import Path
from typing import Any

import joblib

logger = logging.getLogger(__name__)


class SignalementCategoryModel:
    """Enveloppe autour du pipeline scikit-learn du module signalements."""

    def __init__(self, model_path: Path, metadata_path: Path) -> None:
        self._lock = threading.Lock()
        self._model_path = Path(model_path)
        self._metadata_path = Path(metadata_path)
        self._model: Any = None
        self._meta: dict[str, Any] = {}
        self.load()

    def load(self) -> bool:
        with self._lock:
            self._model = None
            self._meta = {}
            try:
                if not self._model_path.exists():
                    return False

                model = joblib.load(self._model_path)
                if not hasattr(model, "predict_proba") or not hasattr(model, "classes_"):
                    logger.warning("Artefact de classification signalements invalide : %s", self._model_path)
                    return False

                metadata: dict[str, Any] = {}
                if self._metadata_path.exists():
                    with self._metadata_path.open("r", encoding="utf-8") as stream:
                        loaded_metadata = json.load(stream)
                    if not isinstance(loaded_metadata, dict):
                        raise ValueError("Les métadonnées du modèle doivent être un objet JSON.")
                    metadata = loaded_metadata

                self._model = model
                self._meta = metadata
                return True
            except Exception:
                self._model = None
                self._meta = {}
                logger.exception("Impossible de charger le modèle signalements depuis %s", self._model_path)
                return False

    def reload(self) -> bool:
        return self.load()

    @property
    def available(self) -> bool:
        with self._lock:
            return self._model is not None

    @property
    def version(self) -> str | None:
        with self._lock:
            return self._meta.get("version")

    def metadata(self) -> dict[str, Any]:
        with self._lock:
            return dict(self._meta)

    def predict(self, description: str) -> dict[str, Any]:
        with self._lock:
            model = self._model
            version = self._meta.get("version")
        if model is None:
            raise RuntimeError("Le modèle de catégories de signalement est indisponible.")

        probabilities = model.predict_proba([description])[0]
        classes = [str(value) for value in model.classes_]
        distribution = {
            category: float(probability)
            for category, probability in zip(classes, probabilities)
        }
        category = max(distribution, key=distribution.get)

        return {
            "categorie": category,
            "confiance": int(round(distribution[category] * 100)),
            "probabilites": distribution,
            "model_version": version,
        }
