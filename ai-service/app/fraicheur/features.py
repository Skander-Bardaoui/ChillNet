"""Feature engineering du modèle de santé des points de fraîcheur (module 3).

SOURCE DE VÉRITÉ UNIQUE : la simulation (`scripts/simulate_fraicheur.py`),
l'entraînement (`scripts/train_fraicheur.py`), l'évaluation et l'inférence
(`model.py`) partagent ce module. Laravel (`SantePointService::contexte`) envoie
un CONTEXTE BRUT calculé avec exactement les mêmes définitions :

  - avis NÉGATIF       : note <= 2 OU sentiment « negatif » ;
  - fenêtre « 30 j »   : avis créés dans les 30 derniers jours ;
  - fenêtre précédente : avis créés entre J-120 et J-30 (pour la tendance) ;
  - plaintes           : avis négatifs des 30 j dont le commentaire évoque le
                         thème (propreté / chaleur / équipements).

La cible (``label_14j``) vaut 1 si le point reçoit AU MOINS DEUX avis négatifs
dans les 14 jours qui suivent la capture.
"""

from __future__ import annotations

from datetime import datetime
from typing import Any

# Codes des types de point (alignés sur l'enum PHP TypePointFraicheur).
TYPE_CODES: dict[str, int] = {"parc": 0, "salle_climatisee": 1, "fontaine": 2}
TYPE_LABELS: dict[int, str] = {0: "parc", 1: "salle climatisée", 2: "fontaine"}

# Ordre STRICT du vecteur : jamais le réordonner sans ré-entraîner.
VECTOR_ORDER: list[str] = [
    # --- Le point de fraîcheur ---
    "type_code",
    "capacite",
    "climatise",
    "ombrage",
    "eau_potable",
    "accessible_pmr",
    "heures_ouverture_jour",
    "anciennete_jours",
    "a_photo",
    # --- Ses avis ---
    "note_moyenne",
    "nb_avis_30j",
    "note_moyenne_30j",
    "evolution_note_30j",
    "part_negatifs_30j",
    "jours_depuis_dernier_negatif",
    "part_affluence_forte_30j",
    "sentiment_moyen_30j",
    "plaintes_proprete_30j",
    "plaintes_chaleur_30j",
    "plaintes_equipements_30j",
    # --- Le contexte ---
    "temperature",
    "canicule_niveau",
    "mois",
]

# Contraintes de MONOTONIE passées au classifieur : +1 = la hausse de la feature
# ne peut jamais FAIRE BAISSER le risque ; -1 = ne peut jamais le faire MONTER.
MONOTONIC_FEATURES: dict[str, int] = {
    "part_negatifs_30j": 1,
    "plaintes_proprete_30j": 1,
    "plaintes_chaleur_30j": 1,
    "plaintes_equipements_30j": 1,
    "canicule_niveau": 1,
    "note_moyenne": -1,
    "note_moyenne_30j": -1,
}

# Prior bayésien de la note moyenne (identique au moteur de recommandation PHP).
NOTE_A_PRIORI = 3.5
POIDS_A_PRIORI = 3

# Plafond de « jours depuis le dernier avis négatif » (aucun = 90).
CAP_JOURS_NEGATIF = 90.0

SAFE_DEFAULTS: dict[str, float] = {
    "type_code": 0,
    "capacite": 0,
    "climatise": 0,
    "ombrage": 0,
    "eau_potable": 0,
    "accessible_pmr": 0,
    "heures_ouverture_jour": 12,
    "anciennete_jours": 365,
    "a_photo": 0,
    "note_moyenne": NOTE_A_PRIORI,
    "nb_avis_30j": 0,
    "note_moyenne_30j": NOTE_A_PRIORI,
    "evolution_note_30j": 0.0,
    "part_negatifs_30j": 0.0,
    "jours_depuis_dernier_negatif": CAP_JOURS_NEGATIF,
    "part_affluence_forte_30j": 0.0,
    "sentiment_moyen_30j": 0.0,
    "plaintes_proprete_30j": 0,
    "plaintes_chaleur_30j": 0,
    "plaintes_equipements_30j": 0,
    "temperature": 30.0,
    "canicule_niveau": 0,
    "mois": 7,
}

