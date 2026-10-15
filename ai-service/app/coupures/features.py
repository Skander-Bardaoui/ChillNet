"""Feature engineering du modèle de risque de coupure (module 2).

SOURCE DE VÉRITÉ UNIQUE : l'entraînement (`scripts/train_coupures.py`),
l'évaluation (`scripts/evaluate_coupures.py`) et l'inférence (`model.py`)
partagent ce module. Laravel n'envoie qu'un CONTEXTE BRUT ; toute la dérivation
(températures, comptages, niveaux) vit ici.
"""

from __future__ import annotations

from datetime import datetime
from typing import Any

from app.features import heat_index_c

# Ordre STRICT du vecteur : jamais le réordonner sans ré-entraîner.
VECTOR_ORDER: list[str] = [
    "coupures_7j",
    "coupures_14j",
    "part_chaleur_14j",
    "heures_depuis_derniere",
    "en_cours",
    "confirmations_total",
    "temp",
    "ressentie",
    "heat_index",
    "max_24h",
    "max_48h",
    "canicule_niveau",
    "jours_chauds_consecutifs",
    "heure",
    "jour_semaine",
    "nb_logements_zone",
    "nb_residences",
]

# Features dont l'augmentation ne doit JAMAIS faire BAISSER le risque (contrainte
# de monotonie passée au classifieur). Aligné sur le brief du module 2.
MONOTONIC_FEATURES: tuple[str, ...] = ("temp", "heat_index", "max_24h", "max_48h", "canicule_niveau")

SAFE_DEFAULTS: dict[str, float] = {
    "coupures_7j": 0,
    "coupures_14j": 0,
    "part_chaleur_14j": 0.0,
    "heures_depuis_derniere": 336,  # 14 jours (pas de coupure connue)
    "en_cours": 0,
    "confirmations_total": 0,
    "temp": 25.0,
    "ressentie": 25.0,
    "heat_index": 25.0,
    "max_24h": 25.0,
    "max_48h": 25.0,
    "canicule_niveau": 0,
    "jours_chauds_consecutifs": 0,
    "heure": 12,
    "jour_semaine": 0,
    "nb_logements_zone": 0,
    "nb_residences": 0,
}

FACTEUR_LABELS: dict[str, str] = {
    "coupures_7j": "Coupures sur 7 j",
    "coupures_14j": "Coupures sur 14 j",
    "part_chaleur_14j": "Part de coupures liées à la chaleur",
    "heures_depuis_derniere": "Heures depuis la dernière coupure",
    "en_cours": "Coupures en cours",
    "confirmations_total": "Confirmations des voisins",
    "temp": "Température",
    "ressentie": "Température ressentie",
    "heat_index": "Indice de chaleur",
    "max_24h": "Max 24 h",
    "max_48h": "Max 48 h",
    "canicule_niveau": "Niveau de canicule",
    "jours_chauds_consecutifs": "Jours chauds consécutifs",
    "heure": "Heure",
    "jour_semaine": "Jour de la semaine",
    "nb_logements_zone": "Logements dans la zone",
    "nb_residences": "Résidences dans la zone",
}

JOURS_FR = ("lundi", "mardi", "mercredi", "jeudi", "vendredi", "samedi", "dimanche")

# Seuils de canicule (0..3) dérivés de la température (mêmes bornes que le sens
# commun métier : <35 / 35–38 / 38–41 / >=41 °C).
SEUILS_CANICULE = (35.0, 38.0, 41.0)

_CAP_HEURES_DEPUIS = 336.0  # 14 jours


def _num(value: Any, default: float | None = None) -> float | None:
    if value is None or isinstance(value, bool):
        return default
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def canicule_niveau_depuis_temp(temp: float) -> int:
    if temp >= SEUILS_CANICULE[2]:
        return 3
    if temp >= SEUILS_CANICULE[1]:
        return 2
    if temp >= SEUILS_CANICULE[0]:
        return 1
    return 0


