"""Configuration du microservice IA canicule.

Les seuils de labellisation vivent dans ``config/labeler.yaml`` (source de
vérité partagée par l'entraînement et l'évaluation). Les chemins et la clé
d'API sont surchargeables par variables d'environnement.
"""

from __future__ import annotations

import os
from pathlib import Path
from typing import Any

import yaml

BASE_DIR = Path(__file__).resolve().parent.parent
CONFIG_DIR = BASE_DIR / "config"
DATA_DIR = BASE_DIR / "data"
ARTIFACTS_DIR = BASE_DIR / "artifacts"


def load_labeler_config() -> dict[str, Any]:
    """Charge ``config/labeler.yaml`` (règles de labellisation)."""

    with open(CONFIG_DIR / "labeler.yaml", "r", encoding="utf-8") as fh:
        return yaml.safe_load(fh)


class Settings:
    """Paramètres du service, résolus une fois au démarrage."""

    def __init__(self) -> None:
        self.token: str = os.environ.get("AI_SERVICE_TOKEN", "")

        self.model_path: Path = Path(
            os.environ.get("AI_MODEL_PATH", ARTIFACTS_DIR / "model.joblib")
        )
        self.metadata_path: Path = Path(
            os.environ.get("AI_METADATA_PATH", ARTIFACTS_DIR / "metadata.json")
        )
        self.history_csv: Path = Path(
            os.environ.get("AI_HISTORY_CSV", DATA_DIR / "weather_history.csv")
        )
        self.corrections_csv: Path = Path(
            os.environ.get("AI_CORRECTIONS_CSV", DATA_DIR / "corrections.csv")
        )

        self.labeler: dict[str, Any] = load_labeler_config()


settings = Settings()
