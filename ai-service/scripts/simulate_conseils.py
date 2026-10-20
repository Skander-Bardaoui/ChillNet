#!/usr/bin/env python
"""Génère le dataset synthétique ``data/equipements_risk_history.csv``.

Chaque ligne = un foyer à un instant donné (équipements, météo de la ville,
coupures de la zone). L'ÉTIQUETTE n'est PAS calculée par les règles de
``app/conseils/rules.py`` : elle simule un RÉSULTAT (gravité d'incident pour
le foyer) à partir d'un score latent qui dépend de variables que le modèle ne
voit pas :

  - durée réelle de la coupure en cours (le modèle ne connaît que la durée
    moyenne récente de la zone),
  - niveau de charge de la batterie de l'onduleur,
  - vulnérabilité du foyer (âge, état de santé),
  - bruit d'observation.

Conséquences voulues :
  - l'étiquette n'est pas une fonction déterministe des features → l'exactitude
    ne peut pas atteindre 100 % et mesure une vraie capacité de généralisation ;
  - le modèle apprend des INTERACTIONS (autonomie onduleur × durée de coupure,
    chaleur × coupure × sensibilité) que les règles ne codent pas ;
  - les règles restent le plancher de sécurité en production (cf. risk.py).

Données 100 % synthétiques — à documenter comme telles.

Usage : ``python scripts/simulate_conseils.py`` (depuis ai-service/).
"""

from __future__ import annotations

import csv
import math
import random
import sys
from collections import Counter
from datetime import datetime, timedelta
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent.parent
sys.path.insert(0, str(BASE_DIR))

from app.features import heat_index_c  # noqa: E402

# ---------------------------------------------------------------------------
# Paramètres de simulation
# ---------------------------------------------------------------------------
N_FOYERS    = 400
N_JOURS     = 90
DATE_DEBUT  = datetime(2024, 6, 1)
RANDOM_SEED = 42
OUTPUT_PATH = BASE_DIR / "data" / "equipements_risk_history.csv"
HEURES_SNAPSHOT = [8, 12, 16, 22]

# Seuils du score latent → classe (calibrés pour ~40/28/22/10 %).
SEUIL_MODERE   = 0.60
SEUIL_ELEVE    = 1.40
SEUIL_CRITIQUE = 2.80

TYPES_EQUIPEMENTS = [
    "refrigerateur", "climatiseur", "ventilateur", "respirateur",
    "medical_autre", "onduleur", "chauffe_eau", "autre",
]
TYPES_MEDICAUX = {"respirateur", "medical_autre"}
CRITICITES = ["normale", "elevee", "vitale"]

# Ville → (décalage thermique °C, humidité moyenne %)
VILLES = {
    "Tunis":   (0.0, 62),
    "Sfax":    (1.5, 58),
    "Sousse":  (0.5, 66),
    "Nabeul":  (-0.5, 68),
    "Bizerte": (-1.0, 70),
}

COLONNES = [
    "foyer_id", "timestamp",
    "nb_equipements", "nb_medicaux", "nb_vitaux", "criticite_max",
    "a_respirateur", "a_refrigerateur", "a_onduleur",
    "temperature", "ressentie", "humidite", "heat_index",
    "canicule_niveau", "jours_chauds_consecutifs",
    "coupures_en_cours", "coupures_7j", "duree_moy_coupures_h",
    "mois", "heure", "ville",
    "label", "source",
]


def _canicule_niveau(temp: float) -> int:
    if temp >= 41:
        return 3
    if temp >= 38:
        return 2
    if temp >= 35:
        return 1
    return 0


# ---------------------------------------------------------------------------
# Foyers
# ---------------------------------------------------------------------------