def format_valeur(feature: str, value: float) -> str:
    """Valeur d'une feature, formatée pour l'affichage humain (FR)."""

    if feature in ("part_chaleur_14j",):
        return f"{int(round(value * 100))} %"
    if feature == "jour_semaine":
        return JOURS_FR[int(round(value)) % 7]
    if feature == "heure":
        return f"{int(round(value))} h"
    if feature in ("heures_depuis_derniere",):
        return f"{int(round(value))} h"
    if feature in ("temp", "ressentie", "heat_index", "max_24h", "max_48h"):
        return f"{value:.1f} °C"
    if feature == "canicule_niveau":
        return f"{int(round(value))} / 3"

    return f"{int(round(value))}"


def build_zone_features(contexte: dict[str, Any]) -> dict[str, float]:
    """Construit le vecteur de features d'une zone depuis un contexte brut.

    Ne lève jamais : chaque valeur manquante retombe sur un défaut sûr.
    """

    def valeur(nom: str) -> float:
        brut = _num(contexte.get(nom))
        return float(brut) if brut is not None else SAFE_DEFAULTS[nom]

    temp = valeur("temperature") if _num(contexte.get("temperature")) is not None else valeur("temp")

    humidite = _num(contexte.get("humidite"))
    hi_brut = _num(contexte.get("heat_index"))
    heat_index = hi_brut if hi_brut is not None else (heat_index_c(temp, humidite) or temp)

    ressentie_brut = _num(contexte.get("ressentie"))
    ressentie = ressentie_brut if ressentie_brut is not None else heat_index

    max_24h = _num(contexte.get("max_24h"))
    max_48h = _num(contexte.get("max_48h"))
    max_24h = max_24h if max_24h is not None else temp
    max_48h = max_48h if max_48h is not None else max(max_24h, temp)

    canicule_brut = _num(contexte.get("canicule_niveau"))
    canicule = int(canicule_brut) if canicule_brut is not None else canicule_niveau_depuis_temp(temp)
    canicule = max(0, min(3, canicule))

    heures_depuis = _num(contexte.get("heures_depuis_derniere"))
    heures_depuis = (
        min(_CAP_HEURES_DEPUIS, float(heures_depuis))
        if heures_depuis is not None
        else _CAP_HEURES_DEPUIS
    )

    heure_brut = _num(contexte.get("heure"))
    heure = int(heure_brut) if heure_brut is not None and 0 <= heure_brut <= 23 else SAFE_DEFAULTS["heure"]

    jour_brut = _num(contexte.get("jour_semaine"))
    jour = int(jour_brut) % 7 if jour_brut is not None else datetime.now().weekday()

    features = {
        "coupures_7j": valeur("coupures_7j"),
        "coupures_14j": valeur("coupures_14j"),
        "part_chaleur_14j": max(0.0, min(1.0, valeur("part_chaleur_14j"))),
        "heures_depuis_derniere": heures_depuis,
        "en_cours": valeur("en_cours"),
        "confirmations_total": valeur("confirmations_total"),
        "temp": temp,
        "ressentie": ressentie,
        "heat_index": heat_index,
        "max_24h": max_24h,
        "max_48h": max_48h,
        "canicule_niveau": float(canicule),
        "jours_chauds_consecutifs": valeur("jours_chauds_consecutifs"),
        "heure": float(heure),
        "jour_semaine": float(jour),
        "nb_logements_zone": valeur("nb_logements_zone"),
        "nb_residences": valeur("nb_residences"),
    }

    return features


def vector(features: dict[str, float]) -> list[float]:
    """Ordonne un dictionnaire de features selon ``VECTOR_ORDER``."""

    return [float(features.get(nom, SAFE_DEFAULTS[nom])) for nom in VECTOR_ORDER]


def monotonic_constraints() -> list[int]:
    """Contraintes de monotonie (+1 / 0) dans l'ordre de ``VECTOR_ORDER``."""

    return [1 if nom in MONOTONIC_FEATURES else 0 for nom in VECTOR_ORDER]
