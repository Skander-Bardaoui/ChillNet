"""Chargement et normalisation des exemples labellisés de signalements."""

from __future__ import annotations

import csv
import hashlib
from pathlib import Path

from app.config import settings

CATEGORIES = ("autre", "fuite", "panne_locale", "personne_vulnerable")
ALIASES = {
    "autre": "autre",
    "fuite": "fuite",
    "panne locale": "panne_locale",
    "panne_locale": "panne_locale",
    "coupure": "panne_locale",
    "personne_vulnerable": "personne_vulnerable",
}


def _rows(path: Path, delimiter: str) -> list[dict[str, str]]:
    if not path.exists():
        return []
    with path.open("r", encoding="utf-8-sig", newline="") as stream:
        return list(csv.DictReader(stream, delimiter=delimiter))


def load_examples() -> tuple[list[str], list[str]]:
    """Retourne les descriptions et catégories normalisées."""

    descriptions: list[str] = []
    categories: list[str] = []
    sources = (
        (_rows(settings.signalements_history_csv, ";"), "categorie"),
        (_rows(settings.signalements_examples_csv, ";"), "categorie"),
    )

    for rows, label_column in sources:
        for row in rows:
            description = (row.get("description") or "").strip()
            label = (row.get(label_column) or "").strip().lower()
            categorie = ALIASES.get(label)
            if description and categorie is not None:
                descriptions.append(description)
                categories.append(categorie)

    if not descriptions:
        raise ValueError(
            "Aucun exemple de signalement labellisé n'est disponible."
        )

    return descriptions, categories


def data_hash() -> str:
    """Empreinte des sources afin de versionner les données d'entraînement."""

    digest = hashlib.sha256()
    for path in (settings.signalements_history_csv, settings.signalements_examples_csv):
        if path.exists():
            with path.open("rb") as stream:
                for chunk in iter(lambda: stream.read(8192), b""):
                    digest.update(chunk)
    return digest.hexdigest()[:16]
