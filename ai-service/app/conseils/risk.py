"""Évaluation du risque équipements pour un batch de foyers.

Même architecture que ``app/coupures/risk.py`` :
  1. Dériver les features depuis le contexte brut (``FoyerContexte``)
  2. Appliquer les règles déterministes (plancher de sécurité)
  3. Appliquer le modèle si disponible
  4. Retenir le MAXIMUM des deux (le modèle ne peut jamais réduire le risque)

RÈGLE DE SÉCURITÉ ABSOLUE : équipement vital (criticité « vitale ») ou médical
→ alerte_vitale = True et niveau final au moins « critique », indépendamment du
niveau prédit. Même définition que côté PHP (ConseilAiService / ConseilRiskService).
"""

from __future__ import annotations

from datetime import datetime
from typing import Any

from .features import build_features
from .model import ORDRE, ConseilRiskModel
from .rules import label_risque, niveau_plus_grave
from .schemas import FoyerContexte, FoyerResultat


def _enrichir_contexte(foyer: FoyerContexte) -> dict[str, Any]:
    """Déduit les compteurs agrégés depuis la liste d'équipements."""

    contexte = foyer.model_dump()

    equipements = foyer.equipements
    nb_medicaux = sum(1 for e in equipements if e.medical or e.type in ("respirateur", "medical_autre"))
    nb_vitaux   = sum(1 for e in equipements if e.criticite == "vitale")

    criticite_scores = {"normale": 0, "elevee": 1, "vitale": 2}
    criticite_max = max(
        (criticite_scores.get(e.criticite, 0) for e in equipements),
        default=0,
    )

    types = [e.type for e in equipements]

    contexte.update({
        "nb_equipements":   len(equipements),
        "nb_medicaux":      nb_medicaux,
        "nb_vitaux":        nb_vitaux,
        "criticite_max":    criticite_max,
        "a_respirateur":    int("respirateur" in types),
        "a_refrigerateur":  int("refrigerateur" in types),
        "a_onduleur":       int("onduleur" in types),
        "types_equipements": types,
        # Heure par défaut si absente
        "heure": foyer.heure if foyer.heure is not None else datetime.now().hour,
        "mois":  foyer.mois  if foyer.mois  is not None else datetime.now().month,
    })

    return contexte


def _raison(
    niveau_regles: str,
    niveau_modele: str | None,
    niveau_final: str,
    modele_ok: bool,
    confiance: float,
    alerte_vitale: bool,
) -> str:
    if alerte_vitale:
        return (
            "Équipement vital détecté : niveau critique garanti "
            "par la règle de sécurité absolue."
        )
    if not modele_ok:
        return (
            f"Règles seules : niveau {niveau_regles}. "
            "Modèle IA indisponible — classification déterministe appliquée."
        )
    if niveau_final == niveau_modele and niveau_final == niveau_regles:
        return f"Règles et modèle alignés : {niveau_final} (confiance {int(confiance*100)} %)."
    if ORDRE.get(niveau_final, 0) > ORDRE.get(niveau_regles, 0):
        return (
            f"Règles : {niveau_regles} — Modèle : {niveau_modele} "
            f"(confiance {int(confiance*100)} %). "
            "Le modèle a escaladé le niveau (il ne peut jamais le réduire)."
        )
    return (
        f"Règles : {niveau_regles} — Modèle : {niveau_modele} "
        f"(confiance {int(confiance*100)} %). "
        "Le plancher des règles est conservé."
    )


def evaluate_foyer(foyer: FoyerContexte, model: ConseilRiskModel) -> FoyerResultat:
    """Évalue le risque d'un foyer unique."""

    contexte  = _enrichir_contexte(foyer)
    features  = build_features(contexte)

    # RÈGLE DE SÉCURITÉ ABSOLUE : équipement vital OU médical → alerte_vitale.
    # Définition identique à ConseilAiService::aEquipementVital (PHP).
    alerte_vitale = contexte.get("nb_vitaux", 0) > 0 or contexte.get("nb_medicaux", 0) > 0

    # Plancher déterministe
    niveau_r = label_risque(contexte)

    modele_ok = model is not None and model.available

    if modele_ok:
        niveau_m, probas, confiance = model.predict(features)
        facteurs    = model.facteurs(features)
        niveau_f    = niveau_plus_grave(niveau_r, niveau_m)
        desaccord   = niveau_m != niveau_r
        version     = model.version
        source      = "modele"
    else:
        niveau_m  = None
        probas    = {}
        confiance = 0.0
        facteurs  = []
        niveau_f  = niveau_r
        desaccord = False
        version   = None
        source    = "regles"

    # RÈGLE ABSOLUE : si équipement vital → jamais en dessous de "critique"
    if alerte_vitale and ORDRE.get(niveau_f, 0) < ORDRE["critique"]:
        niveau_f = "critique"

    return FoyerResultat(
        foyer_id      = foyer.foyer_id,
        niveau_final  = niveau_f,
        niveau_modele = niveau_m,
        niveau_regles = niveau_r,
        desaccord     = desaccord,
        confiance     = confiance,
        probabilites  = probas,
        facteurs      = facteurs,
        alerte_vitale = alerte_vitale,
        raison        = _raison(niveau_r, niveau_m, niveau_f, modele_ok, confiance, alerte_vitale),
        source        = source,
        model_version = version,
    )


def evaluate_batch(foyers: list[FoyerContexte], model: ConseilRiskModel) -> list[FoyerResultat]:
    """Évalue un batch de foyers."""
    return [evaluate_foyer(f, model) for f in foyers]
