"""Construction des variables d'entrée (features) à partir du CONTEXTE BRUT.

SOURCE DE VÉRITÉ UNIQUE du feature engineering : l'entraînement, l'évaluation
et l'inférence partagent ces fonctions. Laravel n'envoie que des mesures brutes
(températures, humidité, prévisions, historique) ; toute la dérivation vit ici.
"""

from __future__ import annotations

from datetime import datetime
from typing import Any

FEATURE_ORDER: list[str] = [
    "temp",
    "ressentie",
    "humidite",
    "heat_index",
    "temp_minus_seuil",
    "max_24h",
    "max_48h",
    "nuit_tropicale",
    "jours_chauds_consecutifs",
    "mois",
    "heure_pic",
]

DEFAULT_TEMP = 25.0
DEFAULT_HUMIDITE = 50.0
DEFAULT_SEUIL = 35.0
DEFAULT_HEURE_PIC = 15

# Repli sûr (médianes « tempérées ») quand un contexte est totalement absent.
SAFE_DEFAULTS: dict[str, float] = {
    "temp": DEFAULT_TEMP,
    "ressentie": DEFAULT_TEMP,
    "humidite": DEFAULT_HUMIDITE,
    "heat_index": DEFAULT_TEMP,
    "temp_minus_seuil": DEFAULT_TEMP - DEFAULT_SEUIL,
    "max_24h": DEFAULT_TEMP,
    "max_48h": DEFAULT_TEMP,
    "nuit_tropicale": 0,
    "jours_chauds_consecutifs": 0,
    "mois": 7,
    "heure_pic": DEFAULT_HEURE_PIC,
}


def _num(value: Any) -> float | None:
    """Convertit une valeur en float, ou ``None`` si non numérique."""

    if value is None or isinstance(value, bool):
        return None

    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def heat_index_c(temp_c: float | None, humidity: float | None) -> float | None:
    """Indice de chaleur de Rothfusz (°C).

    Renvoie ``None`` si une entrée manque ; renvoie la température sèche quand
    l'air est trop frais (< 26,7 °C) pour que l'indice ait un sens.
    """

    if temp_c is None or humidity is None:
        return None

    if temp_c < 26.7:
        return temp_c

    t = temp_c * 9.0 / 5.0 + 32.0  # °C → °F
    r = max(0.0, min(100.0, humidity))

    hi = (
        -42.379
        + 2.04901523 * t
        + 10.14333127 * r
        - 0.22475541 * t * r
        - 0.00683783 * t * t
        - 0.05481717 * r * r
        + 0.00122874 * t * t * r
        + 0.00085282 * t * r * r
        - 0.00000199 * t * t * r * r
    )

    return (hi - 32.0) * 5.0 / 9.0


def _heure_pic(previsions: list[dict[str, Any]]) -> int:
    """Heure (0-23) de la température maximale des prévisions."""

    meilleure_temp: float | None = None
    meilleure_heure = DEFAULT_HEURE_PIC

    for prevision in previsions:
        temperature = _num(prevision.get("temperature"))
        if temperature is None:
            continue

        if meilleure_temp is None or temperature > meilleure_temp:
            meilleure_temp = temperature
            meilleure_heure = _heure_brute(prevision.get("heure"), meilleure_heure)

    return meilleure_heure


def _heure_brute(heure: Any, defaut: int) -> int:
    if isinstance(heure, str):
        fragment = heure.replace("h", ":").split(":")[0].strip()
        if fragment.isdigit():
            return int(fragment) % 24
    return defaut


def build_features(context: dict[str, Any]) -> dict[str, float]:
    """Transforme un contexte brut en vecteur de features (jamais d'exception)."""

    temp = _num(context.get("temperature"))
    temp = temp if temp is not None else DEFAULT_TEMP

    humidite = _num(context.get("humidite"))
    humidite = humidite if humidite is not None else DEFAULT_HUMIDITE

    seuil = _num(context.get("seuil_temperature"))
    seuil = seuil if seuil is not None else DEFAULT_SEUIL

    hi = heat_index_c(temp, humidite)

    ressentie = _num(context.get("ressentie"))
    if ressentie is None:
        ressentie = hi if hi is not None else temp

    previsions = context.get("previsions") or []
    if not isinstance(previsions, list):
        previsions = []

    temperatures = [
        t for t in (_num(p.get("temperature")) for p in previsions if isinstance(p, dict))
        if t is not None
    ]

    max_24h = max(temperatures[:24]) if temperatures else temp
    max_48h = max(temperatures) if temperatures else temp
    temp_min = min(temperatures) if temperatures else temp
    nuit_tropicale = 1 if temperatures and temp_min > 25.0 else 0

    historique = context.get("historique") or {}
    if not isinstance(historique, dict):
        historique = {}
    jours_chauds = int(_num(historique.get("jours_surchauffe")) or 0)

    mois = _num(context.get("mois"))
    mois = int(mois) if mois is not None and 1 <= mois <= 12 else datetime.now().month

    return {
        "temp": float(temp),
        "ressentie": float(ressentie),
        "humidite": float(humidite),
        "heat_index": float(hi if hi is not None else temp),
        "temp_minus_seuil": float(temp - seuil),
        "max_24h": float(max_24h),
        "max_48h": float(max_48h),
        "nuit_tropicale": int(nuit_tropicale),
        "jours_chauds_consecutifs": int(jours_chauds),
        "mois": int(mois),
        "heure_pic": int(_heure_pic(previsions)),
    }


def vector(features: dict[str, float]) -> list[float]:
    """Ordonne un dictionnaire de features selon ``FEATURE_ORDER``."""

    return [float(features.get(nom, SAFE_DEFAULTS[nom])) for nom in FEATURE_ORDER]
