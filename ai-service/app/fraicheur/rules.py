"""Règles déterministes de santé d'un point de fraîcheur — PORT de ``SantePointService``.

Référence « métier » du module 3 : PLANCHER DE SÉCURITÉ
(``score_final = max(score_modele, score_regles)``) et REPLI quand le modèle
entraîné est indisponible. Doit rester STRICTEMENT aligné avec le PHP — le test
de parité ``tests/test_fraicheur_rules.py`` (fixture partagée
``tests/fixtures/fraicheur_rules_cases.json``) le garantit.

Formule du score (0–100), identique au PHP :

  note récente  (0–35) : note moyenne des avis des 30 j (<=2 : 35, <=2,5 : 25, <=3 : 15)
  négatifs      (0–25) : part d'avis négatifs sur 30 j × 25 (au moins 2 avis)
  tendance      (0–15) : note en baisse vs les 90 j précédents (<=-1 : 15, <=-0,5 : 8)
  plaintes      (0–15) : 5 points par plainte récurrente (propreté, chaleur, équipements)
  pression      (0–10) : vigilance canicule >= 2 (+5), affluence forte >= 50 % (+5)
"""

from __future__ import annotations

import math
from decimal import ROUND_HALF_UP, Decimal
from typing import Any

FENETRE_JOURS = 30
HORIZON_JOURS = 14

NIVEAUX = ("bon", "a_surveiller", "degrade", "critique")


def _int(value: Any, default: int = 0) -> int:
    if value is None or isinstance(value, bool):
        return default
    try:
        return int(float(value))
    except (TypeError, ValueError):
        return default


def _float(value: Any) -> float | None:
    if value is None or isinstance(value, bool):
        return None
    try:
        resultat = float(value)
    except (TypeError, ValueError):
        return None
    return resultat if math.isfinite(resultat) else None


def _part(value: Any) -> float:
    brut = _float(value)
    return max(0.0, min(1.0, brut)) if brut is not None else 0.0


def _arrondi(value: float) -> int:
    """Arrondi « .5 vers le haut » robuste, identique au PHP
    ``(int) floor($v + 0.5 + 1e-9)`` (évite les écarts de représentation)."""

    return int(math.floor(value + 0.5 + 1e-9))


def format_note(value: float) -> str:
    """Équivalent de ``number_format($value, 1, ',', '')`` : 1 décimale, virgule."""

    decimal = Decimal(str(value)).quantize(Decimal("0.1"), rounding=ROUND_HALF_UP)

    return str(decimal).replace(".", ",")


def niveau_sante(score: int) -> str:
    """Paliers de l'enum PHP ``SantePoint::fromScore`` : 75 / 50 / 25."""

    if score >= 75:
        return "critique"
    if score >= 50:
        return "degrade"
    if score >= 25:
        return "a_surveiller"

    return "bon"


def score_depuis_probabilite(p14: float) -> int:
    """Mapping documenté ``score = arrondi(100 * p14)`` borné 0..100."""

    borne = max(0.0, min(1.0, float(p14)))

    return max(0, min(100, _arrondi(borne * 100)))


def _lecture(contexte: dict[str, Any]) -> dict[str, Any]:
    """Normalise le contexte brut (mêmes conversions que le PHP)."""

    nb = max(0, _int(contexte.get("nb_avis_30j")))
    note = _float(contexte.get("note_moyenne_30j"))

    return {
        "nb": nb,
        "note": max(1.0, min(5.0, note)) if note is not None else None,
        "part_negatifs": _part(contexte.get("part_negatifs_30j")),
        "evolution": _float(contexte.get("evolution_note_30j")) or 0.0,
        "proprete": max(0, _int(contexte.get("plaintes_proprete_30j"))),
        "chaleur": max(0, _int(contexte.get("plaintes_chaleur_30j"))),
        "equipements": max(0, _int(contexte.get("plaintes_equipements_30j"))),
        "canicule": max(0, min(3, _int(contexte.get("canicule_niveau")))),
        "part_affluence": _part(contexte.get("part_affluence_forte_30j")),
    }


