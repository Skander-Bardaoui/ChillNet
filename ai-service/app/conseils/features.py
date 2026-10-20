"""Feature engineering du modèle de risque équipements (Module 4).

SOURCE DE VÉRITÉ UNIQUE : l'entraînement, l'évaluation et l'inférence
partagent ce module. Laravel envoie un contexte brut ; toute la dérivation
vit ici.

Ne jamais réordonner VECTOR_ORDER sans ré-entraîner le modèle.
"""

from __future__ import annotations

from typing import Any

from app.features import heat_index_c

# Ordre STRICT du vecteur de features — aligné avec simulate_conseils.py
VECTOR_ORDER: list[str] = [
    "nb_equipements",
    "nb_medicaux",
    "nb_vitaux",
    "criticite_max",          # 0=normale 1=elevee 2=vitale
    "a_respirateur",          # 0 ou 1
    "a_refrigerateur",        # 0 ou 1
    "a_onduleur",             # 0 ou 1
    "temperature",
    "ressentie",
    "heat_index",
    "canicule_niveau",        # 0..3
    "jours_chauds_consecutifs",
    "coupures_en_cours",      # 0 ou 1
    "coupures_7j",
    "duree_moy_coupures_h",
    "mois",
    "heure",
]

# Valeurs par défaut sûres (médianes estivales tunisiennes)
SAFE_DEFAULTS: dict[str, float] = {
    "nb_equipements":           1.0,
    "nb_medicaux":              0.0,
    "nb_vitaux":                0.0,
    "criticite_max":            0.0,
    "a_respirateur":            0.0,
    "a_refrigerateur":          1.0,
    "a_onduleur":               0.0,
    "temperature":              32.0,
    "ressentie":                33.0,
    "heat_index":               33.0,
    "canicule_niveau":          1.0,
    "jours_chauds_consecutifs": 1.0,
    "coupures_en_cours":        0.0,
    "coupures_7j":              1.0,
    "duree_moy_coupures_h":     1.0,
    "mois":                     7.0,
    "heure":                    14.0,
}

# Labels FR pour l'affichage des facteurs explicatifs
FACTEUR_LABELS: dict[str, str] = {
    "nb_equipements":           "Nombre d'équipements",
    "nb_medicaux":              "Équipements médicaux",
    "nb_vitaux":                "Équipements vitaux",
    "criticite_max":            "Criticité maximale",
    "a_respirateur":            "Respirateur présent",
    "a_refrigerateur":          "Réfrigérateur présent",
    "a_onduleur":               "Onduleur présent",
    "temperature":              "Température",
    "ressentie":                "Température ressentie",
    "heat_index":               "Indice de chaleur",
    "canicule_niveau":          "Niveau canicule",
    "jours_chauds_consecutifs": "Jours chauds consécutifs",
    "coupures_en_cours":        "Coupure en cours",
    "coupures_7j":              "Coupures sur 7 j",
    "duree_moy_coupures_h":     "Durée moy. coupures (h)",
    "mois":                     "Mois",
    "heure":                    "Heure",
}


def _num(value: Any, default: float | None = None) -> float | None:
    """Convertit une valeur en float, ou ``default`` si impossible."""
    if value is None or isinstance(value, bool):
        return default
    try:
        return float(value)
    except (TypeError, ValueError):
        return default


def _criticite_to_score(criticite: str | None) -> int:
    """Convertit une criticité texte en score 0-2."""
    mapping = {"normale": 0, "elevee": 1, "vitale": 2}
    return mapping.get(str(criticite).lower(), 0)


