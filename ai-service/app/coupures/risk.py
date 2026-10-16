"""Score de risque de coupure par zone — orchestration modèle + règles.

Le score des RÈGLES (``rules.py``) est toujours calculé : il sert de plancher de
sécurité. Quand le modèle entraîné est disponible, on combine les deux :

    score_final = max(score_modele, score_regles)

Le modèle ne peut donc JAMAIS faire baisser le risque sous les règles.
En l'absence de modèle (Phase A, service down, artefact absent), on retombe
proprement sur les règles seules (``source = "regles"``).
"""

from __future__ import annotations

from typing import Any

from . import rules
from .schemas import ZoneContexte


def _pourcentage(valeur: float) -> str:
    return f"{int(round(valeur * 100))} %"


def _raison_modele(
    niveau_modele: str,
    niveau_regles: str,
    niveau_final: str,
    p24: float,
    p48: float,
) -> str:
    if niveau_final != niveau_regles and niveau_final == niveau_modele:
        complement = "Le modèle a relevé le niveau (il ne peut jamais le réduire)."
    elif niveau_modele == niveau_regles:
        complement = "Modèle et règles sont alignés."
    else:
        complement = "Le modèle suggère un niveau moindre ; le plancher des règles est conservé."

    return (
        f"Règles : {niveau_regles} — Modèle : {niveau_modele} "
        f"(p24 {_pourcentage(p24)}, p48 {_pourcentage(p48)}). "
        f"Niveau final : {niveau_final}. {complement}"
    )


def evaluate_zone(zone: ZoneContexte, model: Any = None) -> dict[str, Any]:
    """Résultat complet pour une zone (règles seules ou modèle + règles)."""

    regles = rules.score_regles(zone.model_dump())

    score_regles = int(regles["score"])
    niveau_regles = regles["niveau_regles"]

    resultat: dict[str, Any] = {
        "quartier_id": zone.quartier_id,
        "p24": None,
        "p48": None,
        "score_modele": None,
        "score_regles": score_regles,
        "score_final": score_regles,
        "niveau_modele": None,
        "niveau_regles": niveau_regles,
        "niveau_final": niveau_regles,
        "desaccord": False,
        "facteurs": list(regles["facteurs"]),
        "raison": regles["raison"],
        "source": "regles",
        "model_version": None,
    }

    if model is None or not getattr(model, "available", False):
        return resultat

    prediction = model.predict_zone(zone)
    p24 = float(prediction["p24"])
    p48 = float(prediction["p48"])
    score_modele = rules.score_depuis_probabilite(p24)
    niveau_modele = rules.niveau_risque(score_modele)

    score_final = max(score_modele, score_regles)
    niveau_final = rules.niveau_risque(score_final)

    resultat.update(
        {
            "p24": round(p24, 4),
            "p48": round(p48, 4),
            "score_modele": score_modele,
            "score_final": score_final,
            "niveau_modele": niveau_modele,
            "niveau_final": niveau_final,
            "desaccord": niveau_modele != niveau_regles,
            "facteurs": list(prediction.get("facteurs") or regles["facteurs"]),
            "raison": _raison_modele(niveau_modele, niveau_regles, niveau_final, p24, p48),
            "source": "modele",
            "model_version": prediction.get("model_version"),
        }
    )

    return resultat


def evaluate_zones(zones: list[ZoneContexte], model: Any = None) -> list[dict[str, Any]]:
    return [evaluate_zone(zone, model) for zone in zones]