FACTEUR_LABELS: dict[str, str] = {
    "type_code": "Type de point",
    "capacite": "Capacité d'accueil",
    "climatise": "Climatisé",
    "ombrage": "Ombragé",
    "eau_potable": "Eau potable",
    "accessible_pmr": "Accessible PMR",
    "heures_ouverture_jour": "Heures d'ouverture par jour",
    "anciennete_jours": "Ancienneté du point",
    "a_photo": "Photo publiée",
    "note_moyenne": "Note moyenne (tous avis)",
    "nb_avis_30j": "Avis sur 30 j",
    "note_moyenne_30j": "Note moyenne sur 30 j",
    "evolution_note_30j": "Évolution de la note",
    "part_negatifs_30j": "Part d'avis négatifs sur 30 j",
    "jours_depuis_dernier_negatif": "Jours depuis le dernier avis négatif",
    "part_affluence_forte_30j": "Affluence forte signalée",
    "sentiment_moyen_30j": "Sentiment moyen des commentaires",
    "plaintes_proprete_30j": "Plaintes « propreté »",
    "plaintes_chaleur_30j": "Plaintes « chaleur / climatisation »",
    "plaintes_equipements_30j": "Plaintes « équipements »",
    "temperature": "Température",
    "canicule_niveau": "Niveau de canicule",
    "mois": "Mois",
}

MOIS_FR = (
    "janvier", "février", "mars", "avril", "mai", "juin",
    "juillet", "août", "septembre", "octobre", "novembre", "décembre",
)

# Seuils de canicule (0..3) dérivés de la température (mêmes bornes que le
# module 2 : <35 / 35–38 / 38–41 / >=41 °C).
SEUILS_CANICULE = (35.0, 38.0, 41.0)

_BOOLEENS = ("climatise", "ombrage", "eau_potable", "accessible_pmr", "a_photo")
_PARTS = ("part_negatifs_30j", "part_affluence_forte_30j")


def _num(value: Any, default: float | None = None) -> float | None:
    if value is None:
        return default
    if isinstance(value, bool):
        return float(value)
    try:
        resultat = float(value)
    except (TypeError, ValueError):
        return default
    return resultat if resultat == resultat else default  # NaN → défaut


def canicule_niveau_depuis_temp(temp: float) -> int:
    if temp >= SEUILS_CANICULE[2]:
        return 3
    if temp >= SEUILS_CANICULE[1]:
        return 2
    if temp >= SEUILS_CANICULE[0]:
        return 1
    return 0


def type_code(contexte: dict[str, Any]) -> int:
    """Code du type : ``type_code`` explicite, sinon ``type`` (valeur d'enum)."""

    code = _num(contexte.get("type_code"))
    if code is not None and int(code) in TYPE_LABELS:
        return int(code)

    return TYPE_CODES.get(str(contexte.get("type") or "").lower(), 0)


def format_valeur(feature: str, value: float) -> str:
    """Valeur d'une feature, formatée pour l'affichage humain (FR)."""

    if feature in _PARTS:
        return f"{int(round(value * 100))} %"
    if feature in _BOOLEENS:
        return "oui" if value >= 0.5 else "non"
    if feature == "type_code":
        return TYPE_LABELS.get(int(round(value)), "parc")
    if feature in ("note_moyenne", "note_moyenne_30j"):
        return f"{value:.1f}/5".replace(".", ",")
    if feature == "evolution_note_30j":
        signe = "+" if value > 0 else ""
        return f"{signe}{value:.1f} pt".replace(".", ",")
    if feature == "sentiment_moyen_30j":
        return f"{value:+.2f}".replace(".", ",")
    if feature == "temperature":
        return f"{value:.1f} °C".replace(".", ",")
    if feature == "canicule_niveau":
        return f"{int(round(value))} / 3"
    if feature == "mois":
        return MOIS_FR[(int(round(value)) - 1) % 12]
    if feature in ("anciennete_jours", "jours_depuis_dernier_negatif"):
        return f"{int(round(value))} j"
    if feature == "heures_ouverture_jour":
        return f"{int(round(value))} h"
    if feature == "capacite":
        return f"{int(round(value))} pers." if value > 0 else "non renseignée"

    return f"{int(round(value))}"