def build_features(contexte: dict[str, Any]) -> dict[str, float]:
    """Construit le vecteur de features depuis un contexte brut.

    Accepte indifféremment les contextes venant de Laravel (noms PHP)
    ou du CSV d'entraînement. Ne lève jamais d'exception.
    """

    def val(nom: str) -> float:
        """Lit une valeur ou retourne le défaut sûr."""
        brut = _num(contexte.get(nom))
        return float(brut) if brut is not None else SAFE_DEFAULTS[nom]

    # Température et dérivés
    temperature = val("temperature")
    humidite    = _num(contexte.get("humidite"))
    hi_brut     = _num(contexte.get("heat_index"))
    heat_index  = hi_brut if hi_brut is not None else (heat_index_c(temperature, humidite) or temperature)
    ressentie   = _num(contexte.get("ressentie"))
    ressentie   = ressentie if ressentie is not None else heat_index

    # Niveau canicule
    canicule_brut = _num(contexte.get("canicule_niveau"))
    if canicule_brut is not None:
        canicule = max(0, min(3, int(canicule_brut)))
    else:
        # Dériver depuis la température si absent
        temp = temperature
        canicule = 3 if temp >= 41 else 2 if temp >= 38 else 1 if temp >= 35 else 0

    # Criticité max : accepte int (0-2) ou str ("normale"/"elevee"/"vitale")
    criticite_raw = contexte.get("criticite_max")
    if isinstance(criticite_raw, str):
        criticite_max = float(_criticite_to_score(criticite_raw))
    else:
        criticite_max = float(_num(criticite_raw) or 0.0)
    criticite_max = max(0.0, min(2.0, criticite_max))

    # Équipements booléens : accepte liste (depuis Laravel) ou entier (depuis CSV)
    types_list = contexte.get("types_equipements") or []
    if isinstance(types_list, list):
        a_respirateur   = float(any(t in ("respirateur", "medical_autre") for t in types_list))
        a_refrigerateur = float("refrigerateur" in types_list)
        a_onduleur      = float("onduleur" in types_list)
    else:
        a_respirateur   = float(val("a_respirateur"))
        a_refrigerateur = float(val("a_refrigerateur"))
        a_onduleur      = float(val("a_onduleur"))

    # Heure de la journée
    heure_brut = _num(contexte.get("heure"))
    heure = float(int(heure_brut) % 24) if heure_brut is not None else SAFE_DEFAULTS["heure"]

    return {
        "nb_equipements":           val("nb_equipements"),
        "nb_medicaux":              val("nb_medicaux"),
        "nb_vitaux":                val("nb_vitaux"),
        "criticite_max":            criticite_max,
        "a_respirateur":            a_respirateur,
        "a_refrigerateur":          a_refrigerateur,
        "a_onduleur":               a_onduleur,
        "temperature":              float(temperature),
        "ressentie":                float(ressentie),
        "heat_index":               float(heat_index),
        "canicule_niveau":          float(canicule),
        "jours_chauds_consecutifs": val("jours_chauds_consecutifs"),
        "coupures_en_cours":        val("coupures_en_cours"),
        "coupures_7j":              val("coupures_7j"),
        "duree_moy_coupures_h":     val("duree_moy_coupures_h"),
        "mois":                     val("mois"),
        "heure":                    heure,
    }


def vector(features: dict[str, float]) -> list[float]:
    """Ordonne un dictionnaire de features selon ``VECTOR_ORDER``."""
    return [float(features.get(nom, SAFE_DEFAULTS[nom])) for nom in VECTOR_ORDER]


def format_valeur(feature: str, value: float) -> str:
    """Formate une valeur pour l'affichage humain (FR)."""
    if feature in ("temperature", "ressentie", "heat_index"):
        return f"{value:.1f} °C"
    if feature == "canicule_niveau":
        labels = {0: "absent", 1: "jaune", 2: "orange", 3: "rouge"}
        return labels.get(int(round(value)), str(int(value)))
    if feature == "criticite_max":
        labels = {0: "normale", 1: "élevée", 2: "vitale"}
        return labels.get(int(round(value)), str(int(value)))
    if feature in ("a_respirateur", "a_refrigerateur", "a_onduleur", "coupures_en_cours"):
        return "oui" if value >= 0.5 else "non"
    if feature == "duree_moy_coupures_h":
        return f"{value:.1f} h"
    if feature == "heure":
        return f"{int(round(value))} h"
    return f"{int(round(value))}"