def _generer_foyer(rng: random.Random, foyer_id: int) -> dict:
    nb_eq = rng.randint(0, 5)
    types = rng.choices(TYPES_EQUIPEMENTS, k=nb_eq)
    criticites = [rng.choice(CRITICITES) for _ in range(nb_eq)]

    for i, t in enumerate(types):
        if t in TYPES_MEDICAUX and criticites[i] == "normale":
            criticites[i] = rng.choice(["elevee", "vitale"])

    score_crit = 2 if "vitale" in criticites else 1 if "elevee" in criticites else 0

    return {
        "foyer_id":        foyer_id,
        "nb_equipements":  nb_eq,
        "nb_medicaux":     sum(t in TYPES_MEDICAUX for t in types),
        "nb_vitaux":       sum(c == "vitale" for c in criticites),
        "criticite_max":   score_crit,
        "a_respirateur":   int("respirateur" in types),
        "a_refrigerateur": int("refrigerateur" in types),
        "a_onduleur":      int("onduleur" in types),
        "a_clim":          int("climatiseur" in types or "ventilateur" in types),  # caché
        "ville":           rng.choice(list(VILLES)),
        "vulnerabilite":   rng.gauss(0.0, 0.45),                                    # caché
    }


# ---------------------------------------------------------------------------
# Météo / zone (partagées par tous les foyers d'une même ville)
# ---------------------------------------------------------------------------

def _generer_meteo(rng: random.Random) -> dict:
    """(ville, jour, heure) → météo + coupures de zone. Anomalie AR(1) : vagues de chaleur."""
    meteo: dict[tuple[str, int, int], dict] = {}

    for ville, (decal, hum_moy) in VILLES.items():
        anomalie = 0.0
        jours_chauds = 0
        mu_zone = rng.uniform(0.7, 3.5)           # durée moyenne des coupures de la zone
        historique: list[int] = [0] * 7           # coupures par jour, 7 derniers jours

        for j in range(N_JOURS):
            dt_base = DATE_DEBUT + timedelta(days=j)
            anomalie = 0.75 * anomalie + rng.gauss(0, 2.2)
            tmax_jour = 28 + (dt_base.month - 6) * 3 + decal + anomalie + 5
            jours_chauds = min(jours_chauds + 1, 30) if tmax_jour >= 35 else max(0, jours_chauds - 1)

            coupures_jour = 0
            for h in HEURES_SNAPSHOT:
                base = 28 + (dt_base.month - 6) * 3 + decal + anomalie
                if 14 <= h <= 17:
                    base += rng.uniform(3, 8)
                elif h >= 22 or h <= 5:
                    base -= rng.uniform(5, 12)
                temperature = round(max(15.0, min(50.0, base + rng.gauss(0, 1.2))), 1)
                humidite = round(max(20.0, min(95.0, rng.gauss(hum_moy, 12))), 1)
                canicule = _canicule_niveau(temperature)

                p_coupure = 0.04 + 0.09 * canicule
                en_cours = rng.random() < p_coupure
                duree_reelle = (0.25 + rng.expovariate(1 / mu_zone)) if en_cours else 0.0
                coupures_jour += int(en_cours)

                coupures_7j = sum(historique[-7:]) + coupures_jour
                meteo[(ville, j, h)] = {
                    "temperature":  temperature,
                    "humidite":     humidite,
                    "ressentie":    round(temperature + rng.gauss(1.5, 1.0), 1),
                    "heat_index":   round(heat_index_c(temperature, humidite) or temperature, 1),
                    "canicule":     canicule,
                    "jours_chauds": jours_chauds,
                    "en_cours":     int(en_cours),
                    "duree_reelle": duree_reelle,          # caché
                    "coupures_7j":  coupures_7j,
                    "duree_moy":    round(mu_zone * rng.uniform(0.7, 1.3), 1) if coupures_7j > 0 else 0.0,
                    "mois":         dt_base.month,
                }
            historique.append(coupures_jour)

    return meteo


# ---------------------------------------------------------------------------
# Score latent de gravité → étiquette
# ---------------------------------------------------------------------------

