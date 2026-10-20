"""Chargement commun des bundles joblib des modules 2 et 3.

Chaque bundle est un dictionnaire ``{clé: modèle}`` accompagné d'un JSON de
métadonnées. Toute absence ou corruption rend le bundle « indisponible » sans
lever d'exception : les services retombent alors sur les règles seules.
"""

from __future__ import annotations

import json
import threading
from pathlib import Path
from typing import Any

import joblib


class JoblibBundle:
    """Enveloppe thread-safe d'un bundle joblib et de ses métadonnées."""

    def __init__(self, model_path: Path, metadata_path: Path, required_key: str) -> None:
        self._lock = threading.Lock()
        self._model_path = Path(model_path)
        self._metadata_path = Path(metadata_path)
        self._required_key = required_key
        self._bundle: dict[str, Any] | None = None
        self._meta: dict[str, Any] = {}
        self.load()

    def load(self) -> bool:
        with self._lock:
            self._bundle = None
            self._meta = {}

            try:
                if not self._model_path.exists():
                    return False

                bundle = joblib.load(self._model_path)

                if not isinstance(bundle, dict) or self._required_key not in bundle:
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

    def medians(self, defaults: dict[str, float], order: list[str]) -> dict[str, float]:
        medians = self._meta.get("medians")

        if isinstance(medians, dict) and medians:
            return {k: float(medians.get(k, defaults[k])) for k in order}

        return {k: float(v) for k, v in defaults.items()}
