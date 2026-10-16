"""Règles déterministes de risque de coupure — PORT de ``CoupureRiskAiService``.

Ce module est la référence « métier » du module 2 : il sert de PLANCHER DE
SÉCURITÉ (``score_final = max(score_modele, score_regles)``) et de REPLI quand
le modèle entraîné est indisponible. Il doit rester STRICTEMENT aligné avec
l'implémentation PHP — le test de parité ``tests/test_coupures_rules.py``
(fixture partagée ``tests/fixtures/coupure_rules_cases.json``) le garantit.

Formule du score (0–100), identique au PHP :

  fréquence  (0–35) : historique de coupures sur 14 jours, 7 points par coupure
  type       (0–20) : part des coupures « chaudes » (surcharge / délestage)
  canicule   (0–35) : alerte couvrant la zone, sinon pic de température ≥ 35 °C
  activité   (0–10) : coupures en cours + confirmations des voisins
"""

from __future__ import annotations

from decimal import ROUND_HALF_UP, Decimal
from typing import Any

FENETRE_HISTORIQUE_JOURS = 14
FENETRE_ANOMALIE_MINUTES = 120
SEUIL_ANOMALIE = 3

# Libellés des alertes canicule (alignés sur l'enum PHP NiveauAlerte::label()).
LABEL_ALERTE: dict[str, str] = {
    "jaune": "Vigilance jaune",
    "orange": "Vigilance orange",
    "rouge": "Vigilance rouge",
}


def _int(value: Any, default: int = 0) -> int:
    if isinstance(value, bool):
        return default
    try:
        return int(value)
    except (TypeError, ValueError):
        return default


def _float(value: Any) -> float | None:
    if value is None or isinstance(value, bool):
        return None
    try:
        return float(value)
    except (TypeError, ValueError):
        return None


def _round_half_up(value: float) -> int:
    """Équivalent de ``round()`` PHP (arrondi des .5 vers le haut).

    Python utilise le « banker's rounding » ; on reproduit PHP pour une parité
    stricte avec ``CoupureRiskAiService``.
    """

    return int(value + 0.5) if value >= 0 else -int(-value + 0.5)


def _format_temperature(value: float) -> str:
    """Équivalent de ``number_format($value, 1, ',', '')`` : 1 décimale, virgule."""

    decimal = Decimal(str(value)).quantize(Decimal("0.1"), rounding=ROUND_HALF_UP)

    return str(decimal).replace(".", ",")


def _niveau_alerte(value: Any) -> str | None:
    return value if value in LABEL_ALERTE else None


def niveau_risque(score: int) -> str:
    """Paliers ``NiveauRisque::fromScore`` (inchangés) : 75 / 50 / 25."""

    if score >= 75:
        return "critique"
    if score >= 50:
        return "eleve"
    if score >= 25:
        return "modere"

    return "faible"


def score_depuis_probabilite(p24: float) -> int:
    """Mapping documenté ``score = round(100 * p24)`` borné 0..100.

    Le score du modèle est exprimé sur la même échelle que les règles, ce qui
    permet le plancher de sécurité ``score_final = max(score_modele, score_regles)``
    et l'application des paliers ``NiveauRisque`` inchangés.
    """

    borne = max(0.0, min(1.0, float(p24)))

    return max(0, min(100, _round_half_up(borne * 100)))


def _facteurs(
    frequence: int,
    chaudes: int,
    niveau_chaleur: str | None,
    temperature: float | None,
    en_cours: int,
) -> list[str]:
    facteurs: list[str] = []

    if frequence > 0:
        facteurs.append(
            f"{frequence} coupure(s) ces {FENETRE_HISTORIQUE_JOURS} derniers jours"
        )
    if chaudes > 0:
        facteurs.append(f"{chaudes} coupure(s) de type surcharge/délestage")
    if niveau_chaleur is not None:
        facteurs.append(LABEL_ALERTE[niveau_chaleur] + " en cours")
    if temperature is not None:
        facteurs.append(f"Pic de température {_format_temperature(temperature)}°C")
    if en_cours > 0:
        facteurs.append(f"{en_cours} coupure(s) en cours")

    return facteurs


def score_regles(contexte: dict[str, Any]) -> dict[str, Any]:
    """Score déterministe d'une zone + justification (aucune dépendance base).

    ``contexte`` accepte : ``frequence``, ``en_cours``, ``chaudes``,
    ``confirmations``, ``niveau_chaleur`` (jaune|orange|rouge|None) et
    ``temperature`` (pic, °C).
    """

    frequence = max(0, _int(contexte.get("frequence")))
    en_cours = max(0, _int(contexte.get("en_cours")))
    chaudes = max(0, _int(contexte.get("chaudes")))
    confirmations = max(0, _int(contexte.get("confirmations")))
    niveau_chaleur = _niveau_alerte(contexte.get("niveau_chaleur"))
    temperature = _float(contexte.get("temperature"))

    # 1) Fréquence récente (0–35).
    score_frequence = min(35, frequence * 7)

    # 2) Part des coupures « liées à la chaleur » (0–20).
    score_type = (
        _round_half_up(min(20.0, (chaudes / frequence) * 20)) if frequence > 0 else 0
    )

    # 3) Canicule : alerte couvrant la zone + pic de température (0–35).
    if niveau_chaleur == "rouge":
        score_chaleur = 35
    elif niveau_chaleur == "orange":
        score_chaleur = 22
    elif niveau_chaleur == "jaune":
        score_chaleur = 10
    else:
        score_chaleur = 12 if (temperature is not None and temperature >= 35) else 0

    # 4) Activité en cours + confirmations des voisins (0–10).
    score_actif = min(10, en_cours * 4 + (2 if confirmations >= 3 else 0))

    score = max(0, min(100, score_frequence + score_type + score_chaleur + score_actif))

    alerte_label = LABEL_ALERTE[niveau_chaleur] if niveau_chaleur else "aucune"
    raison = (
        f"{frequence} coupure(s) en {FENETRE_HISTORIQUE_JOURS} j "
        f"· alerte {alerte_label} · {en_cours} en cours"
    )

    return {
        "score": score,
        "niveau_regles": niveau_risque(score),
        "raison": raison,
        "facteurs": _facteurs(frequence, chaudes, niveau_chaleur, temperature, en_cours),
        "frequence": frequence,
        "en_cours": en_cours,
        "temperature": temperature,
    }