def build_point_features(contexte: dict[str, Any]) -> dict[str, float]:
    """Construit le vecteur de features d'un point depuis un contexte brut.

    Ne lève jamais : chaque valeur manquante retombe sur un défaut sûr.
    """

    def valeur(nom: str) -> float:
        brut = _num(contexte.get(nom))
        return float(brut) if brut is not None else float(SAFE_DEFAULTS[nom])

    def borne(nom: str, bas: float, haut: float) -> float:
        return max(bas, min(haut, valeur(nom)))

    nb_30j = max(0.0, valeur("nb_avis_30j"))

    note_moyenne = borne("note_moyenne", 1.0, 5.0)

    # Sans avis récent, la note « 30 j » retombe sur la note globale : aucune
    # information nouvelle ne doit être inventée.
    note_30j_brute = _num(contexte.get("note_moyenne_30j"))
    note_30j = (
        max(1.0, min(5.0, note_30j_brute))
        if note_30j_brute is not None and nb_30j > 0
        else note_moyenne
    )

    temperature = borne("temperature", -20.0, 60.0)
    canicule_brut = _num(contexte.get("canicule_niveau"))
    canicule = (
        int(canicule_brut)
        if canicule_brut is not None
        else canicule_niveau_depuis_temp(temperature)
    )

    mois_brut = _num(contexte.get("mois"))
    mois = int(mois_brut) if mois_brut is not None and 1 <= mois_brut <= 12 else datetime.now().month

    return {
        "type_code": float(type_code(contexte)),
        "capacite": max(0.0, valeur("capacite")),
        "climatise": 1.0 if valeur("climatise") >= 0.5 else 0.0,
        "ombrage": 1.0 if valeur("ombrage") >= 0.5 else 0.0,
        "eau_potable": 1.0 if valeur("eau_potable") >= 0.5 else 0.0,
        "accessible_pmr": 1.0 if valeur("accessible_pmr") >= 0.5 else 0.0,
        "heures_ouverture_jour": borne("heures_ouverture_jour", 0.0, 24.0),
        "anciennete_jours": max(0.0, valeur("anciennete_jours")),
        "a_photo": 1.0 if valeur("a_photo") >= 0.5 else 0.0,
        "note_moyenne": note_moyenne,
        "nb_avis_30j": nb_30j,
        "note_moyenne_30j": note_30j,
        "evolution_note_30j": borne("evolution_note_30j", -4.0, 4.0) if nb_30j > 0 else 0.0,
        "part_negatifs_30j": borne("part_negatifs_30j", 0.0, 1.0) if nb_30j > 0 else 0.0,
        "jours_depuis_dernier_negatif": borne("jours_depuis_dernier_negatif", 0.0, CAP_JOURS_NEGATIF),
        "part_affluence_forte_30j": borne("part_affluence_forte_30j", 0.0, 1.0),
        "sentiment_moyen_30j": borne("sentiment_moyen_30j", -1.0, 1.0) if nb_30j > 0 else 0.0,
        "plaintes_proprete_30j": max(0.0, valeur("plaintes_proprete_30j")),
        "plaintes_chaleur_30j": max(0.0, valeur("plaintes_chaleur_30j")),
        "plaintes_equipements_30j": max(0.0, valeur("plaintes_equipements_30j")),
        "temperature": temperature,
        "canicule_niveau": float(max(0, min(3, canicule))),
        "mois": float(mois),
    }


def vector(features: dict[str, float]) -> list[float]:
    """Ordonne un dictionnaire de features selon ``VECTOR_ORDER``."""

    return [float(features.get(nom, SAFE_DEFAULTS[nom])) for nom in VECTOR_ORDER]


def monotonic_constraints() -> list[int]:
    """Contraintes de monotonie (+1 / 0 / -1) dans l'ordre de ``VECTOR_ORDER``."""

    return [MONOTONIC_FEATURES.get(nom, 0) for nom in VECTOR_ORDER]