def _composantes(v: dict[str, Any]) -> dict[str, int]:
    nb = v["nb"]
    note = v["note"]

    # 1) Note récente (0–35) : au moins un avis sur 30 j.
    if nb >= 1 and note is not None:
        if note <= 2.0:
            score_note = 35
        elif note <= 2.5:
            score_note = 25
        elif note <= 3.0:
            score_note = 15
        else:
            score_note = 0
    else:
        score_note = 0

    # 2) Part d'avis négatifs (0–25) : au moins deux avis (un seul = bruit).
    score_negatifs = _arrondi(v["part_negatifs"] * 25) if nb >= 2 else 0

    # 3) Tendance de la note vs les 90 j précédents (0–15).
    if nb >= 2 and v["evolution"] <= -1.0:
        score_tendance = 15
    elif nb >= 2 and v["evolution"] <= -0.5:
        score_tendance = 8
    else:
        score_tendance = 0

    # 4) Plaintes récurrentes dans les avis négatifs (0–15).
    plaintes = v["proprete"] + v["chaleur"] + v["equipements"]
    score_plaintes = min(15, 5 * plaintes)

    # 5) Pression : canicule forte + saturation ressentie (0–10).
    score_pression = (5 if v["canicule"] >= 2 else 0) + (
        5 if nb >= 2 and v["part_affluence"] >= 0.5 else 0
    )

    return {
        "note": score_note,
        "negatifs": score_negatifs,
        "tendance": score_tendance,
        "plaintes": score_plaintes,
        "pression": score_pression,
    }


def _facteurs(v: dict[str, Any], c: dict[str, int]) -> list[str]:
    facteurs: list[str] = []

    if c["note"] > 0:
        facteurs.append(f"Note moyenne sur {FENETRE_JOURS} j : {format_note(v['note'])}/5")
    if c["negatifs"] > 0:
        facteurs.append(f"{_arrondi(v['part_negatifs'] * 100)} % d'avis négatifs sur {FENETRE_JOURS} j")
    if c["tendance"] > 0:
        facteurs.append(f"Note en baisse de {format_note(abs(v['evolution']))} point(s)")
    if v["proprete"] > 0:
        facteurs.append(f"{v['proprete']} plainte(s) « propreté »")
    if v["chaleur"] > 0:
        facteurs.append(f"{v['chaleur']} plainte(s) « chaleur / climatisation »")
    if v["equipements"] > 0:
        facteurs.append(f"{v['equipements']} plainte(s) « équipements »")
    if v["canicule"] >= 2:
        facteurs.append(f"Vigilance canicule {v['canicule']}/3")
    if v["nb"] >= 2 and v["part_affluence"] >= 0.5:
        facteurs.append(f"Affluence forte signalée dans {_arrondi(v['part_affluence'] * 100)} % des avis")

    return facteurs


def actions(contexte: dict[str, Any], score: int) -> list[str]:
    """Actions conseillées à l'administrateur (déterministes, ordre fixe).

    ``score`` est le score FINAL : un point jugé à risque par le modèle seul
    reçoit au moins la consigne de surveillance.
    """

    v = _lecture(contexte)
    c = _composantes(v)
    conseils: list[str] = []

    if v["proprete"] > 0:
        conseils.append("Programmer un nettoyage du site")
    if v["chaleur"] > 0:
        conseils.append("Vérifier la climatisation, l'ombrage et l'accès à l'eau")
    if v["equipements"] > 0:
        conseils.append("Réparer les équipements signalés")
    if v["nb"] >= 2 and v["part_affluence"] >= 0.5:
        conseils.append("Augmenter la capacité d'accueil ou orienter vers un point voisin")
    if c["note"] >= 25 or c["tendance"] >= 8:
        conseils.append("Inspecter le site et contacter son gestionnaire")
    if not conseils and score >= 25:
        conseils.append("Surveiller les prochains avis")

    return conseils


def score_regles(contexte: dict[str, Any]) -> dict[str, Any]:
    """Score déterministe d'un point + justification (aucune dépendance base)."""

    v = _lecture(contexte)
    c = _composantes(v)

    score = max(0, min(100, sum(c.values())))

    note_txt = f"{format_note(v['note'])}/5" if v["nb"] >= 1 and v["note"] is not None else "—"
    raison = (
        f"{v['nb']} avis sur {FENETRE_JOURS} j · note récente {note_txt} "
        f"· {_arrondi(v['part_negatifs'] * 100)} % négatifs"
    )

    return {
        "score": score,
        "niveau_regles": niveau_sante(score),
        "raison": raison,
        "facteurs": _facteurs(v, c),
        "actions": actions(contexte, score),
        "composantes": c,
    }
