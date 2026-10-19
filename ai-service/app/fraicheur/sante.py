"""Santé prédite d'un point de fraîcheur — orchestration modèle + règles.

Le score des RÈGLES (``rules.py``) est toujours calculé : il sert de plancher de
sécurité. Quand le modèle entraîné est disponible, on combine les deux :

    score_final = max(score_modele, score_regles)

Le modèle ne peut donc JAMAIS rendre un point « plus sain » que les règles.
En l'absence de modèle (artefact absent, service dégradé), on retombe proprement
sur les règles seules (``source = "regles"``).
"""

from __future__ import annotations

from typing import Any

from . import rules
from .schemas import PointContexte


def _pourcentage(valeur: float) -> str:
    return f"{int(round(valeur * 100))} %"


def _raison_modele(niveau_modele: str, niveau_regles: str, niveau_final: str, p14: float) -> str:
    if niveau_final != niveau_regles and niveau_final == niveau_modele:
        complement = "Le modèle a relevé le niveau (il ne peut jamais le réduire)."
    elif niveau_modele == niveau_regles:
        complement = "Modèle et règles sont alignés."
    else:
        complement = "Le modèle suggère un niveau moindre ; le plancher des règles est conservé."

    return (
        f"Risque d'au moins 2 avis négatifs sous {rules.HORIZON_JOURS} j : {_pourcentage(p14)}. "
        f"Règles : {niveau_regles} — Modèle : {niveau_modele}. "
        f"Niveau final : {niveau_final}. {complement}"
    )


def evaluate_point(point: PointContexte, model: Any = None) -> dict[str, Any]:
    """Résultat complet pour un point (règles seules ou modèle + règles)."""

    contexte = point.model_dump()
    regles = rules.score_regles(contexte)

    score_regles = int(regles["score"])
    niveau_regles = regles["niveau_regles"]

    resultat: dict[str, Any] = {
        "point_id": point.point_id,
        "p14": None,
        "score_modele": None,
        "score_regles": score_regles,
        "score_final": score_regles,
        "niveau_modele": None,
        "niveau_regles": niveau_regles,
        "niveau_final": niveau_regles,
        "desaccord": False,
        "facteurs": list(regles["facteurs"]),
        "actions": list(regles["actions"]),
        "raison": regles["raison"],
        "source": "regles",
        "model_version": None,
    }

    if model is None or not getattr(model, "available", False):
        return resultat

    prediction = model.predict_point(point)
    p14 = float(prediction["p14"])
    score_modele = rules.score_depuis_probabilite(p14)
    niveau_modele = rules.niveau_sante(score_modele)

    score_final = max(score_modele, score_regles)
    niveau_final = rules.niveau_sante(score_final)

    resultat.update(
        {
            "p14": round(p14, 4),
            "score_modele": score_modele,
            "score_final": score_final,
            "niveau_modele": niveau_modele,
            "niveau_final": niveau_final,
            "desaccord": niveau_modele != niveau_regles,
            "facteurs": list(prediction.get("facteurs") or regles["facteurs"]),
            "actions": rules.actions(contexte, score_final),
            "raison": _raison_modele(niveau_modele, niveau_regles, niveau_final, p14),
            "source": "modele",
            "model_version": prediction.get("model_version"),
        }
    )

    return resultat


def evaluate_points(points: list[PointContexte], model: Any = None) -> list[dict[str, Any]]:
    return [evaluate_point(point, model) for point in points]