def _gravite(rng: random.Random, foyer: dict, m: dict, heure: int) -> float:
    """Score latent d'incident. N'utilise PAS ``rules.py``."""
    sensibilite = 0.0
    if foyer["nb_vitaux"] > 0:
        sensibilite = 1.6 + 0.15 * (foyer["nb_vitaux"] - 1)
    elif foyer["nb_medicaux"] > 0:
        sensibilite = 0.9
    elif foyer["criticite_max"] >= 1:
        sensibilite = 0.35
    sensibilite += 0.05 * foyer["nb_equipements"]

    D = m["duree_reelle"]
    batterie = rng.uniform(0.2, 1.0)                                  # caché
    autonomie = batterie * 4.0 if foyer["a_onduleur"] else 0.0
    non_couvert = max(0.0, D - autonomie)

    if m["en_cours"]:
        expo = 0.55 + 0.38 * min(non_couvert, 8.0)
    else:
        expo = 0.30 + 0.08 * min(m["coupures_7j"], 5)

    chaleur = max(0.0, min(1.5, (m["heat_index"] - 30.0) / 10.0))
    if 12 <= heure <= 17:
        chaleur *= 1.2
    if foyer["a_clim"] and not m["en_cours"]:
        chaleur *= 0.6                                                # clim/ventilo en marche
    expo = expo * (1.0 + 0.8 * chaleur) + 0.25 * chaleur

    score = sensibilite * expo

    if foyer["a_refrigerateur"] and m["en_cours"]:
        couvert = foyer["a_onduleur"] and autonomie >= D
        score += 0.30 * min(D / 6.0, 1.0) * (0.3 if couvert else 1.0)

    if sensibilite > 0.3:
        score += 0.35 * foyer["vulnerabilite"]

    return score + rng.gauss(0, 0.30)


def _etiquette(score: float) -> str:
    if score >= SEUIL_CRITIQUE:
        return "critique"
    if score >= SEUIL_ELEVE:
        return "eleve"
    if score >= SEUIL_MODERE:
        return "modere"
    return "faible"


def main() -> int:
    rng = random.Random(RANDOM_SEED)
    OUTPUT_PATH.parent.mkdir(parents=True, exist_ok=True)

    foyers = [_generer_foyer(rng, fid) for fid in range(1, N_FOYERS + 1)]
    meteo = _generer_meteo(rng)

    lignes: list[dict] = []
    for foyer in foyers:
        for j in range(N_JOURS):
            dt_base = DATE_DEBUT + timedelta(days=j)
            for h in HEURES_SNAPSHOT:
                m = meteo[(foyer["ville"], j, h)]
                score = _gravite(rng, foyer, m, h)
                lignes.append({
                    "foyer_id":        foyer["foyer_id"],
                    "timestamp":       dt_base.replace(hour=h).isoformat(),
                    "nb_equipements":  foyer["nb_equipements"],
                    "nb_medicaux":     foyer["nb_medicaux"],
                    "nb_vitaux":       foyer["nb_vitaux"],
                    "criticite_max":   foyer["criticite_max"],
                    "a_respirateur":   foyer["a_respirateur"],
                    "a_refrigerateur": foyer["a_refrigerateur"],
                    "a_onduleur":      foyer["a_onduleur"],
                    "temperature":     m["temperature"],
                    "ressentie":       m["ressentie"],
                    "humidite":        m["humidite"],
                    "heat_index":      m["heat_index"],
                    "canicule_niveau": m["canicule"],
                    "jours_chauds_consecutifs": m["jours_chauds"],
                    "coupures_en_cours":        m["en_cours"],
                    "coupures_7j":              m["coupures_7j"],
                    "duree_moy_coupures_h":     m["duree_moy"],
                    "mois":            m["mois"],
                    "heure":           h,
                    "ville":           foyer["ville"],
                    "label":           _etiquette(score),
                    "source":          "sim_outcome",
                })

    with open(OUTPUT_PATH, "w", newline="", encoding="utf-8") as fh:
        writer = csv.DictWriter(fh, fieldnames=COLONNES)
        writer.writeheader()
        writer.writerows(lignes)

    dist = Counter(l["label"] for l in lignes)
    total = len(lignes)
    print(f"Dataset synthétique généré : {total} lignes -> {OUTPUT_PATH}")
    print("  Distribution des étiquettes :")
    for classe in ["faible", "modere", "eleve", "critique"]:
        n = dist.get(classe, 0)
        print(f"    {classe:10s} : {n:6d}  ({100 * n / total:.1f} %)")
    print("AVERTISSEMENT : données 100 % synthétiques ; étiquette = gravité simulée "
          "(variables cachées + bruit), indépendante de rules.py.")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
