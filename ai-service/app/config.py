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

# Dossier d'artefacts DÉDIÉ au module coupures : chaque modèle a le sien, pour
# rester distinguable du modèle canicule (artifacts/). Voir app/coupures/.
COUPURES_ARTIFACTS_DIR = ARTIFACTS_DIR / "coupures"
SIGNALEMENTS_ARTIFACTS_DIR = ARTIFACTS_DIR / "signalements"

# Dossier d'artefacts DÉDIÉ au module 3 (points de fraîcheur + avis). Voir
# app/fraicheur/.
FRAICHEUR_ARTIFACTS_DIR = ARTIFACTS_DIR / "fraicheur"

# Dossier d'artefacts DÉDIÉ au module 4 (conseils / équipements) : chaque
# modèle a le sien, pour rester distinguable des autres modules.
CONSEILS_ARTIFACTS_DIR = ARTIFACTS_DIR / "conseils"


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

        # --- Module coupures (artefacts et données isolés) ---
        self.coupures_model_path: Path = Path(
            os.environ.get(
                "AI_COUPURES_MODEL_PATH", COUPURES_ARTIFACTS_DIR / "model.joblib"
            )
        )
        self.coupures_metadata_path: Path = Path(
            os.environ.get(
                "AI_COUPURES_METADATA_PATH", COUPURES_ARTIFACTS_DIR / "metadata.json"
            )
        )
        self.coupures_report_path: Path = Path(
            os.environ.get(
                "AI_COUPURES_REPORT_PATH", COUPURES_ARTIFACTS_DIR / "report.json"
            )
        )
        self.coupures_history_csv: Path = Path(
            os.environ.get("AI_COUPURES_HISTORY_CSV", DATA_DIR / "coupures_history.csv")
        )
        # CSV RÉELS exportés par Laravel (`ai:export-coupures`) : fichier distinct
        # de la simulation pour ne jamais l'écraser. Fusionné à l'entraînement.
        self.coupures_real_csv: Path = Path(
            os.environ.get("AI_COUPURES_REAL_CSV", DATA_DIR / "coupures_real.csv")
        )
        self.coupures_etr_history_csv: Path = Path(
            os.environ.get("AI_COUPURES_ETR_HISTORY_CSV", DATA_DIR / "coupures_etr_history.csv")
        )
        self.coupures_etr_real_csv: Path = Path(
            os.environ.get("AI_COUPURES_ETR_REAL_CSV", DATA_DIR / "coupures_etr_real.csv")
        )
        self.coupures_etr_model_path: Path = Path(
            os.environ.get(
                "AI_COUPURES_ETR_MODEL_PATH", COUPURES_ARTIFACTS_DIR / "etr.joblib"
            )
        )

        # --- Module 4 conseils / équipements (artefacts isolés) ---
        self.conseils_model_path: Path = Path(
            os.environ.get(
                "AI_CONSEILS_MODEL_PATH", CONSEILS_ARTIFACTS_DIR / "model.joblib"
            )
        )
        self.conseils_metadata_path: Path = Path(
            os.environ.get(
                "AI_CONSEILS_METADATA_PATH", CONSEILS_ARTIFACTS_DIR / "metadata.json"
            )
        )

        # --- Module signalements (modèle et données isolés) ---
        self.signalements_model_path: Path = Path(
            os.environ.get(
                "AI_SIGNALEMENTS_MODEL_PATH",
                SIGNALEMENTS_ARTIFACTS_DIR / "model.joblib",
            )
        )
        self.signalements_metadata_path: Path = Path(
            os.environ.get(
                "AI_SIGNALEMENTS_METADATA_PATH",
                SIGNALEMENTS_ARTIFACTS_DIR / "metadata.json",
            )
        )
        self.signalements_report_path: Path = Path(
            os.environ.get(
                "AI_SIGNALEMENTS_REPORT_PATH",
                SIGNALEMENTS_ARTIFACTS_DIR / "report.json",
            )
        )
        self.signalements_history_csv: Path = Path(
            os.environ.get(
                "AI_SIGNALEMENTS_HISTORY_CSV",
                DATA_DIR / "signalements_urgence.csv",
            )
        )
        self.signalements_examples_csv: Path = Path(
            os.environ.get(
                "AI_SIGNALEMENTS_EXAMPLES_CSV",
                DATA_DIR / "signalements_categories.csv",
            )
        )
        # --- Module 3 : santé des points de fraîcheur (artefacts et données isolés) ---
        self.fraicheur_model_path: Path = Path(
            os.environ.get(
                "AI_FRAICHEUR_MODEL_PATH", FRAICHEUR_ARTIFACTS_DIR / "model.joblib"
            )
        )
        self.fraicheur_metadata_path: Path = Path(
            os.environ.get(
                "AI_FRAICHEUR_METADATA_PATH", FRAICHEUR_ARTIFACTS_DIR / "metadata.json"
            )
        )
        self.fraicheur_report_path: Path = Path(
            os.environ.get(
                "AI_FRAICHEUR_REPORT_PATH", FRAICHEUR_ARTIFACTS_DIR / "report.json"
            )
        )
        self.fraicheur_history_csv: Path = Path(
            os.environ.get("AI_FRAICHEUR_HISTORY_CSV", DATA_DIR / "fraicheur_history.csv")
        )
        # CSV RÉELS exportés par Laravel (`ai:export-fraicheur`) : fichier distinct
        # de la simulation pour ne jamais l'écraser. Fusionné à l'entraînement.
        self.fraicheur_real_csv: Path = Path(
            os.environ.get("AI_FRAICHEUR_REAL_CSV", DATA_DIR / "fraicheur_real.csv")
        )

        self.labeler: dict[str, Any] = load_labeler_config()


settings = Settings()
