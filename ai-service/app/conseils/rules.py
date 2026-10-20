"""Règles déterministes de risque équipements — plancher de sécurité.

Ces règles sont le MIROIR PYTHON de la logique PHP existante dans
``App\\Services\\ConseilAiService``. Elles jouent le même rôle que
``app/rules.py`` pour la canicule : jamais remplacées par le modèle,
uniquement utilisées comme plancher (le niveau final ne peut jamais être
inférieur à ce que les règles donnent).

Niveaux : faible < modere < eleve < critique
"""

from __future__ import annotations

from typing import Any

NIVEAUX = ("faible", "modere", "eleve", "critique")
ORDRE:   dict[str, int] = {n: i for i, n in enumerate(NIVEAUX)}


def niveau_regles(contexte: dict[str, Any]) -> str:
    """Calcule le niveau de risque déterministe pour un foyer.

    Paramètres attendus dans ``contexte`` :
        nb_medicaux          : int   — nombre d'équipements médicaux
        nb_vitaux            : int   — nombre d'équipements en criticité vitale
        criticite_max        : int   — 0=normale, 1=elevee, 2=vitale
        temperature          : float — température actuelle (°C)
        canicule_niveau      : int   — 0..3
        coupures_en_cours    : int   — 0 ou 1
        jours_chauds_consecutifs : int
    """

    def entier(nom: str) -> int:
        # Une valeur absente ou None (météo indisponible) vaut 0, jamais une exception.
        brut = contexte.get(nom)
        return int(brut) if brut is not None else 0

    nb_medicaux   = entier("nb_medicaux")
    nb_vitaux     = entier("nb_vitaux")
    criticite_max = entier("criticite_max")
    canicule      = entier("canicule_niveau")
    coupure       = entier("coupures_en_cours")
    jours_chauds  = entier("jours_chauds_consecutifs")

    # --- Règle 1 : équipement vital → toujours au moins « critique » si coupure
    #               ou « eleve » sinon.
    if nb_vitaux > 0:
        if coupure:
            return "critique"
        return "eleve"

    # --- Règle 2 : équipement médical (sans vital)
    if nb_medicaux > 0:
        if coupure and canicule >= 2:
            return "critique"
        if coupure or canicule >= 2:
            return "eleve"
        if canicule >= 1:
            return "modere"
        return "modere"

    # --- Règle 3 : criticité élevée sans médical
    if criticite_max >= 1:
        if coupure and canicule >= 2:
            return "eleve"
        if coupure or canicule >= 2:
            return "modere"
        return "faible"

    # --- Règle 4 : aucun équipement sensible particulier
    if coupure and canicule >= 2:
        return "modere"
    if canicule >= 3 and jours_chauds >= 3:
        return "modere"
    return "faible"


def label_risque(contexte: dict[str, Any]) -> str:
    """Alias explicite pour l'entraînement (même interface que ``labeler.label``)."""
    return niveau_regles(contexte)


def niveau_plus_grave(a: str, b: str) -> str:
    """Retourne le niveau le plus grave entre deux."""
    return a if ORDRE.get(a, 0) >= ORDRE.get(b, 0) else b
