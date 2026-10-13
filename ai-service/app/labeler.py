"""« Expert labeler » — étiquettes d'entraînement issues de règles documentées.

Aligné sur ``VigilanceAiService`` / ``config/labeler.yaml`` : la température
effective combine indice de chaleur, ressentie, jours chauds consécutifs et
nuits tropicales, puis un bruit gaussien déterministe évite une frontière de
décision trop nette. La classe ``normal`` n'existe QUE pour l'entraînement du
modèle (les règles de production ne descendent jamais sous ``jaune``).
"""

from __future__ import annotations

import math
from typing import Any

from .features import heat_index_c

ORDRE = {"normal": 0, "jaune": 1, "orange": 2, "rouge": 3}


def _uniform(seed: int) -> float:
    """Générateur congruent (identique à ``ExpertLabeler`` côté PHP)."""

    x = (seed * 1103515245 + 12345) & 0x7FFFFFFF
    return x / 0x7FFFFFFF


def noise(seed: int, sigma: float) -> float:
    """Bruit gaussien déterministe (Box-Muller sur un générateur congruent)."""

    if sigma <= 0.0:
        return 0.0

    u1 = max(1e-9, _uniform(seed))
    u2 = _uniform(seed + 1)
    z = math.sqrt(-2.0 * math.log(u1)) * math.cos(2.0 * math.pi * u2)

    return z * sigma


def effective_temperature(
    row: dict[str, Any],
    cfg: dict[str, Any],
    with_noise: bool = True,
    seed: int | None = None,
) -> float:
    """Température effective (avant seuillage), bruit optionnel inclus."""

    temp_max = float(row.get("temp_max") or 0.0)
    humidite = row.get("humidite")
    humidite = float(humidite) if humidite is not None else None

    heat_index = heat_index_c(temp_max, humidite)

    ressentie = row.get("ressentie")
    ressentie = float(ressentie) if ressentie is not None else temp_max

    effective = max(temp_max, heat_index if heat_index is not None else temp_max, ressentie)

    jours = int(row.get("jours_chauds_consecutifs") or 0)
    if jours >= int(cfg["jours_chauds_seuil"]):
        effective += float(cfg["penalite_jours_chauds"])

    if est_nuit_tropicale(row, cfg):
        effective += float(cfg["penalite_nuit_tropicale"])

    if with_noise:
        effective += noise(
            seed if seed is not None else int(cfg["seed"]),
            float(cfg["noise_sigma"]),
        )

    return effective


def est_nuit_tropicale(row: dict[str, Any], cfg: dict[str, Any]) -> bool:
    if "nuit_tropicale" in row and row["nuit_tropicale"] is not None:
        return bool(row["nuit_tropicale"])

    temp_min = row.get("temp_min")
    if temp_min is None:
        return False

    return float(temp_min) > float(cfg["nuit_tropicale_min"])


def label(
    row: dict[str, Any],
    cfg: dict[str, Any],
    with_noise: bool = True,
    seed: int | None = None,
) -> str:
    """Étiquette d'une ligne météo : normal | jaune | orange | rouge."""

    effective = effective_temperature(row, cfg, with_noise, seed)

    seuil = float(cfg["seuil"])
    marge = float(cfg["normal_marge"])

    if effective >= seuil + float(cfg["rouge_offset"]):
        return "rouge"
    if effective >= seuil + float(cfg["orange_offset"]):
        return "orange"
    if effective >= seuil - marge:
        return "jaune"

    return "normal"
